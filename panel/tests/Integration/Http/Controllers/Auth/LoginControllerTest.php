<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Auth;

use Pterodactyl\Models\User;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;

/**
 * Hydra: users log in with their username only, never with their email.
 */
class LoginControllerTest extends HttpTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        config()->set('recaptcha.enabled', false);
    }

    public function testUserCanLogInWithUsername(): void
    {
        $user = User::factory()->create(['password' => password_hash('secret-password', PASSWORD_BCRYPT)]);

        $this->postJson('/auth/login', ['user' => $user->username, 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonPath('data.complete', true);
    }

    public function testUserCannotLogInWithEmail(): void
    {
        $user = User::factory()->create(['password' => password_hash('secret-password', PASSWORD_BCRYPT)]);

        $this->postJson('/auth/login', ['user' => $user->email, 'password' => 'secret-password'])
            ->assertStatus(400);
    }
}
