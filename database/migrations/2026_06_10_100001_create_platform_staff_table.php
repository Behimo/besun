<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_staff')) {
            Schema::create('platform_staff', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->string('role', 30);
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('platform_staff')->nullOnDelete();
                $table->rememberToken();
                $table->timestamps();

                $table->index(['role', 'is_active']);
            });
        }

        if (Schema::hasTable('platform_audit_logs') && ! Schema::hasColumn('platform_audit_logs', 'platform_staff_id')) {
            $this->dropForeignIfExists('platform_audit_logs', 'user_id');

            Schema::table('platform_audit_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->change();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                $table->foreignId('platform_staff_id')->nullable()->after('user_id')->constrained('platform_staff')->nullOnDelete();
            });
        }

        if (Schema::hasTable('platform_support_tickets') && ! Schema::hasColumn('platform_support_tickets', 'creator_staff_id')) {
            $this->dropForeignIfExists('platform_support_tickets', 'created_by');
            $this->dropForeignIfExists('platform_support_tickets', 'assigned_to');

            $legacyColumns = array_filter([
                Schema::hasColumn('platform_support_tickets', 'created_by') ? 'created_by' : null,
                Schema::hasColumn('platform_support_tickets', 'assigned_to') ? 'assigned_to' : null,
            ]);

            Schema::table('platform_support_tickets', function (Blueprint $table) use ($legacyColumns) {
                if ($legacyColumns !== []) {
                    $table->dropColumn($legacyColumns);
                }
                $table->foreignId('creator_staff_id')->nullable()->constrained('platform_staff')->nullOnDelete();
                $table->foreignId('assignee_staff_id')->nullable()->constrained('platform_staff')->nullOnDelete();
            });
        }

        if (Schema::hasTable('platform_support_ticket_messages') && ! Schema::hasColumn('platform_support_ticket_messages', 'platform_staff_id')) {
            $this->dropForeignIfExists('platform_support_ticket_messages', 'user_id');

            Schema::table('platform_support_ticket_messages', function (Blueprint $table) {
                if (Schema::hasColumn('platform_support_ticket_messages', 'user_id')) {
                    $table->dropColumn('user_id');
                }
                $table->foreignId('platform_staff_id')->constrained('platform_staff')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('platform_support_ticket_messages', function (Blueprint $table) {
            $table->dropForeign(['platform_staff_id']);
            $table->dropColumn('platform_staff_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('platform_support_tickets', function (Blueprint $table) {
            $table->dropForeign(['creator_staff_id']);
            $table->dropForeign(['assignee_staff_id']);
            $table->dropColumn(['creator_staff_id', 'assignee_staff_id']);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('platform_audit_logs', function (Blueprint $table) {
            $table->dropForeign(['platform_staff_id']);
            $table->dropColumn('platform_staff_id');
        });

        Schema::dropIfExists('platform_staff');
    }

    private function dropForeignIfExists(string $table, string $column): void
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (in_array($column, $foreignKey['columns'], true)) {
                Schema::table($table, function (Blueprint $table) use ($foreignKey) {
                    $table->dropForeign(DB::getDriverName() === 'sqlite' ? $foreignKey['columns'] : $foreignKey['name']);
                });

                return;
            }
        }
    }
};
