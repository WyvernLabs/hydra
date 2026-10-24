<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Hydra: game profiles (addon "games"). The files of a game a server
     * switched away from, kept by the node outside of the server directory,
     * with what is needed to switch back: egg, startup, image and variables.
     */
    public function up(): void
    {
        Schema::create('hydra_game_profiles', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('server_id');
            // Name of the folder on the node.
            $table->string('uuid', 64)->unique();
            $table->string('name', 191);
            $table->unsignedInteger('egg_id')->nullable();
            $table->text('startup');
            $table->string('image', 191);
            $table->json('variables');
            $table->text('startup_done')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->foreign('egg_id')->references('id')->on('eggs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hydra_game_profiles');
    }
};
