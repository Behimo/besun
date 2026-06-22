<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pipeline_stages', function (Blueprint $table) {
            $table->string('type')->default('sales')->after('color');
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->string('channel')->nullable();
            $table->decimal('budget', 14, 2)->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->after('workspace_id')->constrained()->nullOnDelete();
            $table->foreignId('marketing_stage_id')->nullable()->after('campaign_id')->constrained('pipeline_stages')->nullOnDelete();
            $table->string('job_title')->nullable()->after('company');
            $table->string('city')->nullable()->after('job_title');
            $table->unsignedTinyInteger('score')->nullable()->after('city');
        });

        Schema::create('tenant_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        DB::table('pipeline_stages')->update(['type' => 'sales']);

        $marketingStages = config('crm_pipeline.marketing_stages', []);

        DB::table('tenants')->orderBy('id')->get()->each(function ($tenant) use ($marketingStages) {
            $workspaceId = DB::table('workspaces')
                ->where('tenant_id', $tenant->id)
                ->orderByDesc('is_default')
                ->value('id');

            if (! $workspaceId) {
                return;
            }

            $hasMarketing = DB::table('pipeline_stages')
                ->where('tenant_id', $tenant->id)
                ->where('type', 'marketing')
                ->exists();

            if ($hasMarketing) {
                return;
            }

            foreach ($marketingStages as $stage) {
                DB::table('pipeline_stages')->insert([
                    'tenant_id' => $tenant->id,
                    'workspace_id' => $workspaceId,
                    'name' => $stage['name'],
                    'sort_order' => $stage['sort_order'],
                    'color' => $stage['color'],
                    'type' => 'marketing',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_chat_messages');

        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_id');
            $table->dropConstrainedForeignId('marketing_stage_id');
            $table->dropColumn(['job_title', 'city', 'score']);
        });

        Schema::dropIfExists('campaigns');

        DB::table('pipeline_stages')->where('type', 'marketing')->delete();

        Schema::table('pipeline_stages', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
