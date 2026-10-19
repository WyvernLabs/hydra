<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Hydra: background of the server pages of an egg ({"image", "blur",
     * "dim"}). NULL uses the dashboard background. Not part of egg exports.
     */
    public function up(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            $table->json('hydra_background')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            $table->dropColumn('hydra_background');
        });
    }
};
