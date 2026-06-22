<?php

namespace App\Infrastructure\Persistence\Eloquent\Concerns;

use App\Infrastructure\Persistence\Eloquent\Models\CrmEntityProduct;
use App\Infrastructure\Persistence\Eloquent\Models\Product;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasCrmProducts
{
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'crm_entity_products', 'entity_id', 'product_id')
            ->where('crm_entity_products.entity_type', $this->crmProductEntityType())
            ->withPivot(['quantity', 'notes', 'sort_order'])
            ->orderBy('crm_entity_products.sort_order');
    }

    public function crmProductLinks()
    {
        return $this->hasMany(CrmEntityProduct::class, 'entity_id')
            ->where('entity_type', $this->crmProductEntityType());
    }

    abstract protected function crmProductEntityType(): string;
}
