<?php

namespace App\Application\Integrations;

use App\Application\Automation\AutomationDispatcher;
use App\Application\Contact\ContactResolver;
use App\Application\Lead\LeadResolver;
use App\Application\Pipeline\PipelineTransitionLogger;
use App\Application\Product\CrmEntityProductService;
use App\Domain\Shared\Enums\Department;
use App\Infrastructure\Persistence\Eloquent\Models\Deal;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use App\Infrastructure\Persistence\Eloquent\Models\PipelineStage;
use App\Infrastructure\Persistence\Eloquent\Models\Product;
use App\Infrastructure\Persistence\Eloquent\Models\WooCommerceConnection;
use App\Infrastructure\Persistence\Eloquent\Models\WooCommerceOrder;
use App\Infrastructure\Services\TenantContext;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;

class WooCommerceOrderService
{
    public const LEAD_STATUSES = ['pending', 'on-hold', 'failed', 'cancelled'];

    public const WON_STATUSES = ['processing', 'completed'];

    public function __construct(
        protected TenantContext $tenantContext,
        protected ContactResolver $contactResolver,
        protected LeadResolver $leadResolver,
        protected PipelineTransitionLogger $transitions,
        protected AutomationDispatcher $automation,
        protected CrmEntityProductService $entityProducts,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function processOrder(WooCommerceConnection $connection, array $payload): WooCommerceOrder
    {
        $this->tenantContext->set($connection->tenant_id, $connection->workspace_id);

        if (! $connection->order_sync_enabled) {
            abort(422, 'همگام‌سازی سفارش غیرفعال است.');
        }

        $externalOrderId = (int) ($payload['id'] ?? 0);
        if ($externalOrderId <= 0) {
            abort(422, 'شناسه سفارش ووکامرس نامعتبر است.');
        }

        $status = (string) ($payload['status'] ?? '');
        $billing = $payload['billing'] ?? [];
        $customerData = $this->extractCustomerData($billing, $externalOrderId);
        $total = (float) ($payload['total'] ?? 0);
        $currency = (string) ($payload['currency'] ?? 'IRR');

        return DB::transaction(function () use (
            $connection,
            $payload,
            $externalOrderId,
            $status,
            $customerData,
            $total,
            $currency,
        ) {
            $orderRecord = WooCommerceOrder::query()
                ->where('woocommerce_connection_id', $connection->id)
                ->where('external_order_id', $externalOrderId)
                ->first();

            if (! $orderRecord) {
                $orderRecord = WooCommerceOrder::create([
                    'tenant_id' => $connection->tenant_id,
                    'workspace_id' => $connection->workspace_id,
                    'woocommerce_connection_id' => $connection->id,
                    'external_order_id' => $externalOrderId,
                    'status' => $status,
                    'total' => $total,
                    'currency' => $currency,
                    'raw_payload' => $payload,
                    'processed_at' => now(),
                ]);
            } else {
                $orderRecord->update([
                    'status' => $status,
                    'total' => $total,
                    'currency' => $currency,
                    'raw_payload' => $payload,
                    'processed_at' => now(),
                ]);
            }

            $contact = $this->contactResolver->findOrCreateFromLeadData(
                array_merge($customerData, [
                    'tenant_id' => $connection->tenant_id,
                    'workspace_id' => $connection->workspace_id,
                    'department' => Department::Sales->value,
                    'notes' => $this->orderNotes($externalOrderId, $status),
                ]),
                $connection->tenant_id,
                $connection->workspace_id,
            );

            $orderRecord->update(['contact_id' => $contact->id]);

            if ($this->shouldCreateLead($status)) {
                $lead = $this->ensureLead($connection, $orderRecord, $contact, $customerData, $externalOrderId, $status, dispatchCreated: true);
                $orderRecord->update(['lead_id' => $lead->id]);
                $this->attachOrderLineItems($connection, $payload, lead: $lead, contact: $contact);
            }

            if ($this->shouldCreateWonDeal($status)) {
                $lead = $orderRecord->lead_id
                    ? Lead::find($orderRecord->lead_id)
                    : $this->ensureLead($connection, $orderRecord, $contact, $customerData, $externalOrderId, $status, dispatchCreated: false);

                if ($lead && ! $orderRecord->lead_id) {
                    $orderRecord->update(['lead_id' => $lead->id]);
                }

                $deal = $this->ensureWonDeal($connection, $orderRecord, $contact, $lead, $externalOrderId, $total, $currency);
                $orderRecord->update(['deal_id' => $deal->id]);

                $this->attachOrderLineItems($connection, $payload, deal: $deal, lead: $lead, contact: $contact);
            }

            return $orderRecord->fresh(['contact', 'lead', 'deal']);
        });
    }

    protected function shouldCreateLead(string $status): bool
    {
        return in_array($status, self::LEAD_STATUSES, true);
    }

    protected function shouldCreateWonDeal(string $status): bool
    {
        return in_array($status, self::WON_STATUSES, true);
    }

    /**
     * @param  array<string, mixed>  $billing
     * @return array<string, mixed>
     */
    protected function extractCustomerData(array $billing, int $orderId): array
    {
        $firstName = trim((string) ($billing['first_name'] ?? ''));
        $lastName = trim((string) ($billing['last_name'] ?? ''));
        $name = trim($firstName.' '.$lastName);

        if ($name === '') {
            $name = 'مشتری ووکامرس #'.$orderId;
        }

        return [
            'name' => $name,
            'email' => ! empty($billing['email']) ? trim((string) $billing['email']) : null,
            'phone' => $this->normalizePhone((string) ($billing['phone'] ?? '')),
            'city' => ! empty($billing['city']) ? trim((string) $billing['city']) : null,
            'company' => ! empty($billing['company']) ? trim((string) $billing['company']) : null,
        ];
    }

    protected function normalizePhone(string $phone): ?string
    {
        return PhoneNormalizer::normalize($phone);
    }

    protected function orderNotes(int $orderId, string $status): string
    {
        return "سفارش ووکامرس #{$orderId} — وضعیت: {$status}";
    }

    /**
     * @param  array<string, mixed>  $customerData
     */
    protected function ensureLead(
        WooCommerceConnection $connection,
        WooCommerceOrder $orderRecord,
        $contact,
        array $customerData,
        int $externalOrderId,
        string $status,
        bool $dispatchCreated = false,
    ): Lead {
        if ($orderRecord->lead_id) {
            $lead = Lead::find($orderRecord->lead_id);
            if ($lead) {
                $lead->update([
                    'notes' => $this->orderNotes($externalOrderId, $status),
                    'contact_id' => $contact->id,
                ]);

                return $lead->fresh();
            }
        }

        $leadData = [
            'tenant_id' => $connection->tenant_id,
            'workspace_id' => $connection->workspace_id,
            'name' => $customerData['name'],
            'email' => $customerData['email'],
            'phone' => $customerData['phone'],
            'company' => $customerData['company'],
            'city' => $customerData['city'],
            'source' => 'woocommerce',
            'campaign_id' => $connection->campaign_id,
            'marketing_stage_id' => PipelineStage::query()
                ->where('type', 'marketing')
                ->orderBy('sort_order')
                ->value('id'),
            'status' => 'new',
            'department' => Department::Marketing->value,
            'contact_id' => $contact->id,
            'notes' => $this->orderNotes($externalOrderId, $status),
        ];

        $result = $this->leadResolver->findOrCreateFromData($leadData);
        $lead = $result['lead'];

        if ($result['created'] && $lead->marketing_stage_id) {
            $this->transitions->log('lead', $lead->id, null, $lead->marketing_stage_id);
        }

        if ($dispatchCreated && $result['created']) {
            $this->automation->dispatch('lead.created', $lead, ['source' => 'woocommerce']);
        }

        return $lead;
    }

    protected function ensureWonDeal(
        WooCommerceConnection $connection,
        WooCommerceOrder $orderRecord,
        $contact,
        ?Lead $lead,
        int $externalOrderId,
        float $total,
        string $currency,
    ): Deal {
        $wonStageId = PipelineStage::query()
            ->where('type', 'sales')
            ->where('is_won', true)
            ->orderBy('sort_order')
            ->value('id');

        if (! $wonStageId) {
            $wonStageId = PipelineStage::query()
                ->where('type', 'sales')
                ->orderByDesc('sort_order')
                ->value('id');
        }

        if ($orderRecord->deal_id) {
            $deal = Deal::find($orderRecord->deal_id);
            if ($deal) {
                $fromStageId = $deal->pipeline_stage_id;
                $deal->update([
                    'pipeline_stage_id' => $wonStageId,
                    'amount' => $total,
                    'currency' => $currency,
                    'contact_id' => $contact->id,
                    'lead_id' => $lead?->id,
                    'notes' => $this->orderNotes($externalOrderId, 'completed'),
                ]);

                if ($fromStageId !== $wonStageId) {
                    $this->transitions->log('deal', $deal->id, $fromStageId, $wonStageId);
                    $this->automation->dispatch('deal.stage_changed', $deal->fresh(), [
                        'from_stage_id' => $fromStageId,
                        'to_stage_id' => $wonStageId,
                        'source' => 'woocommerce',
                    ]);
                }

                return $deal->fresh();
            }
        }

        $deal = Deal::create([
            'tenant_id' => $connection->tenant_id,
            'workspace_id' => $connection->workspace_id,
            'pipeline_stage_id' => $wonStageId,
            'contact_id' => $contact->id,
            'lead_id' => $lead?->id,
            'title' => 'سفارش ووکامرس #'.$externalOrderId,
            'amount' => $total,
            'currency' => $currency,
            'department' => Department::Sales->value,
            'notes' => $this->orderNotes($externalOrderId, 'completed'),
        ]);

        $this->transitions->log('deal', $deal->id, null, $wonStageId);
        $this->automation->dispatch('deal.created', $deal, [
            'source' => 'woocommerce',
            'external_order_id' => $externalOrderId,
        ]);

        if ($lead && $lead->status !== 'converted') {
            $lead->update([
                'status' => 'converted',
                'converted_at' => now(),
                'contact_id' => $contact->id,
            ]);
        }

        return $deal;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function attachOrderLineItems(
        WooCommerceConnection $connection,
        array $payload,
        ?Deal $deal = null,
        ?Lead $lead = null,
        $contact = null,
    ): void {
        $lineItems = $payload['line_items'] ?? [];
        if (! is_array($lineItems) || $lineItems === []) {
            return;
        }

        foreach ($lineItems as $index => $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            $product = Product::query()
                ->where('woocommerce_connection_id', $connection->id)
                ->where('external_id', (string) $productId)
                ->first();

            if (! $product) {
                continue;
            }

            $attachData = [
                'quantity' => (int) ($item['quantity'] ?? 1),
                'sort_order' => $index,
            ];

            if ($deal) {
                $this->entityProducts->attach($deal, $product->id, $attachData);
            }

            if ($lead) {
                $this->entityProducts->attach($lead, $product->id, $attachData);
            }

            if ($contact) {
                $this->entityProducts->attach($contact, $product->id, $attachData);
            }
        }
    }
}
