<?php

namespace App\Application\Product;

use App\Infrastructure\Persistence\Eloquent\Models\CrmEntityProduct;
use App\Infrastructure\Persistence\Eloquent\Models\Deal;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use App\Infrastructure\Persistence\Eloquent\Models\Product;
use App\Infrastructure\Services\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CrmEntityProductService
{
    public function __construct(
        protected TenantContext $tenantContext,
    ) {}

    public function sync(Model $entity, array $items): Collection
    {
        $entityType = $this->resolveEntityType($entity);
        $entityId = $entity->id;

        CrmEntityProduct::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->delete();

        $links = collect();

        foreach ($items as $index => $item) {
            $productId = $item['product_id'] ?? null;
            if (! $productId) {
                continue;
            }

            Product::query()->findOrFail($productId);

            $links->push(CrmEntityProduct::create([
                'tenant_id' => $this->tenantContext->tenantId(),
                'workspace_id' => $this->tenantContext->workspaceId(),
                'product_id' => $productId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'quantity' => $item['quantity'] ?? 1,
                'notes' => $item['notes'] ?? null,
                'sort_order' => $item['sort_order'] ?? $index,
            ]));
        }

        return $links;
    }

    public function attach(Model $entity, int $productId, array $data = []): CrmEntityProduct
    {
        $entityType = $this->resolveEntityType($entity);

        Product::query()->findOrFail($productId);

        return CrmEntityProduct::updateOrCreate(
            [
                'product_id' => $productId,
                'entity_type' => $entityType,
                'entity_id' => $entity->id,
            ],
            [
                'tenant_id' => $this->tenantContext->tenantId(),
                'workspace_id' => $this->tenantContext->workspaceId(),
                'quantity' => $data['quantity'] ?? 1,
                'notes' => $data['notes'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
            ],
        );
    }

    public function detach(Model $entity, int $productId): void
    {
        CrmEntityProduct::query()
            ->where('entity_type', $this->resolveEntityType($entity))
            ->where('entity_id', $entity->id)
            ->where('product_id', $productId)
            ->delete();
    }

    public function copyFromLeadToDeal(Lead $lead, Deal $deal): void
    {
        $links = CrmEntityProduct::query()
            ->where('entity_type', 'lead')
            ->where('entity_id', $lead->id)
            ->get();

        foreach ($links as $link) {
            CrmEntityProduct::updateOrCreate(
                [
                    'product_id' => $link->product_id,
                    'entity_type' => 'deal',
                    'entity_id' => $deal->id,
                ],
                [
                    'tenant_id' => $link->tenant_id,
                    'workspace_id' => $link->workspace_id,
                    'quantity' => $link->quantity,
                    'notes' => $link->notes,
                    'sort_order' => $link->sort_order,
                ],
            );
        }
    }

    protected function resolveEntityType(Model $entity): string
    {
        return match ($entity::class) {
            Lead::class => 'lead',
            Deal::class => 'deal',
            default => strtolower(class_basename($entity)),
        };
    }
}
