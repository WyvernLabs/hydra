<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Hydra: servers with a limited runtime. hydra_expires_at = when the server
 * runs out (null = unlimited), hydra_expired_at = when it was suspended for
 * that, so it is suspended only once and unsuspended again when extended.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->timestamp('hydra_expires_at')->nullable()->index();
            $table->timestamp('hydra_expired_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropIndex(['hydra_expires_at']);
            $table->dropColumn(['hydra_expires_at', 'hydra_expired_at']);
        });
    }
};
