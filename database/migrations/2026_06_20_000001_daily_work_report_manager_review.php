<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_work_reports', function (Blueprint $table) {
            $table->unsignedTinyInteger('manager_score')->nullable()->after('submitted_at');
            $table->text('manager_feedback')->nullable()->after('manager_score');
            $table->foreignId('reviewed_by')->nullable()->after('manager_feedback')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('daily_work_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['manager_score', 'manager_feedback', 'reviewed_at']);
        });
    }
};
