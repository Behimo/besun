<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class WooCommerceConnection extends TenantWorkspaceModel
{
    protected $table = 'woocommerce_connections';

    protected $fillable = [
        'tenant_id',
        'workspace_id',
        'store_url',
        'connection_mode',
        'consumer_key',
        'consumer_secret',
        'is_active',
        'webhook_token',
        'webhook_secret',
        'order_sync_enabled',
        'campaign_id',
        'order_sync_from_date',
        'order_sync_run_status',
        'order_sync_run_progress',
        'last_sync_at',
        'last_sync_status',
        'last_sync_message',
        'last_order_sync_at',
        'last_order_sync_status',
        'last_order_sync_message',
        'external_webhook_ids',
        'plugin_last_ping_at',
        'plugin_version',
        'plugin_pending_commands',
    ];

    protected $hidden = [
        'consumer_key',
        'consumer_secret',
        'webhook_secret',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'order_sync_enabled' => 'boolean',
            'order_sync_from_date' => 'date',
            'order_sync_run_progress' => 'array',
            'last_sync_at' => 'datetime',
            'last_order_sync_at' => 'datetime',
            'plugin_last_ping_at' => 'datetime',
            'consumer_key' => 'encrypted',
            'consumer_secret' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'external_webhook_ids' => 'array',
            'plugin_pending_commands' => 'array',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(WooCommerceOrder::class, 'woocommerce_connection_id');
    }

    public function campaign(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function usesPluginBridge(): bool
    {
        return ($this->connection_mode ?? 'plugin') !== 'rest';
    }
}
