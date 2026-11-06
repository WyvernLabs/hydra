<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Hydra: announcements in the client area, written per language. Without nodes
 * they are general, with nodes they go to the users of servers on those nodes.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('hydra_announcements', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type', 16);
            $table->json('title');
            $table->json('description');
            $table->json('button_text')->nullable();
            $table->string('button_url', 2048)->nullable();
            $table->json('nodes')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hydra_announcements');
    }
};
