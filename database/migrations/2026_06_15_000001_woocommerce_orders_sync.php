<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woocommerce_connections', function (Blueprint $table) {
            $table->string('webhook_token', 64)->nullable()->unique()->after('is_active');
            $table->text('webhook_secret')->nullable()->after('webhook_token');
            $table->boolean('order_sync_enabled')->default(true)->after('webhook_secret');
            $table->foreignId('campaign_id')->nullable()->after('order_sync_enabled')->constrained('campaigns')->nullOnDelete();
            $table->timestamp('last_order_sync_at')->nullable()->after('last_sync_message');
            $table->string('last_order_sync_status')->nullable()->after('last_order_sync_at');
            $table->text('last_order_sync_message')->nullable()->after('last_order_sync_status');
            $table->json('external_webhook_ids')->nullable()->after('last_order_sync_message');
        });

        Schema::create('woocommerce_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('woocommerce_connection_id')->constrained('woocommerce_connections')->cascadeOnDelete();
            $table->unsignedBigInteger('external_order_id');
            $table->string('status', 50);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('currency', 10)->default('IRR');
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained('deals')->nullOnDelete();
            $table->json('raw_payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['woocommerce_connection_id', 'external_order_id'], 'woocommerce_orders_connection_external_unique');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('woocommerce_orders');

        Schema::table('woocommerce_connections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_id');
            $table->dropColumn([
                'webhook_token',
                'webhook_secret',
                'order_sync_enabled',
                'last_order_sync_at',
                'last_order_sync_status',
                'last_order_sync_message',
                'external_webhook_ids',
            ]);
        });
    }
};
