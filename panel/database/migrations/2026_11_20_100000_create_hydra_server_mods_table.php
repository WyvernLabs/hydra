<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Hydra: mods installed with the mod manager (addon "mods"), so the panel
 * knows where a jar came from and can offer updates.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('hydra_server_mods', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('server_id');
            $table->string('source', 16);
            $table->string('project_id', 64);
            $table->string('name', 191);
            $table->string('file', 191);
            $table->string('version_id', 191);
            $table->string('version_name', 191);
            $table->string('icon', 512)->nullable();
            $table->string('url', 512)->nullable();
            $table->string('loader', 16)->nullable();
            $table->string('minecraft', 32)->nullable();
            $table->boolean('dependency')->default(false);
            $table->unsignedInteger('installed_by')->nullable();
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->unique(['server_id', 'file']);
            $table->index(['server_id', 'source', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hydra_server_mods');
    }
};
