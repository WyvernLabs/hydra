<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Hydra: status page (addon "status"). Own applications to check, and the
 * results per target ("node:1", "service:2") and hour.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('hydra_status_services', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 64);
            $table->string('type', 8);
            $table->string('target', 2048);
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('hydra_status_checks', function (Blueprint $table) {
            $table->string('target', 32);
            $table->dateTime('hour');
            $table->unsignedSmallInteger('checks')->default(0);
            $table->unsignedSmallInteger('failures')->default(0);
            $table->boolean('online')->default(false);

            $table->primary(['target', 'hour']);
            $table->index('hour');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hydra_status_checks');
        Schema::dropIfExists('hydra_status_services');
    }
};
