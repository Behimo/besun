<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_chat_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('tenant_chat_group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_chat_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at')->useCurrent();
            $table->unique(['tenant_chat_group_id', 'user_id']);
        });

        Schema::table('tenant_chat_messages', function (Blueprint $table) {
            $table->foreignId('group_id')
                ->nullable()
                ->after('recipient_id')
                ->constrained('tenant_chat_groups')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_chat_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_id');
        });

        Schema::dropIfExists('tenant_chat_group_user');
        Schema::dropIfExists('tenant_chat_groups');
    }
};
