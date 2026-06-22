<?php

namespace App\Application\Quote;

use App\Infrastructure\Persistence\Eloquent\Models\Quote;
use App\Infrastructure\Persistence\Eloquent\Models\QuoteLineItem;
use App\Infrastructure\Services\TenantContext;
use Illuminate\Support\Str;

class QuoteService
{
    public function __construct(
        protected TenantContext $tenantContext,
    ) {}

    public function generateNumber(): string
    {
        $tenantId = $this->tenantContext->tenantId();
        $count = Quote::query()->where('tenant_id', $tenantId)->count() + 1;

        return 'PF-'.$tenantId.'-'.str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }

    public function syncLineItems(Quote $quote, array $items): Quote
    {
        $quote->lineItems()->delete();

        $subtotal = 0;

        foreach ($items as $index => $item) {
            $quantity = (int) ($item['quantity'] ?? 1);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $discount = (float) ($item['discount'] ?? 0);
            $lineTotal = max(0, ($quantity * $unitPrice) - $discount);

            QuoteLineItem::create([
                'quote_id' => $quote->id,
                'product_id' => $item['product_id'] ?? null,
                'description' => $item['description'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount' => $discount,
                'line_total' => $lineTotal,
                'sort_order' => $item['sort_order'] ?? $index,
            ]);

            $subtotal += $lineTotal;
        }

        $discount = (float) ($quote->discount ?? 0);
        $tax = (float) ($quote->tax ?? 0);
        $total = max(0, $subtotal - $discount + $tax);

        $quote->update([
            'subtotal' => $subtotal,
            'total' => $total,
        ]);

        return $quote->fresh(['lineItems.product', 'contact', 'deal', 'lead', 'issuer:id,name']);
    }

    public static function uniqueSlug(string $name, ?int $excludeId = null): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $counter = 1;

        while (true) {
            $query = \App\Infrastructure\Persistence\Eloquent\Models\Product::query()->where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            if (! $query->exists()) {
                return $slug;
            }
            $slug = $base.'-'.$counter;
            $counter++;
        }
    }
}
