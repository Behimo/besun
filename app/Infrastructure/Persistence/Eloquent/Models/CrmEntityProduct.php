<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmEntityProduct extends TenantWorkspaceModel
{
    protected $fillable = [
        'tenant_id',
        'workspace_id',
        'product_id',
        'entity_type',
        'entity_id',
        'quantity',
        'notes',
        'sort_order',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
