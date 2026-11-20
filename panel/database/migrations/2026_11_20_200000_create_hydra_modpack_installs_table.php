<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Hydra: installations of modpacks (addon "modpacks") with their progress.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('hydra_modpack_installs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('server_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('source', 16);
            $table->string('project_id', 64);
            $table->string('version_id', 191);
            $table->string('name', 191);
            $table->string('version_name', 191)->nullable();
            $table->string('icon', 512)->nullable();
            $table->string('url', 512)->nullable();
            $table->string('mode', 16);
            $table->boolean('eula')->default(false);
            $table->string('status', 16)->default('queued');
            $table->string('step', 16)->nullable();
            $table->unsignedInteger('progress')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->index(['server_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hydra_modpack_installs');
    }
};
