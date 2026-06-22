<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WooCommerceOrder extends TenantWorkspaceModel
{
    protected $table = 'woocommerce_orders';

    protected $fillable = [
        'tenant_id',
        'workspace_id',
        'woocommerce_connection_id',
        'external_order_id',
        'status',
        'total',
        'currency',
        'contact_id',
        'lead_id',
        'deal_id',
        'raw_payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'raw_payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(WooCommerceConnection::class, 'woocommerce_connection_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }
}
