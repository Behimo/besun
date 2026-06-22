<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pipeline_stages', 'is_won')) {
            Schema::table('pipeline_stages', function (Blueprint $table) {
                $table->boolean('is_won')->default(false)->after('type');
                $table->boolean('is_lost')->default(false)->after('is_won');
            });
        }

        if (! Schema::hasColumn('leads', 'converted_at')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->timestamp('converted_at')->nullable()->after('status');
            });
        }

        if (! Schema::hasTable('pipeline_stage_transitions')) {
            Schema::create('pipeline_stage_transitions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('entity_type', 20);
                $table->unsignedBigInteger('entity_id');
                $table->foreignId('from_stage_id')->nullable()->constrained('pipeline_stages')->nullOnDelete();
                $table->foreignId('to_stage_id')->constrained('pipeline_stages')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamp('transitioned_at');
                $table->timestamps();

                $table->index(['tenant_id', 'workspace_id', 'entity_type', 'transitioned_at'], 'pst_tenant_ws_type_date_idx');
                $table->index(['entity_type', 'entity_id'], 'pst_entity_idx');
            });
        }

        DB::table('pipeline_stages')->where('name', 'برنده')->update(['is_won' => true]);
        DB::table('pipeline_stages')->where('name', 'باخته')->update(['is_lost' => true]);
        DB::table('pipeline_stages')->where('name', 'نامرتبط')->update(['is_lost' => true]);

        DB::table('leads')
            ->where('status', 'converted')
            ->whereNull('converted_at')
            ->update(['converted_at' => DB::raw('updated_at')]);

        if (DB::table('pipeline_stage_transitions')->count() === 0) {
            $now = now();

            foreach (DB::table('deals')->select('id', 'tenant_id', 'workspace_id', 'pipeline_stage_id', 'created_at')->get() as $deal) {
                DB::table('pipeline_stage_transitions')->insert([
                    'tenant_id' => $deal->tenant_id,
                    'workspace_id' => $deal->workspace_id,
                    'entity_type' => 'deal',
                    'entity_id' => $deal->id,
                    'from_stage_id' => null,
                    'to_stage_id' => $deal->pipeline_stage_id,
                    'user_id' => null,
                    'transitioned_at' => $deal->created_at ?? $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach (DB::table('leads')->select('id', 'tenant_id', 'workspace_id', 'marketing_stage_id', 'created_at')->whereNotNull('marketing_stage_id')->get() as $lead) {
                DB::table('pipeline_stage_transitions')->insert([
                    'tenant_id' => $lead->tenant_id,
                    'workspace_id' => $lead->workspace_id,
                    'entity_type' => 'lead',
                    'entity_id' => $lead->id,
                    'from_stage_id' => null,
                    'to_stage_id' => $lead->marketing_stage_id,
                    'user_id' => null,
                    'transitioned_at' => $lead->created_at ?? $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_stage_transitions');

        if (Schema::hasColumn('leads', 'converted_at')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropColumn('converted_at');
            });
        }

        if (Schema::hasColumn('pipeline_stages', 'is_won')) {
            Schema::table('pipeline_stages', function (Blueprint $table) {
                $table->dropColumn(['is_won', 'is_lost']);
            });
        }
    }
};
