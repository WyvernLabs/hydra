<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Auth;

use Pterodactyl\Models\User;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Services\Hydra\Addons\TurnstileAddon;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;

/**
 * Hydra: users log in with their username only, never with their email.
 */
class LoginControllerTest extends HttpTestCase
{
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

    public function testTurnstileProtectsLogin(): void
    {
        $this->enableTurnstile(['login' => true]);
        $user = User::factory()->create(['password' => password_hash('secret-password', PASSWORD_BCRYPT)]);

        Http::fake([TurnstileAddon::VERIFY_URL => Http::sequence()
            ->push(['success' => false])
            ->push(['success' => true]),
        ]);

        // Without a token Cloudflare is not even asked.
        $this->postJson('/auth/login', ['user' => $user->username, 'password' => 'secret-password'])
            ->assertStatus(400);

        $this->postJson('/auth/login', ['user' => $user->username, 'password' => 'secret-password', 'cf-turnstile-response' => 'bad'])
            ->assertStatus(400);

        $this->postJson('/auth/login', ['user' => $user->username, 'password' => 'secret-password', 'cf-turnstile-response' => 'good'])
            ->assertOk()
            ->assertJsonPath('data.complete', true);

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['secret'] === 'secret' && $request['response'] === 'good');
    }

    public function testTurnstileOnlyProtectsSelectedForms(): void
    {
        $this->enableTurnstile(['login' => false]);
        $user = User::factory()->create(['password' => password_hash('secret-password', PASSWORD_BCRYPT)]);
        Http::fake();

        $this->postJson('/auth/login', ['user' => $user->username, 'password' => 'secret-password'])->assertOk();

        Http::assertNothingSent();
    }

    private function enableTurnstile(array $forms): void
    {
        config()->set('hydra.addons.turnstile', array_merge([
            'enabled' => true,
            'site_key' => 'site',
            'secret_key' => 'secret',
            'login' => true,
            'password' => true,
            'register' => true,
        ], $forms));
    }
}
