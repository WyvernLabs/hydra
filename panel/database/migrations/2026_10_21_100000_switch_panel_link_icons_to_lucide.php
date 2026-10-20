<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Hydra: links in the sidebar use Lucide icons instead of Heroicons, and any
 * Lucide icon can be picked, so the names get longer.
 */
return new class () extends Migration {
    private const MAP = [
        'document-text' => 'file-text',
        'identification' => 'id-card',
        'information-circle' => 'info',
        'question-mark-circle' => 'circle-question-mark',
        'chat' => 'message-circle',
        'globe-alt' => 'globe',
    ];

    public function up(): void
    {
        Schema::table('panel_links', function (Blueprint $table) {
            $table->string('icon', 64)->default('file-text')->change();
        });

        foreach (self::MAP as $old => $new) {
            DB::table('panel_links')->where('icon', $old)->update(['icon' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::MAP as $old => $new) {
            DB::table('panel_links')->where('icon', $new)->update(['icon' => $old]);
        }

        Schema::table('panel_links', function (Blueprint $table) {
            $table->string('icon', 32)->default('document-text')->change();
        });
    }
};
