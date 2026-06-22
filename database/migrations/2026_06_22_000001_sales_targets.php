<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 20); // department | user
            $table->string('department', 30)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('jyear');
            $table->unsignedTinyInteger('jmonth');
            $table->decimal('revenue_target', 16, 2)->default(0);
            $table->unsignedInteger('deals_target')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'scope', 'jyear', 'jmonth']);
            $table->index(['tenant_id', 'user_id', 'jyear', 'jmonth']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_targets');
    }
};
