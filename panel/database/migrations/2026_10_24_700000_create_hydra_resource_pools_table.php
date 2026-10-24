<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Hydra: resources a user may share out to own servers (addon "resource_pool").
 * CPU in percent of a core, memory, disk and backup space in MiB. Servers
 * created from the pool are marked so they count against it.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('hydra_resource_pools', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->unique();
            // Null: as many servers as the resources allow.
            $table->unsignedInteger('servers')->nullable();
            $table->unsignedInteger('cpu')->default(0);
            $table->unsignedInteger('memory')->default(0);
            $table->unsignedInteger('disk')->default(0);
            $table->unsignedInteger('databases')->default(0);
            $table->unsignedInteger('ports')->default(0);
            $table->unsignedInteger('backups')->default(0);
            $table->unsignedInteger('subdomains')->default(0);
            $table->unsignedInteger('proxies')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->boolean('hydra_pool')->default(false)->after('hydra_proxy_limit');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('hydra_pool');
        });

        Schema::dropIfExists('hydra_resource_pools');
    }
};
