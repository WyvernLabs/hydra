<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Hydra: warnings and bans of user accounts, ban templates, and when a
     * server was suspended (for the autodelete addon).
     */
    public function up(): void
    {
        Schema::create('ban_templates', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 191);
            $table->text('reason');
            // Null bans permanently.
            $table->unsignedInteger('duration_days')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('user_sanctions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('type', 16);
            $table->text('reason');
            $table->unsignedInteger('issued_by')->nullable();
            // Bans only: null is permanent.
            $table->timestamp('expires_at')->nullable();
            // Bans: servers suspended by the ban, unsuspended again when it ends.
            $table->json('suspended_servers')->nullable();
            // Bans: set when the ban ended (lifted by an admin or expired).
            $table->timestamp('lifted_at')->nullable();
            $table->unsignedInteger('lifted_by')->nullable();
            // Warnings: when the user confirmed having read it.
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('issued_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('lifted_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->timestamp('hydra_suspended_at')->nullable();
        });
        // Servers suspended before: count from now on.
        DB::table('servers')->where('status', 'suspended')->update(['hydra_suspended_at' => now()]);

        $locale = config('app.locale', 'en');
        foreach ((array) trans('admin/sanctions.default_templates', [], $locale) as $index => $template) {
            DB::table('ban_templates')->insert([
                'name' => $template['name'],
                'reason' => $template['reason'],
                'duration_days' => $template['days'],
                'sort' => $index,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('hydra_suspended_at');
        });
        Schema::dropIfExists('user_sanctions');
        Schema::dropIfExists('ban_templates');
    }
};
