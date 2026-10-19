<?php

namespace Pterodactyl\Tests\Unit\Models;

use Pterodactyl\Tests\TestCase;
use Pterodactyl\Models\AdminRole;

class AdminRoleTest extends TestCase
{
    public function testUnknownPermissionsAreDropped()
    {
        $this->assertSame([], AdminRole::normalizePermissions(['servers.fly', 'foo', '*']));
    }

    public function testViewPermissionIsAddedForEveryArea()
    {
        $permissions = AdminRole::normalizePermissions(['servers.build', 'users.delete']);

        sort($permissions);
        $this->assertSame(['servers.build', 'servers.view', 'users.delete', 'users.view'], $permissions);
    }

    public function testImpliedPermissionsAreAdded()
    {
        $permissions = AdminRole::normalizePermissions(['eggs.update', 'roles.assign']);

        sort($permissions);
        $this->assertSame(
            ['eggs.update', 'eggs.view', 'nests.view', 'roles.assign', 'roles.view', 'users.update', 'users.view'],
            $permissions
        );
    }

    public function testTextColorIsReadable()
    {
        $this->assertSame('#111827', (new AdminRole(['color' => '#e3b341']))->textColor());
        $this->assertSame('#ffffff', (new AdminRole(['color' => '#6c63ff']))->textColor());
    }
}
