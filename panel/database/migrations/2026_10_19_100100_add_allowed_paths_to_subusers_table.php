<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Hydra: folders a subuser may work in. NULL means the whole server.
     */
    public function up(): void
    {
        Schema::table('subusers', function (Blueprint $table) {
            $table->json('allowed_paths')->nullable()->after('permissions');
        });
    }

    public function down(): void
    {
        Schema::table('subusers', function (Blueprint $table) {
            $table->dropColumn('allowed_paths');
        });
    }
};
