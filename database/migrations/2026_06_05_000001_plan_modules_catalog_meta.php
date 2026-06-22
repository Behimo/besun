<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_modules', function (Blueprint $table) {
            $table->string('category', 32)->nullable()->after('slug');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('category');
            $table->string('nav_route')->nullable()->after('sort_order');
            $table->string('icon', 64)->nullable()->after('nav_route');
        });

        $slugMap = config('crm_modules.slug_migrations', []);

        foreach ($slugMap as $old => $new) {
            DB::table('plan_modules')->where('slug', $old)->update(['slug' => $new]);
        }
    }

    public function down(): void
    {
        $slugMap = array_flip(config('crm_modules.slug_migrations', []));

        foreach ($slugMap as $old => $new) {
            DB::table('plan_modules')->where('slug', $old)->update(['slug' => $new]);
        }

        Schema::table('plan_modules', function (Blueprint $table) {
            $table->dropColumn(['category', 'sort_order', 'nav_route', 'icon']);
        });
    }
};
