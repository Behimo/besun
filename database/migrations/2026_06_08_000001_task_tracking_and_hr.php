<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('tasks', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('assignee_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('tasks', 'assigned_by')) {
                $table->foreignId('assigned_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('tasks', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('due_at');
            }
        });

        if (! $this->indexExists('tasks', 'tasks_tenant_ws_assignee_status_due_idx')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->index(
                    ['tenant_id', 'workspace_id', 'assignee_id', 'status', 'due_at'],
                    'tasks_tenant_ws_assignee_status_due_idx',
                );
            });
        }

        DB::table('tasks')->whereNull('created_by')->orderBy('id')->chunkById(100, function ($tasks) {
            foreach ($tasks as $task) {
                $createdBy = $task->assignee_id;

                if (! $createdBy) {
                    $createdBy = DB::table('tenant_user')
                        ->where('tenant_id', $task->tenant_id)
                        ->value('user_id');
                }

                $completedAt = $task->status === 'completed' ? ($task->updated_at ?? now()) : null;

                DB::table('tasks')->where('id', $task->id)->update([
                    'created_by' => $createdBy,
                    'completed_at' => $completedAt,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if ($this->indexExists('tasks', 'tasks_tenant_ws_assignee_status_due_idx')) {
                $table->dropIndex('tasks_tenant_ws_assignee_status_due_idx');
            }

            if (Schema::hasColumn('tasks', 'assigned_by')) {
                $table->dropConstrainedForeignId('assigned_by');
            }
            if (Schema::hasColumn('tasks', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
            if (Schema::hasColumn('tasks', 'completed_at')) {
                $table->dropColumn('completed_at');
            }
        });
    }

    protected function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = $connection->select("PRAGMA index_list('{$table}')");

            return collect($indexes)->contains(fn ($row) => ($row->name ?? null) === $index);
        }

        $database = $connection->getDatabaseName();

        return (bool) $connection->selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $index],
        )?->c;
    }
};
