<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Hydra: the expiry date is the time the admin entered. As a timestamp it would
 * move by an hour between summer and winter time, the connection uses a fixed offset.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('hydra_announcements', function (Blueprint $table) {
            $table->dateTime('expires_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('hydra_announcements', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->change();
        });
    }
};
