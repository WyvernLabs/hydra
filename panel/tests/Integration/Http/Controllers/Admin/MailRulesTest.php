<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Pterodactyl\Models\User;
use Mockery\MockInterface;
use Pterodactyl\Models\Subuser;
use Illuminate\Support\Facades\Notification;
use Pterodactyl\Notifications\AccountDeleted;
use Pterodactyl\Notifications\ServerSuspended;
use Illuminate\Notifications\AnonymousNotifiable;
use Pterodactyl\Services\Servers\SuspensionService;
use Pterodactyl\Services\Users\UserCreationService;
use Pterodactyl\Services\Users\UserDeletionService;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;

/**
 * Hydra: emails are only sent for the registration code, a password reset,
 * a deleted account, a suspended server and a ban.
 */
class MailRulesTest extends HttpTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->mock(DaemonServerRepository::class, function (MockInterface $mock) {
            $mock->allows('setServer')->andReturnSelf();
            $mock->allows('sync');
        });
    }

    public function testSuspendedServerIsMailedToTheOwner(): void
    {
        [$user, $server] = $this->generateTestAccount();

        app(SuspensionService::class)->toggle($server, SuspensionService::ACTION_SUSPEND);
        Notification::assertSentTo($user, ServerSuspended::class, fn (ServerSuspended $n) => $n->server === $server->name);

        // Lifting it again sends nothing.
        app(SuspensionService::class)->toggle($server->refresh(), SuspensionService::ACTION_UNSUSPEND);
        Notification::assertSentToTimes($user, ServerSuspended::class, 1);
    }

    public function testDeletedAccountIsMailedToItsAddress(): void
    {
        $user = User::factory()->create(['name_first' => 'Erika']);
        $email = $user->email;

        app(UserDeletionService::class)->handle($user);

        Notification::assertSentOnDemand(AccountDeleted::class, function (AccountDeleted $n, array $channels, AnonymousNotifiable $notifiable) use ($email) {
            return $notifiable->routes['mail'] === $email && $n->name === 'Erika';
        });
    }

    public function testOtherEventsSendNoMail(): void
    {
        [, $server] = $this->generateTestAccount();
        $subuser = User::factory()->create();

        $created = app(UserCreationService::class)->handle([
            'email' => 'mailrules-' . uniqid() . '@example.com',
            'username' => 'mailrules' . uniqid(),
            'name_first' => 'New',
            'name_last' => 'User',
        ]);

        $entry = Subuser::query()->create(['user_id' => $subuser->id, 'server_id' => $server->id, 'permissions' => ['control.console']]);
        $entry->delete();

        Notification::assertNothingSentTo($created);
        Notification::assertNothingSentTo($subuser);
    }
}
