<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woocommerce_connections', function (Blueprint $table) {
            $table->text('consumer_key')->nullable()->change();
            $table->text('consumer_secret')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('woocommerce_connections', function (Blueprint $table) {
            $table->text('consumer_key')->nullable(false)->change();
            $table->text('consumer_secret')->nullable(false)->change();
        });
    }
};
