<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Hydra: knowledgebase addon. Categories with one level of subcategories
 * (parent_id), posts in a category or subcategory, and uploaded pictures and
 * videos. Names, titles and texts are stored per language (JSON).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('hydra_kb_categories', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('parent_id')->nullable();
            $table->json('names');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('hydra_kb_categories')->cascadeOnDelete();
        });

        Schema::create('hydra_kb_posts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('category_id');
            $table->string('slug', 120)->unique();
            $table->json('titles');
            $table->json('contents');
            $table->boolean('published')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->unsignedInteger('author_id')->nullable();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('hydra_kb_categories')->cascadeOnDelete();
            $table->foreign('author_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('hydra_kb_media', function (Blueprint $table) {
            $table->increments('id');
            $table->char('uuid', 36)->unique();
            $table->string('name', 191);
            $table->string('mime', 64);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hydra_kb_media');
        Schema::dropIfExists('hydra_kb_posts');
        Schema::dropIfExists('hydra_kb_categories');
    }
};
