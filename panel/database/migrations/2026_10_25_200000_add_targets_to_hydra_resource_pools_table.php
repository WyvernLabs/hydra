<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Hydra: where the servers of a resource pool may be created. Locations
 * (public nodes of them) and nodes (also non-public ones, e.g. nodes
 * dedicated to the user); both empty = the locations of the addon settings.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('hydra_resource_pools', function (Blueprint $table) {
            $table->json('locations')->nullable()->after('proxies');
            $table->json('nodes')->nullable()->after('locations');
        });
    }

    public function down(): void
    {
        Schema::table('hydra_resource_pools', function (Blueprint $table) {
            $table->dropColumn(['locations', 'nodes']);
        });
    }
};
