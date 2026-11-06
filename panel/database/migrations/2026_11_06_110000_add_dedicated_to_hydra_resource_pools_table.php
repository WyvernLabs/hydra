<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Hydra: pool servers on the own nodes of the pool pick their ports freely.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('hydra_resource_pools', function (Blueprint $table) {
            $table->boolean('dedicated')->default(false)->after('nodes');
        });
    }

    public function down(): void
    {
        Schema::table('hydra_resource_pools', function (Blueprint $table) {
            $table->dropColumn('dedicated');
        });
    }
};
