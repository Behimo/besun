<?php

use App\Infrastructure\Persistence\Eloquent\Models\PipelineStage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_handoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 10);
            $table->unsignedBigInteger('entity_id');
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('from_stage_id')->nullable()->constrained('pipeline_stages')->nullOnDelete();
            $table->foreignId('to_stage_id')->nullable()->constrained('pipeline_stages')->nullOnDelete();
            $table->string('handoff_type', 20)->default('assign');
            $table->text('note')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('returned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('parent_handoff_id')->nullable()->constrained('crm_handoffs')->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'workspace_id', 'status'], 'crm_handoffs_tenant_status_idx');
            $table->index(['entity_type', 'entity_id'], 'crm_handoffs_entity_idx');
            $table->index(['to_user_id', 'status'], 'crm_handoffs_to_user_status_idx');
        });

        $this->ensureFinanceStage();
    }

    protected function ensureFinanceStage(): void
    {
        $tenantIds = DB::table('pipeline_stages')
            ->where('type', 'sales')
            ->distinct()
            ->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            $exists = DB::table('pipeline_stages')
                ->where('tenant_id', $tenantId)
                ->where('type', 'sales')
                ->where('name', 'مالی')
                ->exists();

            if ($exists) {
                continue;
            }

            $rows = DB::table('pipeline_stages')
                ->where('tenant_id', $tenantId)
                ->where('type', 'sales')
                ->orderBy('sort_order')
                ->get();

            if ($rows->isEmpty()) {
                continue;
            }

            $winner = $rows->firstWhere('name', 'برنده');
            $insertOrder = $winner?->sort_order ?? 4;

            DB::table('pipeline_stages')
                ->where('tenant_id', $tenantId)
                ->where('type', 'sales')
                ->where('sort_order', '>=', $insertOrder)
                ->increment('sort_order');

            $sample = $rows->first();

            DB::table('pipeline_stages')->insert([
                'tenant_id' => $tenantId,
                'workspace_id' => $sample->workspace_id,
                'name' => 'مالی',
                'sort_order' => $insertOrder,
                'color' => '#9C27B0',
                'type' => 'sales',
                'is_won' => false,
                'is_lost' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_handoffs');

        PipelineStage::query()
            ->withoutGlobalScopes()
            ->where('name', 'مالی')
            ->where('type', 'sales')
            ->delete();
    }
};
