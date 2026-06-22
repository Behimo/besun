<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('reminder_at');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('next_follow_up_at')->nullable()->after('notes');
            $table->timestamp('follow_up_reminder_at')->nullable()->after('next_follow_up_at');
            $table->timestamp('follow_up_reminder_sent_at')->nullable()->after('follow_up_reminder_at');
        });

        Schema::table('deals', function (Blueprint $table) {
            $table->timestamp('next_follow_up_at')->nullable()->after('notes');
            $table->timestamp('follow_up_reminder_at')->nullable()->after('next_follow_up_at');
            $table->timestamp('follow_up_reminder_sent_at')->nullable()->after('follow_up_reminder_at');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable()->after('body');
            $table->timestamp('reminder_at')->nullable()->after('scheduled_at');
            $table->timestamp('reminder_sent_at')->nullable()->after('reminder_at');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn(['scheduled_at', 'reminder_at', 'reminder_sent_at']);
        });

        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn(['next_follow_up_at', 'follow_up_reminder_at', 'follow_up_reminder_sent_at']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['next_follow_up_at', 'follow_up_reminder_at', 'follow_up_reminder_sent_at']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });

        Schema::dropIfExists('notifications');
    }
};
