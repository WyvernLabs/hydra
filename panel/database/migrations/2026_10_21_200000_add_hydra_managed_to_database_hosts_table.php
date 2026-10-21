<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Hydra: database hosts set up on a node from the admin area. Their
     * firewall rules are kept in sync with the nodes of the panel.
     */
    public function up(): void
    {
        Schema::table('database_hosts', function (Blueprint $table) {
            $table->boolean('hydra_managed')->default(false)->after('node_id');
        });
    }

    public function down(): void
    {
        Schema::table('database_hosts', function (Blueprint $table) {
            $table->dropColumn('hydra_managed');
        });
    }
};
