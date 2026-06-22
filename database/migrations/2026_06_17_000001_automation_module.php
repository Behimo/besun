<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('trigger_event', 64);
            $table->json('conditions')->nullable();
            $table->json('actions');
            $table->json('runtime_state')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('run_count')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'workspace_id', 'trigger_event', 'is_active'], 'auto_rules_tenant_trigger_idx');
        });

        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('automation_rule_id')->constrained()->cascadeOnDelete();
            $table->string('trigger_event', 64);
            $table->string('entity_type', 32);
            $table->unsignedBigInteger('entity_id');
            $table->string('status', 32);
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('executed_at');
            $table->timestamps();

            $table->index(['tenant_id', 'executed_at']);
            $table->index(['automation_rule_id', 'executed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automation_rules');
    }
};
