<?php

namespace App\Application\Sms;

use App\Infrastructure\Persistence\Eloquent\Models\Activity;
use App\Infrastructure\Persistence\Eloquent\Models\Contact;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use App\Infrastructure\Persistence\Eloquent\Models\SmsMessage;
use App\Infrastructure\Persistence\Eloquent\Models\SmsMessageRecipient;
use App\Infrastructure\Persistence\Eloquent\Models\SmsTemplate;
use App\Infrastructure\Persistence\Eloquent\Models\TenantSmsAccount;
use App\Infrastructure\Services\Sms\IppanelTenantClient;
use App\Infrastructure\Services\TenantContext;
use App\Jobs\SendSmsBatchJob;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SmsService
{
    public function __construct(
        protected IppanelTenantClient $ippanel,
        protected SmsAudienceService $audience,
        protected TenantSmsProvisioningService $provisioning,
        protected TenantContext $tenantContext,
    ) {}

    public function requireActiveAccount(): TenantSmsAccount
    {
        $account = TenantSmsAccount::where('tenant_id', $this->tenantContext->tenantId())->first();

        if (! $account?->isActive()) {
            throw new RuntimeException('پنل پیامک فعال نیست.');
        }

        return $account;
    }

    public function dashboard(): array
    {
        $account = $this->requireActiveAccount();
        $account = $this->provisioning->syncCredit($account);

        $tenantId = $this->tenantContext->tenantId();
        $today = SmsMessage::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereDate('created_at', today())
            ->count();

        $month = SmsMessage::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        $recent = SmsMessage::query()
            ->with('sender:id,name')
            ->latest()
            ->limit(10)
            ->get();

        return [
            'credit' => (float) $account->credit_cached,
            'credit_synced_at' => $account->credit_synced_at,
            'sent_today' => $today,
            'sent_month' => $month,
            'default_from_number' => $account->default_from_number,
            'recent_messages' => $recent,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function previewAudience(User $user, array $filters): array
    {
        return $this->audience->build($user, $filters);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(User $user, array $payload): SmsMessage
    {
        $account = $this->requireActiveAccount();
        $fromNumber = $payload['from_number'] ?? $account->default_from_number;

        if (! $fromNumber) {
            throw new RuntimeException('خط ارسال تنظیم نشده است.');
        }

        $audience = [];

        if (! empty($payload['message']) && ! empty($payload['phone'])) {
            $phone = PhoneNormalizer::toE164($payload['phone']);
            if (! $phone) {
                throw new RuntimeException('شماره گیرنده معتبر نیست.');
            }
            $audience = [[
                'phone' => $phone,
                'lead_id' => $payload['lead_id'] ?? null,
                'contact_id' => $payload['contact_id'] ?? null,
            ]];
        } else {
            $built = $this->audience->build($user, $payload);
            $audience = $built['recipients']->all();

            if (count($audience) === 0) {
                throw new RuntimeException('گیرنده معتبری یافت نشد.');
            }
        }

        $messageText = $payload['message'] ?? '';
        $scheduledAt = ! empty($payload['scheduled_at']) ? Carbon::parse($payload['scheduled_at']) : null;

        return DB::transaction(function () use ($user, $account, $fromNumber, $audience, $messageText, $payload, $scheduledAt) {
            $sms = SmsMessage::create([
                'user_id' => $user->id,
                'type' => count($audience) > 1 ? 'bulk' : 'single',
                'from_number' => $fromNumber,
                'body' => $messageText,
                'recipients_count' => count($audience),
                'status' => SmsMessage::STATUS_QUEUED,
                'related_type' => $payload['related_type'] ?? null,
                'related_id' => $payload['related_id'] ?? null,
                'scheduled_at' => $scheduledAt,
            ]);

            foreach ($audience as $row) {
                SmsMessageRecipient::create([
                    'sms_message_id' => $sms->id,
                    'phone' => $row['phone'],
                    'lead_id' => $row['lead_id'] ?? null,
                    'contact_id' => $row['contact_id'] ?? null,
                ]);
            }

            if ($scheduledAt && $scheduledAt->isFuture()) {
                SendSmsBatchJob::dispatch($sms->id)->delay($scheduledAt);
            } else {
                SendSmsBatchJob::dispatch($sms->id);
            }

            return $sms->load('recipients');
        });
    }

    public function processMessage(int $messageId): SmsMessage
    {
        $sms = SmsMessage::withoutGlobalScopes()->with('recipients')->findOrFail($messageId);
        $account = TenantSmsAccount::where('tenant_id', $sms->tenant_id)->firstOrFail();

        if (! $account->isActive()) {
            $sms->update([
                'status' => SmsMessage::STATUS_FAILED,
                'error_message' => 'پنل پیامک غیرفعال است.',
            ]);

            return $sms;
        }

        $batchSize = config('sms.bulk_batch_size', 100);
        $phones = $sms->recipients->pluck('phone')->all();
        $outboxIds = [];

        try {
            foreach (array_chunk($phones, $batchSize) as $chunk) {
                $response = $this->ippanel->sendWebservice(
                    $account,
                    $sms->from_number,
                    (string) $sms->body,
                    $chunk,
                    $sms->scheduled_at?->format('Y-m-d H:i:s'),
                );

                $ids = $response['data']['message_outbox_ids'] ?? [];
                $outboxIds = array_merge($outboxIds, $ids);
            }

            $sms->recipients()->update(['delivery_status' => 'sent']);

            $sms->update([
                'status' => SmsMessage::STATUS_SENT,
                'ippanel_outbox_ids' => $outboxIds,
                'sent_at' => now(),
            ]);

            $this->logActivities($sms);
            $this->provisioning->syncCredit($account);
        } catch (\Throwable $e) {
            $sms->update([
                'status' => SmsMessage::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
        }

        return $sms->fresh('recipients');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createTemplate(array $data): SmsTemplate
    {
        return SmsTemplate::create([
            'title' => $data['title'],
            'body' => $data['body'],
            'ippanel_pattern_code' => $data['ippanel_pattern_code'] ?? null,
            'variables' => $data['variables'] ?? null,
        ]);
    }

    protected function logActivities(SmsMessage $sms): void
    {
        $bodyPreview = mb_substr((string) $sms->body, 0, 120);

        foreach ($sms->recipients as $recipient) {
            if (! $recipient->lead_id && ! $recipient->contact_id) {
                continue;
            }

            Activity::create([
                'tenant_id' => $sms->tenant_id,
                'workspace_id' => $sms->workspace_id,
                'user_id' => $sms->user_id,
                'type' => 'sms',
                'subject' => 'ارسال پیامک',
                'body' => $bodyPreview,
                'happened_at' => now(),
                'related_type' => $recipient->lead_id ? Lead::class : Contact::class,
                'related_id' => $recipient->lead_id ?? $recipient->contact_id,
            ]);
        }
    }
}
