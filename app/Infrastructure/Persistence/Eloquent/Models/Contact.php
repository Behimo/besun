<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasCrmProducts;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends TenantWorkspaceModel
{
    use HasCrmProducts;
    protected $fillable = [
        'tenant_id',
        'workspace_id',
        'name',
        'email',
        'phone',
        'company',
        'job_title',
        'city',
        'notes',
        'tags',
        'custom_fields',
        'assigned_to',
        'department',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'custom_fields' => 'array',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    protected function crmProductEntityType(): string
    {
        return 'contact';
    }
}
