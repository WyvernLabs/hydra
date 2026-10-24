<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Hydra: console messages that mark the server as running, set by the user
     * on the startup page. One per line, null uses those of the egg.
     */
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->text('hydra_startup_done')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('hydra_startup_done');
        });
    }
};
