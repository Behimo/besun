<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('cms_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('cms_categories')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('excerpt', 500)->nullable();
            $table->longText('body')->nullable();
            $table->string('featured_image')->nullable();
            $table->string('author')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->string('og_image')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();
        });

        Schema::create('cms_media', function (Blueprint $table) {
            $table->id();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('filename');
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->string('alt')->nullable();
            $table->timestamps();
        });

        Schema::table('cms_pages', function (Blueprint $table) {
            $table->string('template')->default('system')->after('title');
            $table->boolean('show_in_nav')->default(false)->after('is_published');
            $table->boolean('is_system')->default(true)->after('show_in_nav');
        });
    }

    public function down(): void
    {
        Schema::table('cms_pages', function (Blueprint $table) {
            $table->dropColumn(['template', 'show_in_nav', 'is_system']);
        });

        Schema::dropIfExists('cms_media');
        Schema::dropIfExists('cms_posts');
        Schema::dropIfExists('cms_categories');
    }
};
