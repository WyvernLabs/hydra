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
}
