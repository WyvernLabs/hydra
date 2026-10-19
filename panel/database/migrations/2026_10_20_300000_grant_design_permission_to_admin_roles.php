<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Hydra: colors, logo, backgrounds and links moved from the settings into
     * their own "Design" area. Roles that could use them before keep them.
     */
    public function up(): void
    {
        foreach (DB::table('admin_roles')->get() as $role) {
            $permissions = json_decode($role->permissions, true) ?: [];
            $added = [];

            if (in_array('settings.view', $permissions, true)) {
                $added[] = 'design.view';
            }
            if (in_array('settings.update', $permissions, true)) {
                $added[] = 'design.update';
            }

            if (!empty($added)) {
                DB::table('admin_roles')->where('id', $role->id)->update([
                    'permissions' => json_encode(array_values(array_unique(array_merge($permissions, $added)))),
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('admin_roles')->get() as $role) {
            $permissions = json_decode($role->permissions, true) ?: [];

            DB::table('admin_roles')->where('id', $role->id)->update([
                'permissions' => json_encode(array_values(array_diff($permissions, ['design.view', 'design.update']))),
            ]);
        }
    }
};
