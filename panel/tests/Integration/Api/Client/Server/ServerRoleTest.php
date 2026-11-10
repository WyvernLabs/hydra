<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Models\Permission;
use Illuminate\Support\Facades\Bus;
use Pterodactyl\Jobs\RevokeSftpAccessJob;
use Pterodactyl\Models\HydraServerRole;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

/**
 * Hydra: roles of a server, named sets of permissions for subusers.
 */
class ServerRoleTest extends ClientApiIntegrationTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Bus::fake([RevokeSftpAccessJob::class]);
    }

    public function testRoleGivesItsPermissionsAndKeepsThemInSync(): void
    {
        [$owner, $server] = $this->generateTestAccount();
        $member = User::factory()->create();

        $role = $this->actingAs($owner)->postJson("/api/client/servers/$server->uuid/roles", [
            'name' => 'Moderator',
            'color' => '#3B82F6',
            'permissions' => [Permission::ACTION_CONTROL_CONSOLE, 'not.a.permission'],
        ])->assertOk()->json();
        $this->assertSame([Permission::ACTION_CONTROL_CONSOLE, Permission::ACTION_WEBSOCKET_CONNECT], $role['permissions']);
        $this->assertSame('#3b82f6', $role['color']);

        // The permissions sent with the role do not matter, the role's count.
        $this->actingAs($owner)->postJson("/api/client/servers/$server->uuid/users", [
            'username' => $member->username,
            'permissions' => [Permission::ACTION_FILE_READ],
            'role' => $role['id'],
        ])->assertOk()->assertJsonPath('attributes.role_id', $role['id']);

        /** @var Subuser $subuser */
        $subuser = Subuser::query()->where('server_id', $server->id)->where('user_id', $member->id)->firstOrFail();
        $this->assertEqualsCanonicalizing([Permission::ACTION_CONTROL_CONSOLE, Permission::ACTION_WEBSOCKET_CONNECT], $subuser->permissions);

        // Changing the role changes its members.
        $this->actingAs($owner)->patchJson("/api/client/servers/$server->uuid/roles/{$role['id']}", [
            'name' => 'Moderator',
            'color' => '#3b82f6',
            'permissions' => [Permission::ACTION_CONTROL_CONSOLE, Permission::ACTION_FILE_READ],
        ])->assertOk()->assertJsonPath('members', 1);
        $this->assertContains(Permission::ACTION_FILE_READ, $subuser->refresh()->permissions);
        Bus::assertDispatched(RevokeSftpAccessJob::class);

        // Without the role the member keeps the permissions.
        $this->actingAs($owner)->deleteJson("/api/client/servers/$server->uuid/roles/{$role['id']}")->assertNoContent();
        $subuser->refresh();
        $this->assertNull($subuser->hydra_role_id);
        $this->assertContains(Permission::ACTION_FILE_READ, $subuser->permissions);
    }

    public function testRolesCannotGrantMoreThanTheUserHas(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_USER_CREATE, Permission::ACTION_USER_READ, Permission::ACTION_CONTROL_CONSOLE]);

        $this->actingAs($user)->getJson("/api/client/servers/$server->uuid/roles")->assertOk();

        // Own permissions are fine …
        $this->actingAs($user)->postJson("/api/client/servers/$server->uuid/roles", [
            'name' => 'Console', 'color' => '#74c236', 'permissions' => [Permission::ACTION_CONTROL_CONSOLE],
        ])->assertOk();
        // … others are not.
        $this->actingAs($user)->postJson("/api/client/servers/$server->uuid/roles", [
            'name' => 'Files', 'color' => '#74c236', 'permissions' => [Permission::ACTION_FILE_DELETE],
        ])->assertForbidden();

        // Nor can a role of the owner with more permissions be handed out.
        $strong = HydraServerRole::query()->create([
            'server_id' => $server->id, 'name' => 'Admin', 'color' => '#ef4444',
            'permissions' => [Permission::ACTION_FILE_DELETE, Permission::ACTION_WEBSOCKET_CONNECT],
        ]);
        $other = User::factory()->create();
        $this->actingAs($user)->postJson("/api/client/servers/$server->uuid/users", [
            'username' => $other->username,
            'permissions' => [Permission::ACTION_CONTROL_CONSOLE],
            'role' => $strong->id,
        ])->assertForbidden();

        // Roles of a server the user has no access to are out of reach.
        [$owner, $otherServer] = $this->generateTestAccount();
        $foreign = HydraServerRole::query()->create([
            'server_id' => $otherServer->id, 'name' => 'Foreign', 'color' => '#ef4444', 'permissions' => [Permission::ACTION_WEBSOCKET_CONNECT],
        ]);
        $this->actingAs($owner)->patchJson("/api/client/servers/$server->uuid/roles/$foreign->id", [
            'name' => 'x', 'color' => '#ef4444', 'permissions' => [],
        ])->assertNotFound();
    }

    public function testRoleGivesItsFoldersToTheMembers(): void
    {
        [$owner, $server] = $this->generateTestAccount();
        $member = User::factory()->create();

        $role = $this->actingAs($owner)->postJson("/api/client/servers/$server->uuid/roles", [
            'name' => 'Builder',
            'color' => '#74c236',
            'permissions' => [Permission::ACTION_FILE_READ],
            'allowed_paths' => ['plugins/WorldEdit/', '/world/build', '/plugins/WorldEdit'],
        ])->assertOk()->json();
        $this->assertSame(['/plugins/WorldEdit', '/world/build'], $role['allowed_paths']);

        // The folders sent with the subuser do not matter, the role's count.
        $this->actingAs($owner)->postJson("/api/client/servers/$server->uuid/users", [
            'username' => $member->username,
            'permissions' => [Permission::ACTION_FILE_READ],
            'allowed_paths' => ['/'],
            'role' => $role['id'],
        ])->assertOk();

        /** @var Subuser $subuser */
        $subuser = Subuser::query()->where('server_id', $server->id)->where('user_id', $member->id)->firstOrFail();
        $this->assertSame(['/plugins/WorldEdit', '/world/build'], $subuser->allowed_paths);

        // Changing the folders of the role changes the members.
        $this->actingAs($owner)->patchJson("/api/client/servers/$server->uuid/roles/{$role['id']}", [
            'name' => 'Builder',
            'color' => '#74c236',
            'permissions' => [Permission::ACTION_FILE_READ],
            'allowed_paths' => [],
        ])->assertOk()->assertJsonPath('allowed_paths', []);
        $this->assertNull($subuser->refresh()->allowed_paths);
    }

    public function testRolesCannotGrantFoldersOutsideTheOwnOnes(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_USER_CREATE, Permission::ACTION_USER_READ, Permission::ACTION_FILE_READ]);
        Subuser::query()->where('server_id', $server->id)->where('user_id', $user->id)->update(['allowed_paths' => json_encode(['/plugins'])]);

        $this->actingAs($user)->postJson("/api/client/servers/$server->uuid/roles", [
            'name' => 'Inside', 'color' => '#74c236', 'permissions' => [Permission::ACTION_FILE_READ], 'allowed_paths' => ['/plugins/Essentials'],
        ])->assertOk();
        $this->actingAs($user)->postJson("/api/client/servers/$server->uuid/roles", [
            'name' => 'Outside', 'color' => '#74c236', 'permissions' => [Permission::ACTION_FILE_READ], 'allowed_paths' => ['/world'],
        ])->assertForbidden();
        // The whole server is more than the user has.
        $this->actingAs($user)->postJson("/api/client/servers/$server->uuid/roles", [
            'name' => 'Everything', 'color' => '#74c236', 'permissions' => [Permission::ACTION_FILE_READ], 'allowed_paths' => [],
        ])->assertForbidden();
    }

    public function testRoleCanBeCopiedToOtherServers(): void
    {
        [$owner, $server] = $this->generateTestAccount();
        $second = $this->createServerModel(['owner_id' => $owner->id]);
        $third = $this->createServerModel(['owner_id' => $owner->id]);
        // A server that belongs to another user.
        [, $foreign] = $this->generateTestAccount();

        $role = HydraServerRole::query()->create([
            'server_id' => $server->id, 'name' => 'Moderator', 'color' => '#3b82f6',
            'permissions' => [Permission::ACTION_CONTROL_CONSOLE, Permission::ACTION_WEBSOCKET_CONNECT],
            'allowed_paths' => ['/plugins'],
        ]);

        // A role of the same name on the third server is replaced, with its members.
        $old = HydraServerRole::query()->create([
            'server_id' => $third->id, 'name' => 'Moderator', 'color' => '#ef4444', 'permissions' => [Permission::ACTION_WEBSOCKET_CONNECT],
        ]);
        $member = User::factory()->create();
        $subuser = Subuser::query()->create([
            'user_id' => $member->id, 'server_id' => $third->id, 'permissions' => [Permission::ACTION_WEBSOCKET_CONNECT], 'hydra_role_id' => $old->id,
        ]);

        $targets = $this->actingAs($owner)->getJson("/api/client/servers/$server->uuid/roles/targets")->assertOk()->json('data');
        $this->assertEqualsCanonicalizing([$second->uuid, $third->uuid], array_column($targets, 'uuid'));

        $results = $this->actingAs($owner)->postJson("/api/client/servers/$server->uuid/roles/$role->id/copy", [
            'servers' => [$second->uuid, $third->uuid, $foreign->uuid],
        ])->assertOk()->json('data');

        $this->assertSame(['created', 'updated', 'forbidden'], array_column($results, 'status'));
        // Nothing about the foreign server is revealed.
        $this->assertNull($results[2]['name']);

        $copy = HydraServerRole::query()->where('server_id', $second->id)->firstOrFail();
        $this->assertSame('#3b82f6', $copy->color);
        $this->assertSame(['/plugins'], $copy->allowed_paths);
        $this->assertEqualsCanonicalizing($role->permissions, $copy->permissions);

        $this->assertSame(1, HydraServerRole::query()->where('server_id', $third->id)->count());
        $this->assertContains(Permission::ACTION_CONTROL_CONSOLE, $old->refresh()->permissions);
        $this->assertContains(Permission::ACTION_CONTROL_CONSOLE, $subuser->refresh()->permissions);
        $this->assertSame(['/plugins'], $subuser->allowed_paths);

        $this->assertSame(0, HydraServerRole::query()->where('server_id', $foreign->id)->count());
    }

    public function testCopyIsSkippedWhereTheUserLacksTheRights(): void
    {
        $rights = [Permission::ACTION_USER_CREATE, Permission::ACTION_USER_UPDATE, Permission::ACTION_USER_READ];
        [$user, $server] = $this->generateTestAccount(array_merge($rights, [Permission::ACTION_CONTROL_CONSOLE]));
        $role = HydraServerRole::query()->create([
            'server_id' => $server->id, 'name' => 'Console', 'color' => '#74c236',
            'permissions' => [Permission::ACTION_CONTROL_CONSOLE, Permission::ACTION_WEBSOCKET_CONNECT],
        ]);

        // On the other server the user may manage users, but has no console.
        $other = $this->createServerModel();
        Subuser::query()->create([
            'user_id' => $user->id, 'server_id' => $other->id,
            'permissions' => array_merge($rights, [Permission::ACTION_WEBSOCKET_CONNECT]),
        ]);

        $this->actingAs($user)->postJson("/api/client/servers/$server->uuid/roles/$role->id/copy", ['servers' => [$other->uuid]])
            ->assertOk()
            ->assertJsonPath('data.0.status', 'permissions');
        $this->assertSame(0, HydraServerRole::query()->where('server_id', $other->id)->count());
    }
}
