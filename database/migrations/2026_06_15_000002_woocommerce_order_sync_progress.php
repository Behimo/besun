<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woocommerce_connections', function (Blueprint $table) {
            $table->date('order_sync_from_date')->nullable()->after('campaign_id');
            $table->string('order_sync_run_status', 20)->default('idle')->after('order_sync_from_date');
            $table->json('order_sync_run_progress')->nullable()->after('order_sync_run_status');
        });
    }

    public function down(): void
    {
        Schema::table('woocommerce_connections', function (Blueprint $table) {
            $table->dropColumn([
                'order_sync_from_date',
                'order_sync_run_status',
                'order_sync_run_progress',
            ]);
        });
    }
};
