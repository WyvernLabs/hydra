<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Hydra: banner image on the server dashboard of an egg (file name in
     * storage/app/hydra/backgrounds). Not part of egg exports.
     */
    public function up(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            $table->string('hydra_banner')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            $table->dropColumn('hydra_banner');
        });
    }
};
