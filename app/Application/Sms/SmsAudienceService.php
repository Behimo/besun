<?php

namespace App\Application\Sms;

use App\Infrastructure\Persistence\Eloquent\Models\Campaign;
use App\Infrastructure\Persistence\Eloquent\Models\Contact;
use App\Infrastructure\Persistence\Eloquent\Models\Deal;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use App\Infrastructure\Services\TenantContext;
use App\Models\User;
use App\Support\DepartmentAccessService;
use App\Support\PhoneNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SmsAudienceService
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected DepartmentAccessService $departmentAccess,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{recipients: Collection<int, array{phone: string, lead_id: ?int, contact_id: ?int}>, total_records: int, valid_count: int, invalid_count: int}
     */
    public function build(User $user, array $filters): array
    {
        $tenantId = $this->tenantContext->tenantId();
        $audience = $filters['audience'] ?? 'leads';
        $records = collect();

        if (! empty($filters['phones']) && is_array($filters['phones'])) {
            foreach ($filters['phones'] as $phone) {
                $e164 = PhoneNormalizer::toE164($phone);
                if ($e164) {
                    $records->push([
                        'phone' => $e164,
                        'lead_id' => null,
                        'contact_id' => null,
                    ]);
                }
            }

            return $this->summarize($records, $records->count());
        }

        if (! empty($filters['ids']) && is_array($filters['ids'])) {
            $records = $this->fetchByIds($user, $tenantId, $audience, $filters['ids']);
        } else {
            $records = match ($audience) {
                'contacts' => $this->fetchContacts($user, $tenantId, $filters),
                'deals' => $this->fetchDeals($user, $tenantId, $filters),
                default => $this->fetchLeads($user, $tenantId, $filters),
            };
        }

        return $this->summarize($records, $records->count());
    }

    /**
     * @param  Collection<int, array{phone: string, lead_id: ?int, contact_id: ?int}>  $records
     * @return array{recipients: Collection<int, array{phone: string, lead_id: ?int, contact_id: ?int}>, total_records: int, valid_count: int, invalid_count: int}
     */
    protected function summarize(Collection $records, int $totalRecords): array
    {
        $unique = $records
            ->filter(fn ($row) => ! empty($row['phone']))
            ->unique('phone')
            ->values();

        return [
            'recipients' => $unique,
            'total_records' => $totalRecords,
            'valid_count' => $unique->count(),
            'invalid_count' => max(0, $totalRecords - $unique->count()),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{phone: string, lead_id: ?int, contact_id: ?int}>
     */
    protected function fetchLeads(User $user, int $tenantId, array $filters): Collection
    {
        $query = Lead::query()->whereNotNull('phone');

        if (! empty($filters['pipeline_stage_ids'])) {
            $query->whereIn('marketing_stage_id', $filters['pipeline_stage_ids']);
        }

        if (! empty($filters['campaign_id'])) {
            $query->where('campaign_id', $filters['campaign_id']);
        }

        if (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        $this->departmentAccess->scopeDepartmentRecords($query, $user, $tenantId);

        return $this->mapLeadPhones($query->get(['id', 'phone']));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{phone: string, lead_id: ?int, contact_id: ?int}>
     */
    protected function fetchContacts(User $user, int $tenantId, array $filters): Collection
    {
        $query = Contact::query()->whereNotNull('phone');

        if (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        $this->departmentAccess->scopeDepartmentRecords($query, $user, $tenantId);

        return $query->get(['id', 'phone'])->map(function ($contact) {
            $phone = PhoneNormalizer::toE164($contact->phone);

            return $phone ? [
                'phone' => $phone,
                'lead_id' => null,
                'contact_id' => $contact->id,
            ] : null;
        })->filter()->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{phone: string, lead_id: ?int, contact_id: ?int}>
     */
    protected function fetchDeals(User $user, int $tenantId, array $filters): Collection
    {
        $query = Deal::query()
            ->with('contact:id,phone')
            ->whereHas('contact', fn (Builder $q) => $q->whereNotNull('phone'));

        if (! empty($filters['pipeline_stage_ids'])) {
            $query->whereIn('pipeline_stage_id', $filters['pipeline_stage_ids']);
        }

        $this->departmentAccess->scopeDepartmentRecords($query, $user, $tenantId);

        return $query->get()->map(function ($deal) {
            $phone = PhoneNormalizer::toE164($deal->contact?->phone);

            return $phone ? [
                'phone' => $phone,
                'lead_id' => null,
                'contact_id' => $deal->contact_id,
            ] : null;
        })->filter()->values();
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, array{phone: string, lead_id: ?int, contact_id: ?int}>
     */
    protected function fetchByIds(User $user, int $tenantId, string $audience, array $ids): Collection
    {
        return match ($audience) {
            'contacts' => Contact::query()
                ->whereIn('id', $ids)
                ->tap(fn ($q) => $this->departmentAccess->scopeDepartmentRecords($q, $user, $tenantId))
                ->get(['id', 'phone'])
                ->map(fn ($c) => $this->phoneRow($c->phone, null, $c->id))
                ->filter()
                ->values(),
            'deals' => Deal::query()
                ->with('contact:id,phone')
                ->whereIn('id', $ids)
                ->tap(fn ($q) => $this->departmentAccess->scopeDepartmentRecords($q, $user, $tenantId))
                ->get()
                ->map(fn ($d) => $this->phoneRow($d->contact?->phone, null, $d->contact_id))
                ->filter()
                ->values(),
            default => $this->mapLeadPhones(
                Lead::query()
                    ->whereIn('id', $ids)
                    ->tap(fn ($q) => $this->departmentAccess->scopeDepartmentRecords($q, $user, $tenantId))
                    ->get(['id', 'phone']),
            ),
        };
    }

    /**
     * @return Collection<int, array{phone: string, lead_id: ?int, contact_id: ?int}>
     */
    protected function mapLeadPhones($leads): Collection
    {
        return collect($leads)->map(fn ($lead) => $this->phoneRow($lead->phone, $lead->id, null))->filter()->values();
    }

    /**
     * @return array{phone: string, lead_id: ?int, contact_id: ?int}|null
     */
    protected function phoneRow(?string $phone, ?int $leadId, ?int $contactId): ?array
    {
        $e164 = PhoneNormalizer::toE164($phone);

        if (! $e164) {
            return null;
        }

        return [
            'phone' => $e164,
            'lead_id' => $leadId,
            'contact_id' => $contactId,
        ];
    }

    public function resolveCampaign(int $campaignId): ?Campaign
    {
        return Campaign::find($campaignId);
    }
}
