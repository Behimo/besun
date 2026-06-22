<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WebForm extends TenantWorkspaceModel
{
    protected $fillable = [
        'tenant_id',
        'workspace_id',
        'name',
        'slug',
        'public_token',
        'description',
        'schema',
        'settings',
        'is_active',
        'submissions_count',
        'last_submitted_at',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (self $form): void {
            if (! $form->public_token) {
                $form->public_token = Str::random(48);
            }

            if (! $form->slug) {
                $form->slug = Str::slug($form->name) ?: Str::random(8);
            }
        });
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(WebFormSubmission::class);
    }

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'settings' => 'array',
            'is_active' => 'boolean',
            'last_submitted_at' => 'datetime',
        ];
    }
}
