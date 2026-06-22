<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

class SmsTemplate extends TenantWorkspaceModel
{
    protected $fillable = [
        'tenant_id',
        'workspace_id',
        'title',
        'body',
        'ippanel_pattern_code',
        'variables',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
        ];
    }
}
