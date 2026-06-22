<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('tasks', 'work_started_at')) {
                $table->timestamp('work_started_at')->nullable()->after('completed_at');
            }
            if (! Schema::hasColumn('tasks', 'work_ended_at')) {
                $table->timestamp('work_ended_at')->nullable()->after('work_started_at');
            }
            if (! Schema::hasColumn('tasks', 'time_spent_minutes')) {
                $table->unsignedInteger('time_spent_minutes')->nullable()->after('work_ended_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'time_spent_minutes')) {
                $table->dropColumn('time_spent_minutes');
            }
            if (Schema::hasColumn('tasks', 'work_ended_at')) {
                $table->dropColumn('work_ended_at');
            }
            if (Schema::hasColumn('tasks', 'work_started_at')) {
                $table->dropColumn('work_started_at');
            }
        });
    }
};
