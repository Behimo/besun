<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_modules', function (Blueprint $table) {
            $table->boolean('is_core')->default(false)->after('slug');
            $table->decimal('monthly_price', 12, 0)->nullable()->after('price');
            $table->decimal('semi_annual_price', 12, 0)->nullable()->after('monthly_price');
            $table->decimal('annual_price', 12, 0)->nullable()->after('semi_annual_price');
        });

        Schema::table('subscription_modules', function (Blueprint $table) {
            $table->string('status')->default('active')->after('plan_module_id');
            $table->string('subscription_type')->nullable()->after('status');
            $table->timestamp('expires_at')->nullable()->after('subscription_type');
            $table->decimal('price_paid', 12, 0)->nullable()->after('expires_at');
            $table->timestamp('purchased_at')->nullable()->after('price_paid');
        });

        Schema::table('tenant_user', function (Blueprint $table) {
            $table->timestamp('joined_at')->nullable()->after('user_id');
            $table->foreignId('invited_by')->nullable()->after('joined_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('in_tenant_shell')->default(false)->after('current_workspace_id');
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('invited_phone', 11);
            $table->foreignId('invited_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role', 32);
            $table->string('status', 32)->default('pending');
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'invited_phone', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('in_tenant_shell');
        });

        Schema::table('tenant_user', function (Blueprint $table) {
            $table->dropForeign(['invited_by']);
            $table->dropColumn(['joined_at', 'invited_by']);
        });

        Schema::table('subscription_modules', function (Blueprint $table) {
            $table->dropColumn(['status', 'subscription_type', 'expires_at', 'price_paid', 'purchased_at']);
        });

        Schema::table('plan_modules', function (Blueprint $table) {
            $table->dropColumn(['is_core', 'monthly_price', 'semi_annual_price', 'annual_price']);
        });
    }
};
