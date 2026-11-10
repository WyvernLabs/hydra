<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Hydra: what a server needs at least to switch to an egg in the game library
 * (CPU in percent like the server limit, memory in MiB). Null = no minimum.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            $table->unsignedInteger('hydra_min_cpu')->nullable();
            $table->unsignedInteger('hydra_min_memory')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            $table->dropColumn(['hydra_min_cpu', 'hydra_min_memory']);
        });
    }
};
