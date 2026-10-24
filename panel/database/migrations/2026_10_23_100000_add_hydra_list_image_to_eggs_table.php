<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Hydra: picture of the servers of an egg in the server list (Design > Eggs).
     */
    public function up(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            $table->string('hydra_list_image')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            $table->dropColumn('hydra_list_image');
        });
    }
};
