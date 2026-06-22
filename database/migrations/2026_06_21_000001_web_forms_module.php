<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('public_token', 80)->unique();
            $table->text('description')->nullable();
            $table->json('schema')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('submissions_count')->default(0);
            $table->timestamp('last_submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'workspace_id', 'slug'], 'web_forms_tenant_workspace_slug_unique');
            $table->index(['tenant_id', 'workspace_id', 'is_active']);
        });

        Schema::create('web_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('web_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload');
            $table->string('status', 32)->default('received');
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['tenant_id', 'workspace_id', 'submitted_at']);
            $table->index(['web_form_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_form_submissions');
        Schema::dropIfExists('web_forms');
    }
};
