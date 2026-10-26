<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Hydra: web uploads up to 16 GB by default instead of 100 MB. Wings streams
 * uploads straight to disk, so the size does not matter for its memory. Nodes
 * still on the old default are raised as well; other values stay as set.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->unsignedInteger('upload_size')->default(16384)->change();
        });

        DB::table('nodes')->where('upload_size', 100)->update(['upload_size' => 16384]);
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->unsignedInteger('upload_size')->default(100)->change();
        });

        DB::table('nodes')->where('upload_size', 16384)->update(['upload_size' => 100]);
    }
};
