<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Hydra: roles for the admin area. Each role grants a set of admin permissions
     * and a user can hold any number of roles.
     */
    public function up(): void
    {
        Schema::create('admin_roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 64)->unique();
            $table->string('color', 7)->default('#74c236');
            $table->text('description')->nullable();
            $table->json('permissions');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('admin_role_user', function (Blueprint $table) {
            $table->unsignedInteger('admin_role_id');
            $table->unsignedInteger('user_id');

            $table->primary(['admin_role_id', 'user_id']);
            $table->foreign('admin_role_id')->references('id')->on('admin_roles')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_role_user');
        Schema::dropIfExists('admin_roles');
    }
};
