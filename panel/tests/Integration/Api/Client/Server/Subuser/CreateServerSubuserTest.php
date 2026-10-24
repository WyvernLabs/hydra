<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server\Subuser;

use Pterodactyl\Models\User;
use Illuminate\Http\Response;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Models\Permission;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

class CreateServerSubuserTest extends ClientApiIntegrationTestCase
{
    /**
     * Test that an existing user can be added as a subuser by their username.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('permissionsDataProvider')]
    public function testSubuserCanBeCreated(array $permissions)
    {
        [$user, $server] = $this->generateTestAccount($permissions);

        /** @var User $subuser */
        $subuser = User::factory()->create();

        $response = $this->actingAs($user)->postJson($this->link($server) . '/users', [
            'username' => strtoupper($subuser->username),
            'permissions' => [
                Permission::ACTION_USER_CREATE,
            ],
        ]);

        $response->assertOk();

        $response->assertJsonPath('object', Subuser::RESOURCE_NAME);
        $response->assertJsonPath('attributes.uuid', $subuser->uuid);
        $response->assertJsonPath('attributes.permissions', [
            Permission::ACTION_USER_CREATE,
            Permission::ACTION_WEBSOCKET_CONNECT,
        ]);
        $response->assertJsonPath('attributes.allowed_paths', []);
        // Hydra: other users never see the email address of a subuser.
        $response->assertJsonMissingPath('attributes.email');

        $expected = $response->json('attributes');
        unset($expected['permissions'], $expected['allowed_paths'], $expected['role_id']);

        $this->assertEquals(
            collect($this->getTransformedSubuserUser($subuser))->except('email')->all(),
            $expected
        );
    }

    /**
     * Hydra: an unknown username returns an error instead of creating a new account.
     */
    public function testUnknownUsernameReturnsErrorAndCreatesNoAccount()
    {
        [$user, $server] = $this->generateTestAccount();
        $count = User::query()->count();

        $response = $this->actingAs($user)->postJson($this->link($server) . '/users', [
            'username' => 'does-not-exist',
            'permissions' => [
                Permission::ACTION_USER_CREATE,
            ],
        ]);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonPath('errors.0.code', 'UserNotFoundException');
        $this->assertSame($count, User::query()->count());
        $this->assertSame(0, $server->subusers()->count());
    }

    /**
     * Tests that an error is returned if a subuser attempts to create a new subuser and assign
     * permissions that their account does not also possess.
     */
    public function testErrorIsReturnedIfAssigningPermissionsNotAssignedToSelf()
    {
        [$user, $server] = $this->generateTestAccount([
            Permission::ACTION_USER_CREATE,
            Permission::ACTION_USER_READ,
            Permission::ACTION_CONTROL_CONSOLE,
        ]);

        $response = $this->actingAs($user)->postJson($this->link($server) . '/users', [
            'username' => User::factory()->create()->username,
            'permissions' => [
                Permission::ACTION_USER_CREATE,
                Permission::ACTION_USER_UPDATE, // This permission is not assigned to the subuser.
            ],
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('errors.0.code', 'HttpForbiddenException');
        $response->assertJsonPath('errors.0.detail', 'Cannot assign permissions to a subuser that your account does not actively possess.');
    }

    /**
     * A username is required and limited to the column length.
     */
    public function testUsernameIsValidated()
    {
        [$user, $server] = $this->generateTestAccount();

        $this->actingAs($user)->postJson($this->link($server) . '/users', [
            'permissions' => [Permission::ACTION_USER_CREATE],
        ])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.0.meta.source_field', 'username');

        $this->actingAs($user)->postJson($this->link($server) . '/users', [
            'username' => str_repeat('a', 192),
            'permissions' => [Permission::ACTION_USER_CREATE],
        ])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.0.meta.source_field', 'username');
    }

    /**
     * Test that an error is returned if the account is already associated with the server instance.
     */
    public function testAddingSubuserThatAlreadyIsAssignedReturnsError()
    {
        [$user, $server] = $this->generateTestAccount();
        $existing = User::factory()->create();

        $this->actingAs($user)->postJson($this->link($server) . '/users', [
            'username' => $existing->username,
            'permissions' => [
                Permission::ACTION_USER_CREATE,
            ],
        ])->assertOk();

        $response = $this->actingAs($user)->postJson($this->link($server) . '/users', [
            'username' => $existing->username,
            'permissions' => [
                Permission::ACTION_USER_CREATE,
            ],
        ]);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonPath('errors.0.code', 'ServerSubuserExistsException');
        $response->assertJsonPath('errors.0.detail', 'This user is already assigned as a subuser for this server.');
    }

    public static function permissionsDataProvider(): array
    {
        return [[[]], [[Permission::ACTION_USER_CREATE]]];
    }

    private function getTransformedSubuserUser(User $user): array
    {
        return (new \Pterodactyl\Transformers\Api\Client\UserTransformer())->transform($user);
    }
}
