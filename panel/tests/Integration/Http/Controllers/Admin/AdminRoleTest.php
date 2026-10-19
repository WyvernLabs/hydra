<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Pterodactyl\Models\User;
use Pterodactyl\Models\AdminRole;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;

/**
 * Hydra: roles for the admin area.
 */
class AdminRoleTest extends HttpTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // The admin layout prints the render time, which public/index.php normally starts.
        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }
    }

    private function userWithRole(array $permissions): User
    {
        $user = User::factory()->create();
        $role = AdminRole::query()->create([
            'name' => 'Role ' . uniqid(),
            'color' => '#3b9eea',
            'permissions' => $permissions,
        ]);
        $user->adminRoles()->attach($role);

        return $user->refresh();
    }

    public function testUserWithoutRoleCannotOpenAdminArea(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function testRoleOnlyOpensItsAreas(): void
    {
        $user = $this->userWithRole(['servers.view']);

        $this->actingAs($user)->get('/admin')->assertOk();
        $this->actingAs($user)->get('/admin/servers')->assertOk();
        $this->actingAs($user)->get('/admin/settings')->assertForbidden();
        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->actingAs($user)->get('/admin/servers/new')->assertForbidden();
    }

    public function testRoleCannotGrantPermissionsItDoesNotHold(): void
    {
        $user = $this->userWithRole(['roles.create', 'servers.view']);

        $this->actingAs($user)->post('/admin/roles/new', [
            'name' => 'Too much',
            'color' => '#e5534b',
            'permissions' => ['settings.update'],
        ]);
        $this->assertFalse(AdminRole::query()->where('name', 'Too much')->exists());

        $this->actingAs($user)->post('/admin/roles/new', [
            'name' => 'Fine',
            'color' => '#e5534b',
            'permissions' => ['servers.view'],
        ])->assertRedirect();
        $this->assertTrue(AdminRole::query()->where('name', 'Fine')->exists());
    }

    public function testRoleCannotCreateRootAdminsOrTouchThem(): void
    {
        $user = $this->userWithRole(['users.create', 'users.update']);
        $root = User::factory()->admin()->create();

        $this->actingAs($user)->post('/admin/users/new', [
            'email' => 'sneaky@example.com',
            'username' => 'sneaky',
            'name_first' => 'Sneaky',
            'name_last' => 'User',
            'root_admin' => 1,
        ]);
        $this->assertFalse(User::query()->where('username', 'sneaky')->firstOrFail()->root_admin);

        $this->actingAs($user)->patch('/admin/users/view/' . $root->id, [
            'email' => $root->email,
            'username' => $root->username,
            'name_first' => 'Changed',
            'name_last' => $root->name_last,
        ]);
        $this->assertNotSame('Changed', $root->refresh()->name_first);
    }

    public function testRolesAreShownInTheUserList(): void
    {
        $admin = User::factory()->admin()->create();
        $member = $this->userWithRole(['servers.view']);

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee($member->adminRoles->first()->name);
    }

    public function testRoleCanBeAssignedByRootAdmin(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $role = AdminRole::query()->create(['name' => 'Support', 'color' => '#2fb67c', 'permissions' => ['users.view']]);

        $this->actingAs($admin)->patch('/admin/users/view/' . $user->id, [
            'email' => $user->email,
            'username' => $user->username,
            'name_first' => $user->name_first,
            'name_last' => $user->name_last,
            'roles_submitted' => 1,
            'roles' => [$role->id],
        ])->assertRedirect();

        $user->refresh();
        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->hasAdminPermission('users.view'));
        $this->assertFalse($user->hasAdminPermission('users.update'));
    }
}
