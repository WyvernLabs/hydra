<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Auth;

use Illuminate\Support\Str;
use Pterodactyl\Models\User;
use Illuminate\Support\Facades\Notification;
use Pterodactyl\Notifications\RegistrationCode;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;
use Illuminate\Notifications\AnonymousNotifiable;

/**
 * Hydra: visitors create an account themselves (Design > Addons > Registration).
 */
class RegisterControllerTest extends HttpTestCase
{
    // Data is not rolled back between tests, so every test uses its own address and username.
    private string $email;

    private string $username;

    public function setUp(): void
    {
        parent::setUp();

        $this->username = 'erika' . Str::lower(Str::random(8));
        $this->email = $this->username . '@example.com';

        config()->set('hydra.addons.registration', ['enabled' => true, 'code_lifetime' => 15, 'allowed_domains' => null]);
        Notification::fake();
    }

    public function testRegistrationIsHiddenWhileDisabled(): void
    {
        config()->set('hydra.addons.registration.enabled', false);

        $this->postJson('/auth/register/code', ['email' => $this->email])->assertNotFound();
        $this->postJson('/auth/register', [])->assertNotFound();
    }

    public function testVisitorCanRegisterAndIsLoggedIn(): void
    {
        $code = $this->requestCode(Str::upper($this->email));

        $this->postJson('/auth/register/verify', ['email' => $this->email, 'code' => $code])->assertNoContent();

        $this->postJson('/auth/register', [
            'name_first' => 'Erika',
            'name_last' => 'Mustermann',
            'username' => $this->username,
            'password' => 'a-good-password',
            'password_confirmation' => 'a-good-password',
        ])->assertOk()->assertJsonPath('data.complete', true);

        $user = User::query()->where('email', $this->email)->firstOrFail();
        $this->assertSame($this->username, $user->username);
        $this->assertFalse($user->root_admin);
        $this->assertTrue(password_verify('a-good-password', $user->password));
        $this->assertAuthenticatedAs($user);

        // No "account created" email for accounts people create themselves.
        Notification::assertNothingSentTo($user);
    }

    public function testAccountNeedsConfirmedEmail(): void
    {
        $this->postJson('/auth/register', [
            'name_first' => 'Erika',
            'name_last' => 'Mustermann',
            'username' => $this->username,
            'password' => 'a-good-password',
            'password_confirmation' => 'a-good-password',
        ])->assertStatus(400);

        $this->assertFalse(User::query()->where('username', $this->username)->exists());
    }

    public function testWrongCodesAreLimited(): void
    {
        $code = $this->requestCode($this->email);
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 1; $i < 5; ++$i) {
            $this->postJson('/auth/register/verify', ['email' => $this->email, 'code' => $wrong])
                ->assertStatus(400)
                ->assertJsonPath('errors.0.detail', trans('auth.register.code_invalid'));
        }

        $this->postJson('/auth/register/verify', ['email' => $this->email, 'code' => $wrong])
            ->assertJsonPath('errors.0.detail', trans('auth.register.too_many_attempts'));

        // The code is gone now, even the right one does not work anymore.
        $this->postJson('/auth/register/verify', ['email' => $this->email, 'code' => $code])
            ->assertJsonPath('errors.0.detail', trans('auth.register.code_expired'));
    }

    public function testTakenEmailsAndForeignDomainsGetNoCode(): void
    {
        $user = User::factory()->create();
        $this->postJson('/auth/register/code', ['email' => $user->email])
            ->assertStatus(400)
            ->assertJsonPath('errors.0.detail', trans('auth.register.email_taken'));

        config()->set('hydra.addons.registration.allowed_domains', 'example.org, @company.com');
        $this->postJson('/auth/register/code', ['email' => 'someone' . Str::random(8) . '@example.net'])
            ->assertStatus(400)
            ->assertJsonPath('errors.0.detail', trans('auth.register.domain_not_allowed'));
        $this->postJson('/auth/register/code', ['email' => 'someone' . Str::random(8) . '@company.com'])->assertNoContent();

        Notification::assertSentTimes(RegistrationCode::class, 1);
    }

    public function testCodesCannotBeRequestedTooOften(): void
    {
        $this->requestCode($this->email);

        $this->postJson('/auth/register/code', ['email' => $this->email])->assertStatus(400);
    }

    public function testUsernameMustBeValidAndFree(): void
    {
        $taken = User::factory()->create();
        $code = $this->requestCode($this->email);
        $this->postJson('/auth/register/verify', ['email' => $this->email, 'code' => $code])->assertNoContent();

        foreach ([$taken->username, '-bad-', 'ab'] as $username) {
            $this->postJson('/auth/register', [
                'name_first' => 'Erika',
                'name_last' => 'Mustermann',
                'username' => $username,
                'password' => 'a-good-password',
                'password_confirmation' => 'a-good-password',
            ])->assertStatus(422);
        }

        $this->assertFalse(User::query()->where('email', $this->email)->exists());
    }

    private function requestCode(string $email): string
    {
        $this->postJson('/auth/register/code', ['email' => $email])->assertNoContent();

        $code = null;
        Notification::assertSentOnDemand(RegistrationCode::class, function (RegistrationCode $notification, array $channels, AnonymousNotifiable $notifiable) use ($email, &$code) {
            $code = $notification->code;

            return $notifiable->routes['mail'] === strtolower($email);
        });

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        return $code;
    }
}
