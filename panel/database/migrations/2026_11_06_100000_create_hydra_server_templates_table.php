<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Hydra: named settings for new servers (addon "server_templates"). A template
 * goes with its egg; locations and nodes say where its servers may be deployed.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('hydra_server_templates', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 64)->unique();
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('egg_id');
            $table->string('image', 191);
            $table->text('startup')->nullable();
            $table->json('environment')->nullable();
            $table->unsignedInteger('cpu')->default(0);
            $table->string('threads')->nullable();
            $table->unsignedInteger('memory')->default(0);
            $table->integer('swap')->default(0);
            $table->unsignedInteger('disk')->default(0);
            $table->unsignedInteger('io')->default(500);
            $table->boolean('oom_disabled')->default(true);
            $table->unsignedInteger('database_limit')->default(0);
            $table->unsignedInteger('allocation_limit')->default(0);
            $table->unsignedInteger('backup_storage_limit')->default(0);
            $table->json('locations')->nullable();
            $table->json('nodes')->nullable();
            $table->boolean('start_on_completion')->default(false);
            $table->boolean('skip_scripts')->default(false);
            $table->timestamps();

            $table->foreign('egg_id')->references('id')->on('eggs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hydra_server_templates');
    }
};
