<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_work_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('report_date');
            $table->string('status', 20)->default('draft');
            $table->text('summary')->nullable();
            $table->unsignedInteger('total_minutes')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'report_date']);
        });

        Schema::create('daily_work_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_work_report_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('minutes')->default(0);
            $table->unsignedTinyInteger('effort_score')->default(3);
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_work_entries');
        Schema::dropIfExists('daily_work_reports');
    }
};
