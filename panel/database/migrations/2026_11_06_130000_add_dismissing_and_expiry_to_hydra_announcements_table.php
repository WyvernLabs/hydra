<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Hydra: announcements can run out and may be closed by users, which hides them
 * for that user until "dismissed_until".
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('hydra_announcements', function (Blueprint $table) {
            $table->boolean('dismissible')->default(false)->after('nodes');
            $table->timestamp('expires_at')->nullable()->after('enabled');
        });

        Schema::create('hydra_announcement_dismissals', function (Blueprint $table) {
            $table->unsignedInteger('announcement_id');
            $table->unsignedInteger('user_id');
            $table->timestamp('dismissed_until');

            $table->primary(['announcement_id', 'user_id']);
            $table->foreign('announcement_id')->references('id')->on('hydra_announcements')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hydra_announcement_dismissals');

        Schema::table('hydra_announcements', function (Blueprint $table) {
            $table->dropColumn(['dismissible', 'expires_at']);
        });
    }
};
