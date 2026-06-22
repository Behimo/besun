<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woocommerce_connections', function (Blueprint $table) {
            $table->json('plugin_pending_commands')->nullable()->after('plugin_version');
        });
    }

    public function down(): void
    {
        Schema::table('woocommerce_connections', function (Blueprint $table) {
            $table->dropColumn('plugin_pending_commands');
        });
    }
};
