<?php

use App\Application\Integrations\WooCommerceOrderSyncService;
use App\Infrastructure\Persistence\Eloquent\Models\WooCommerceConnection;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reminders:process')->everyMinute();

Schedule::call(function () {
    $syncService = app(WooCommerceOrderSyncService::class);

    WooCommerceConnection::withoutGlobalScopes()
        ->where('is_active', true)
        ->where('order_sync_enabled', true)
        ->where('order_sync_run_status', '!=', 'running')
        ->get()
        ->each(fn (WooCommerceConnection $connection) => $syncService->syncIncremental($connection));
})->everyFifteenMinutes();
