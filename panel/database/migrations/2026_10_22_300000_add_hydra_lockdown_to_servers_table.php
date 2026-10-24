<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Hydra: lockdown of a server by its owner, null while there is none.
     */
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->timestamp('hydra_lockdown_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('hydra_lockdown_at');
        });
    }
};
