<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\AdminRole;
use Pterodactyl\Models\ActivityLog;
use Pterodactyl\Services\Hydra\ServerExpiryService;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;

/**
 * Hydra: servers with a limited runtime.
 */
class ServerExpiryTest extends HttpTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }

        $this->mock(DaemonServerRepository::class, function ($mock) {
            $mock->allows('setServer')->andReturnSelf();
            $mock->allows('sync');
        });
    }

    private function staff(array $permissions): User
    {
        $user = User::factory()->create();
        $role = AdminRole::query()->create(['name' => 'Expiry ' . uniqid(), 'color' => '#2fb67c', 'permissions' => $permissions]);
        $user->adminRoles()->attach($role);

        return $user->refresh();
    }

    public function testRuntimeCanBeSetAndRemovedWithItsPermission(): void
    {
        $server = $this->createServerModel();
        $until = now()->addDays(3)->setSeconds(0);

        $this->actingAs($this->staff(['servers.manage']))
            ->post("/admin/servers/view/$server->id/manage/expiry", ['hydra_expires_at' => ServerExpiryService::inputValue($until)])
            ->assertForbidden();

        $staff = $this->staff(['servers.expiry', 'servers.view']);
        $this->actingAs($staff)->get("/admin/servers/view/$server->id/manage")->assertOk()->assertSee('hydraExpiresAt', false);
        $this->actingAs($staff)
            ->post("/admin/servers/view/$server->id/manage/expiry", ['hydra_expires_at' => ServerExpiryService::inputValue($until)])
            ->assertSessionHasNoErrors()
            ->assertRedirect("/admin/servers/view/$server->id/manage");

        $server->refresh();
        $this->assertSame($until->getTimestamp(), $server->hydra_expires_at->getTimestamp());
        $this->assertTrue(ActivityLog::query()->where('event', 'server:expiry.update')->exists());

        // Shown in the server list.
        $this->actingAs($staff)->get("/admin/servers?filter[owner_id]=$server->owner_id")->assertOk()
            ->assertSee('data-hydra-expires="' . $server->hydra_expires_at->toAtomString() . '"', false);

        $this->actingAs($staff)->post("/admin/servers/view/$server->id/manage/expiry", ["clear" => "1"])->assertSessionHasNoErrors()->assertRedirect("/admin/servers/view/$server->id/manage");
        $this->assertNull($server->refresh()->hydra_expires_at);
        $this->assertTrue(ActivityLog::query()->where('event', 'server:expiry.clear')->exists());

        $this->actingAs($staff)->post("/admin/servers/view/$server->id/manage/expiry", ['hydra_expires_at' => 'tomorrow'])
            ->assertStatus(422)->assertJsonFragment(['source_field' => 'hydra_expires_at']);
    }

    public function testClientApiShowsTheRuntime(): void
    {
        $server = $this->createServerModel();
        $this->actingAs($server->user)->getJson("/api/client/servers/$server->uuid")->assertOk()
            ->assertJsonPath('attributes.expires_at', null);

        $server->forceFill(['hydra_expires_at' => now()->addDay()->setSeconds(0)])->save();
        $this->actingAs($server->user)->getJson("/api/client/servers/$server->uuid")->assertOk()
            ->assertJsonPath('attributes.expires_at', $server->refresh()->hydra_expires_at->toAtomString());
    }

    public function testExpiredServersAreSuspendedOnce(): void
    {
        $expired = $this->createServerModel();
        $expired->forceFill(['hydra_expires_at' => now()->subMinute()])->save();
        $running = $this->createServerModel();
        $running->forceFill(['hydra_expires_at' => now()->addHour()])->save();
        $unlimited = $this->createServerModel();

        $this->artisan('hydra:servers:expire')->assertSuccessful();

        $this->assertTrue($expired->refresh()->isSuspended());
        $this->assertNotNull($expired->hydra_expired_at);
        $this->assertFalse($running->refresh()->isSuspended());
        $this->assertFalse($unlimited->refresh()->isSuspended());
        $this->assertTrue(ActivityLog::query()->where('event', 'server:expiry.expired')->exists());

        // An admin lifts the suspension by hand: the next run leaves the server alone.
        $expired->update(['status' => null]);
        app(ServerExpiryService::class)->expire();
        $this->assertFalse($expired->refresh()->isSuspended());
    }

    public function testExtendingLiftsOnlyTheSuspensionOfTheRuntime(): void
    {
        $service = app(ServerExpiryService::class);

        $server = $this->createServerModel();
        $server->forceFill(['hydra_expires_at' => now()->subMinute()])->save();
        $service->expire();
        $this->assertTrue($server->refresh()->isSuspended());
        $this->assertTrue($service->suspendedByExpiry($server));

        $service->set($server, now()->addDays(7));
        $this->assertFalse($server->refresh()->isSuspended());
        $this->assertNull($server->hydra_expired_at);

        // Suspended by an admin long before it expired: stays suspended.
        $other = $this->createServerModel();
        $other->forceFill(['status' => Server::STATUS_SUSPENDED, 'hydra_suspended_at' => now()->subDays(2), 'hydra_expires_at' => now()->subMinute()])->save();
        $service->expire();
        $this->assertFalse($service->suspendedByExpiry($other->refresh()));

        $service->set($other, now()->addDays(7));
        $this->assertTrue($other->refresh()->isSuspended());
    }

    public function testInputValuesUseThePanelTimeZone(): void
    {
        config()->set('app.timezone', 'Europe/Berlin');

        $date = ServerExpiryService::parse('2027-01-15T20:30');
        $this->assertSame('2027-01-15T19:30:00+00:00', $date->utc()->toAtomString());
        $this->assertSame('2027-01-15T20:30', ServerExpiryService::inputValue($date));
        $this->assertNull(ServerExpiryService::parse(''));
    }
}
