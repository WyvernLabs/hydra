<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Hydra: the Minecraft software installed with the version changer (addon
     * "versions"), as JSON with type, version and build.
     */
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->json('hydra_minecraft')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('hydra_minecraft');
        });
    }
};
