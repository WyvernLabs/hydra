<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Hydra: SFTP servers backups are stored on (addon "sftp_backups"). A target
     * belongs either to a node (set by an admin, used instead of the node's
     * disk) or to a server (the user's own SFTP server).
     */
    public function up(): void
    {
        Schema::create('sftp_backup_targets', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('node_id')->nullable()->unique();
            $table->unsignedInteger('server_id')->nullable()->unique();
            $table->boolean('enabled')->default(false);
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(22);
            $table->string('username');
            $table->text('password')->nullable();
            $table->text('private_key')->nullable();
            $table->string('path')->default('');
            $table->string('host_key')->nullable();
            $table->timestamps();

            $table->foreign('node_id')->references('id')->on('nodes')->cascadeOnDelete();
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
        });

        Schema::table('backups', function (Blueprint $table) {
            $table->unsignedInteger('hydra_sftp_target_id')->nullable()->after('disk');
            $table->string('hydra_sftp_file')->nullable()->after('hydra_sftp_target_id');
            $table->string('hydra_error', 191)->nullable()->after('hydra_sftp_file');

            $table->foreign('hydra_sftp_target_id')->references('id')->on('sftp_backup_targets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->dropForeign(['hydra_sftp_target_id']);
            $table->dropColumn(['hydra_sftp_target_id', 'hydra_sftp_file', 'hydra_error']);
        });

        Schema::dropIfExists('sftp_backup_targets');
    }
};
