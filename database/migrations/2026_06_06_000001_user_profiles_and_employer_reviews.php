<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_user', function (Blueprint $table) {
            $table->timestamp('left_at')->nullable()->after('invited_by');
        });

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('job_title')->nullable();
            $table->string('city')->nullable();
            $table->text('bio')->nullable();
            $table->json('skills')->nullable();
            $table->boolean('visible_to_owners')->default(true);
            $table->timestamps();
        });

        Schema::create('employer_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('role_at_review', 32)->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employer_reviews');
        Schema::dropIfExists('user_profiles');

        Schema::table('tenant_user', function (Blueprint $table) {
            $table->dropColumn('left_at');
        });
    }
};
