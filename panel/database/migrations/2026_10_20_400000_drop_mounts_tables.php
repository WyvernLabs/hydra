<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Hydra: mounts were removed from the panel.
     */
    public function up(): void
    {
        Schema::dropIfExists('mount_server');
        Schema::dropIfExists('mount_node');
        Schema::dropIfExists('egg_mount');
        Schema::dropIfExists('mounts');

        foreach (DB::table('admin_roles')->get() as $role) {
            $permissions = json_decode($role->permissions, true) ?: [];
            $kept = array_values(array_filter($permissions, fn ($p) => $p !== 'servers.mounts' && !str_starts_with($p, 'mounts.')));

            if (count($kept) !== count($permissions)) {
                DB::table('admin_roles')->where('id', $role->id)->update(['permissions' => json_encode($kept)]);
            }
        }
    }

    /**
     * Creates the (empty) tables again, as they were in Pterodactyl.
     */
    public function down(): void
    {
        Schema::create('mounts', function (Blueprint $table) {
            $table->increments('id')->unique();
            $table->char('uuid', 36)->unique();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('source');
            $table->string('target');
            $table->tinyInteger('read_only')->unsigned();
            $table->tinyInteger('user_mountable')->unsigned();
        });

        Schema::create('egg_mount', function (Blueprint $table) {
            $table->integer('egg_id');
            $table->integer('mount_id');
            $table->unique(['egg_id', 'mount_id']);
        });

        Schema::create('mount_node', function (Blueprint $table) {
            $table->integer('node_id');
            $table->integer('mount_id');
            $table->unique(['node_id', 'mount_id']);
        });

        Schema::create('mount_server', function (Blueprint $table) {
            $table->integer('server_id');
            $table->integer('mount_id');
            $table->unique(['server_id', 'mount_id']);
        });
    }
};
