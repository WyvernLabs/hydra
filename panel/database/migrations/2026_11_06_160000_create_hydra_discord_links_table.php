<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Hydra: panel accounts connected to a Discord account (addon "discord"), with
 * the roles the panel last gave them.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('hydra_discord_links', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->unique();
            $table->string('discord_id', 20)->unique();
            $table->string('username', 64)->nullable();
            $table->boolean('notify_tickets')->default(true);
            $table->json('roles')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hydra_discord_links');
    }
};
