<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Hydra: storage of config files of a server (addon "config_sync"). Users put
 * a config there before a plugin update and sync its values into the newly
 * created config afterwards.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('hydra_config_stash', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('server_id');
            $table->string('name', 64);
            $table->string('path', 512);
            $table->mediumText('content');
            $table->unsignedInteger('size');
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hydra_config_stash');
    }
};
