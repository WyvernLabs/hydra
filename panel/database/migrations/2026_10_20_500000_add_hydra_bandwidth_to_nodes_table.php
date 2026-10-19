<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Hydra: speed of the node's network connection in Mbit/s, used to show
     * the network usage in the admin area.
     */
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->unsignedInteger('hydra_bandwidth')->default(1000);
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn('hydra_bandwidth');
        });
    }
};
