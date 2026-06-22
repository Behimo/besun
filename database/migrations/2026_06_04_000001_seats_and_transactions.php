<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('seat_limit')->nullable()->after('ends_at');
        });

        Schema::table('plan_modules', function (Blueprint $table) {
            $table->decimal('seat_monthly_price', 12, 0)->nullable()->after('annual_price');
            $table->decimal('seat_semi_annual_price', 12, 0)->nullable()->after('seat_monthly_price');
            $table->decimal('seat_annual_price', 12, 0)->nullable()->after('seat_semi_annual_price');
            $table->json('features')->nullable()->after('description');
        });

        Schema::create('subscription_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 0);
            $table->string('status', 32)->default('paid');
            $table->string('gateway_reference')->nullable();
            $table->json('items')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_transactions');

        Schema::table('plan_modules', function (Blueprint $table) {
            $table->dropColumn([
                'seat_monthly_price',
                'seat_semi_annual_price',
                'seat_annual_price',
                'features',
            ]);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('seat_limit');
        });
    }
};
