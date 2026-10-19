<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Hydra: user profiles (picture, banner, biography) and online status.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar', 191)->nullable()->after('language');
            $table->string('banner', 191)->nullable()->after('avatar');
            $table->string('banner_style', 16)->default('gradient')->after('banner');
            $table->string('banner_color_1', 7)->nullable()->after('banner_style');
            $table->string('banner_color_2', 7)->nullable()->after('banner_color_1');
            $table->string('profile_accent', 7)->nullable()->after('banner_color_2');
            $table->text('bio')->nullable()->after('profile_accent');
            $table->boolean('show_online')->default(true)->after('bio');
            $table->timestamp('last_seen_at')->nullable()->after('show_online');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'avatar', 'banner', 'banner_style', 'banner_color_1', 'banner_color_2',
                'profile_accent', 'bio', 'show_online', 'last_seen_at',
            ]);
        });
    }
};
