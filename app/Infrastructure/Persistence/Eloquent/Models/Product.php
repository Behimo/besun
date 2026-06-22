<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends TenantWorkspaceModel
{
    protected $fillable = [
        'tenant_id',
        'workspace_id',
        'product_category_id',
        'woocommerce_connection_id',
        'name',
        'slug',
        'sku',
        'description',
        'short_description',
        'price',
        'sale_price',
        'currency',
        'stock_quantity',
        'stock_status',
        'image_url',
        'gallery',
        'status',
        'source',
        'external_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'gallery' => 'array',
            'metadata' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function woocommerceConnection(): BelongsTo
    {
        return $this->belongsTo(WooCommerceConnection::class);
    }
}
