<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Hydra: roles can limit their members to certain folders, like subusers.
 * Null means the whole server.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('hydra_server_roles', function (Blueprint $table) {
            $table->json('allowed_paths')->nullable()->after('permissions');
        });
    }

    public function down(): void
    {
        Schema::table('hydra_server_roles', function (Blueprint $table) {
            $table->dropColumn('allowed_paths');
        });
    }
};
