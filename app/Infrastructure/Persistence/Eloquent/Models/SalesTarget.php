<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesTarget extends TenantWorkspaceModel
{
    public const SCOPE_DEPARTMENT = 'department';

    public const SCOPE_USER = 'user';

    protected $fillable = [
        'tenant_id',
        'workspace_id',
        'scope',
        'department',
        'user_id',
        'jyear',
        'jmonth',
        'revenue_target',
        'deals_target',
        'notes',
        'set_by',
    ];

    protected function casts(): array
    {
        return [
            'jyear' => 'integer',
            'jmonth' => 'integer',
            'revenue_target' => 'decimal:2',
            'deals_target' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function setter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }
}
