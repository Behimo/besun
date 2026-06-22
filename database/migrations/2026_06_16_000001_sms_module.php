<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_sms_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('draft');
            $table->unsignedBigInteger('ippanel_user_id')->nullable();
            $table->string('ippanel_username')->nullable();
            $table->text('api_key_encrypted')->nullable();
            $table->text('password_encrypted')->nullable();
            $table->string('default_from_number')->nullable();
            $table->unsignedBigInteger('acl_id')->nullable();
            $table->decimal('credit_cached', 16, 4)->nullable();
            $table->timestamp('credit_synced_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_sms_panel_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('pending');
            $table->string('name_family');
            $table->string('company')->nullable();
            $table->string('national_code', 10);
            $table->string('mobile_number', 11);
            $table->date('birth_date');
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('sms_credit_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('ippanel_package_id');
            $table->string('package_name')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('status', 32)->default('pending');
            $table->string('gateway_reference')->nullable();
            $table->json('ippanel_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('ippanel_pattern_code')->nullable();
            $table->json('variables')->nullable();
            $table->timestamps();
        });

        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32)->default('single');
            $table->string('from_number');
            $table->text('body')->nullable();
            $table->string('pattern_code')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->json('ippanel_outbox_ids')->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['related_type', 'related_id']);
        });

        Schema::create('sms_message_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sms_message_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 20);
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('delivery_status', 32)->default('pending');
            $table->json('ippanel_meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_message_recipients');
        Schema::dropIfExists('sms_messages');
        Schema::dropIfExists('sms_templates');
        Schema::dropIfExists('sms_credit_orders');
        Schema::dropIfExists('tenant_sms_panel_requests');
        Schema::dropIfExists('tenant_sms_accounts');
    }
};
