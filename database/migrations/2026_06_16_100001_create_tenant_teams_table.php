<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 64);
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });

        $now = now();
        $systemTeams = [
            ['slug' => 'sales', 'name' => 'فروش', 'sort_order' => 1],
            ['slug' => 'marketing', 'name' => 'بازاریابی', 'sort_order' => 2],
            ['slug' => 'finance', 'name' => 'مالی', 'sort_order' => 3],
        ];

        $tenantIds = DB::table('tenants')->pluck('id');

        foreach ($tenantIds as $tenantId) {
            foreach ($systemTeams as $team) {
                DB::table('tenant_teams')->insert([
                    'tenant_id' => $tenantId,
                    'name' => $team['name'],
                    'slug' => $team['slug'],
                    'is_system' => true,
                    'sort_order' => $team['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_teams');
    }
};
