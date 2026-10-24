<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Hydra: players online on Minecraft Java servers, asked every few minutes
 * with the server list ping (addon "player_stats"). "online" is null while
 * the server did not answer.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('hydra_player_samples', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id');
            $table->unsignedSmallInteger('online')->nullable();
            $table->unsignedSmallInteger('max')->nullable();
            $table->timestamp('sampled_at');

            $table->index(['server_id', 'sampled_at']);
            $table->index('sampled_at');
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hydra_player_samples');
    }
};
