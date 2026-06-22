<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woocommerce_connections', function (Blueprint $table) {
            $table->string('connection_mode', 20)->default('rest')->after('store_url');
            $table->timestamp('plugin_last_ping_at')->nullable()->after('external_webhook_ids');
            $table->string('plugin_version', 32)->nullable()->after('plugin_last_ping_at');
        });
    }

    public function down(): void
    {
        Schema::table('woocommerce_connections', function (Blueprint $table) {
            $table->dropColumn(['connection_mode', 'plugin_last_ping_at', 'plugin_version']);
        });
    }
};
