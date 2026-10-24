<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Hydra: language a ticket is written in (support languages of the tickets addon).
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('language', 8)->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('language');
        });
    }
};
