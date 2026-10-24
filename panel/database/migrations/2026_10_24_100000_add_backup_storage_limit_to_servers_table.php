<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Hydra: backups are limited by the space they use (in MiB) instead of their
     * number. 0 means the server has no backup space. Servers that had backups
     * get the size of their disk (10 GiB for an unlimited disk). The old
     * backup_limit column stays for the API, but is no longer enforced.
     */
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->unsignedInteger('backup_storage_limit')->default(0)->after('backup_limit');
        });

        DB::table('servers')->where('backup_limit', '>', 0)->update([
            'backup_storage_limit' => DB::raw('CASE WHEN disk > 0 THEN disk ELSE 10240 END'),
        ]);
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('backup_storage_limit');
        });
    }
};
