<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Hydra: schedules can also start when the server reaches a state or when a
     * line in its console matches. "cron" keeps the time based schedule.
     */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('hydra_trigger', 16)->default('cron')->after('cron_minute');
            $table->string('hydra_trigger_value')->nullable()->after('hydra_trigger');
            $table->boolean('hydra_trigger_regex')->default(false)->after('hydra_trigger_value');
            $table->unsignedInteger('hydra_trigger_cooldown')->default(60)->after('hydra_trigger_regex');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn(['hydra_trigger', 'hydra_trigger_value', 'hydra_trigger_regex', 'hydra_trigger_cooldown']);
        });
    }
};
