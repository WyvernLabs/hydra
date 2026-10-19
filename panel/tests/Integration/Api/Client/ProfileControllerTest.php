<?php

namespace Pterodactyl\Tests\Integration\Api\Client;

use Pterodactyl\Models\User;
use Illuminate\Http\UploadedFile;
use Pterodactyl\Services\Users\ProfileMediaService;

/**
 * Hydra: profiles of users and deleting the own account.
 */
class ProfileControllerTest extends ClientApiIntegrationTestCase
{
    // 1×1 transparent PNG and GIF.
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    private function profile(User $user, array $data = []): array
    {
        return array_merge([
            'username' => $user->username,
            'name_first' => $user->name_first,
            'name_last' => $user->name_last,
            'banner_style' => 'gradient',
            'show_online' => true,
        ], $data);
    }

    public function testProfileCanBeUpdated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/api/client/account/profile', $this->profile($user, [
            'username' => 'NewName',
            'name_first' => 'Max',
            'name_last' => 'Muster',
            'bio' => "Builder\nund Admin",
            'profile_accent' => '#FF9500',
            'banner_color_1' => '#1f1f1f',
            'banner_color_2' => '#7e3a02',
            'show_online' => false,
        ]), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('username', 'newname')
            ->assertJsonPath('profile_accent', '#ff9500')
            ->assertJsonPath('show_online', false);

        $user->refresh();
        $this->assertSame('newname', $user->username);
        $this->assertSame('Max', $user->name_first);
        $this->assertSame("Builder\nund Admin", $user->bio);
        $this->assertFalse($user->show_online);
        $this->assertActivityLogged('user:account.username-changed');
    }

    public function testUsernameMustBeUnique(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->post('/api/client/account/profile', $this->profile($user, ['username' => $other->username]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.meta.source_field', 'username');
    }

    public function testPictureAndBannerCanBeUploadedAndRemoved(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/api/client/account/profile', $this->profile($user, [
            'avatar' => UploadedFile::fake()->createWithContent('me.png', base64_decode(self::PNG)),
            'banner' => UploadedFile::fake()->createWithContent('banner.gif', base64_decode(self::GIF)),
        ]), ['Accept' => 'application/json'])->assertOk();

        $user->refresh();
        $this->assertStringEndsWith('.png', $user->avatar);
        $this->assertStringEndsWith('.gif', $user->banner);
        $this->assertNotNull(ProfileMediaService::path($user, 'banner'));

        // The animated or transparent file is served exactly as uploaded.
        $response = $this->actingAs($user, 'web')->get(parse_url($user->bannerUrl(), PHP_URL_PATH))->assertOk();
        $this->assertSame('image/gif', $response->headers->get('Content-Type'));
        $this->assertSame(base64_decode(self::GIF), file_get_contents($response->getFile()->getPathname()));

        $path = ProfileMediaService::path($user, 'avatar');
        $this->actingAs($user)->post('/api/client/account/profile', $this->profile($user, ['remove_avatar' => true]), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('avatar_url', null);

        $this->assertNull($user->refresh()->avatar);
        $this->assertFileDoesNotExist($path);

        ProfileMediaService::removeFiles($user);
    }

    public function testOnlyImagesWithinTheLimitCanBeUploaded(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/api/client/account/profile', $this->profile($user, [
            'avatar' => UploadedFile::fake()->createWithContent('evil.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        ]), ['Accept' => 'application/json'])->assertStatus(422);

        // A PNG name does not help if the content is something else.
        $this->actingAs($user)->post('/api/client/account/profile', $this->profile($user, [
            'avatar' => UploadedFile::fake()->createWithContent('fake.png', 'not an image'),
        ]), ['Accept' => 'application/json'])->assertStatus(422);

        config()->set('hydra.profiles.upload_limit', 1);
        $this->actingAs($user)->post('/api/client/account/profile', $this->profile($user, [
            'avatar' => UploadedFile::fake()->create('big.png', 2048, 'image/png'),
        ]), ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertNull($user->refresh()->avatar);
    }

    public function testAccountCanBeDeletedWithConfirmation(): void
    {
        $user = User::factory()->create(['password' => password_hash('secret-password', PASSWORD_BCRYPT)]);

        $this->actingAs($user)->deleteJson('/api/client/account', ['password' => 'secret-password', 'confirm' => 'nope'])
            ->assertStatus(422);
        $this->actingAs($user)->deleteJson('/api/client/account', ['password' => 'wrong', 'confirm' => 'DELETE'])
            ->assertStatus(400);
        $this->assertModelExists($user);

        $this->actingAs($user)->deleteJson('/api/client/account', ['password' => 'secret-password', 'confirm' => 'löschen'])
            ->assertNoContent();
        $this->assertModelMissing($user);
    }

    public function testAccountWithServersCannotBeDeleted(): void
    {
        [$user] = $this->generateTestAccount();
        $user->update(['password' => password_hash('secret-password', PASSWORD_BCRYPT)]);

        $this->actingAs($user)->deleteJson('/api/client/account', ['password' => 'secret-password', 'confirm' => 'DELETE'])
            ->assertStatus(400);
        $this->assertModelExists($user);
    }

    public function testLastSeenIsTracked(): void
    {
        $user = User::factory()->create();
        $this->assertFalse($user->isOnline());

        $this->actingAs($user)->getJson('/api/client/account')->assertOk();

        $this->assertNotNull($user->refresh()->last_seen_at);
        $this->assertTrue($user->isOnline());
    }
}
