<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Hydra: roles of a server, sets of permissions that are given to subusers.
 * The permissions of a subuser with a role are copied from it whenever the
 * role changes, so every check keeps reading subusers.permissions.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('hydra_server_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id');
            $table->string('name', 60);
            $table->string('color', 7)->default('#74c236');
            $table->json('permissions');
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
        });

        Schema::table('subusers', function (Blueprint $table) {
            $table->unsignedBigInteger('hydra_role_id')->nullable()->after('allowed_paths');
            $table->foreign('hydra_role_id')->references('id')->on('hydra_server_roles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subusers', function (Blueprint $table) {
            $table->dropForeign(['hydra_role_id']);
            $table->dropColumn('hydra_role_id');
        });

        Schema::dropIfExists('hydra_server_roles');
    }
};
