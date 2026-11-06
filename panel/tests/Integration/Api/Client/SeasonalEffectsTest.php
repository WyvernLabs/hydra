<?php

namespace Pterodactyl\Tests\Integration\Api\Client;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Setting;
use Pterodactyl\Models\AdminRole;

/**
 * Hydra: seasonal effects (Design > Effects) and what each user sees of them.
 */
class SeasonalEffectsTest extends ClientApiIntegrationTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // The admin layout prints the render time, which public/index.php normally starts.
        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }

        config()->set('hydra.effects.event', 'off');
    }

    public function tearDown(): void
    {
        Setting::query()->where('key', 'settings::hydra:effects:event')->delete();

        parent::tearDown();
    }

    private function web(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
    }

    public function testNothingIsLoadedWhileNoEventRuns(): void
    {
        $this->get('/auth/login')->assertOk()->assertDontSee('hydra/effects.js', false)->assertDontSee('HydraEffectsConfig', false);
    }

    public function testVisitorsGetTheWholeEvent(): void
    {
        config()->set('hydra.effects.event', 'christmas');

        $this->get('/auth/login')->assertOk()
            ->assertSee('hydra/effects.js', false)
            ->assertSee('"event":"christmas"', false)
            ->assertSee('"decor":true', false)
            ->assertSee('"particles":true', false);
    }

    public function testAnUnknownEventCountsAsOff(): void
    {
        config()->set('hydra.effects.event', 'something');

        $this->get('/auth/login')->assertOk()->assertDontSee('hydra/effects.js', false);
    }

    public function testEveryEventHasItsOwnOptions(): void
    {
        $user = User::factory()->create();
        $expected = [
            'off' => [],
            'halloween' => ['decor', 'particles'],
            'christmas' => ['decor', 'particles'],
            'newyear' => ['decor', 'particles', 'fireworks'],
        ];

        foreach ($expected as $event => $options) {
            config()->set('hydra.effects.event', $event);
            $this->actingAs($user)->getJson('/api/client/account/effects')->assertOk()
                ->assertJsonPath('event', $event)
                ->assertJsonPath('options', $options);
        }
    }

    public function testChoicesAreKeptPerUserAndPerEvent(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        config()->set('hydra.effects.event', 'halloween');
        $this->actingAs($user)->putJson('/api/client/account/effects', ['values' => ['particles' => false]])->assertOk()
            ->assertJsonPath('values.particles', false)
            ->assertJsonPath('values.decor', true);

        $this->actingAs($other)->getJson('/api/client/account/effects')->assertJsonPath('values.particles', true);

        // Another event starts with everything on and has its own choices.
        config()->set('hydra.effects.event', 'christmas');
        $this->actingAs($user)->getJson('/api/client/account/effects')->assertJsonPath('values.particles', true);
        $this->actingAs($user)->putJson('/api/client/account/effects', ['values' => ['decor' => false]])->assertOk();

        // Back to the first event, even after it was off for a while: still saved.
        config()->set('hydra.effects.event', 'off');
        $this->actingAs($user)->getJson('/api/client/account/effects')->assertOk();
        config()->set('hydra.effects.event', 'halloween');
        $this->actingAs($user)->getJson('/api/client/account/effects')
            ->assertJsonPath('values.particles', false)
            ->assertJsonPath('values.decor', true);

        $this->assertSame(['halloween' => ['particles' => false], 'christmas' => ['decor' => false]], $user->refresh()->hydra_effects);
    }

    public function testThePageUsesTheChoicesOfTheUser(): void
    {
        $user = User::factory()->create(['hydra_effects' => ['newyear' => ['fireworks' => false]]]);
        config()->set('hydra.effects.event', 'newyear');

        $this->web();
        $this->actingAs($user)->get('/account')->assertOk()
            ->assertSee('"fireworks":false', false)
            ->assertSee('"particles":true', false);
    }

    public function testOnlyOptionsOfTheRunningEventAreStored(): void
    {
        $user = User::factory()->create();

        config()->set('hydra.effects.event', 'halloween');
        $this->actingAs($user)->putJson('/api/client/account/effects', ['values' => ['fireworks' => false, 'other' => false, 'decor' => false]])->assertOk();
        $this->assertSame(['halloween' => ['decor' => false]], $user->refresh()->hydra_effects);

        $this->actingAs($user)->putJson('/api/client/account/effects', ['values' => ['decor' => 'nonsense']])->assertStatus(422);

        config()->set('hydra.effects.event', 'off');
        $this->actingAs($user)->putJson('/api/client/account/effects', ['values' => ['decor' => true]])->assertOk();
        $this->assertSame(['halloween' => ['decor' => false]], $user->refresh()->hydra_effects);
    }

    public function testAdminsPickTheEventWithTheirPermissions(): void
    {
        $viewer = User::factory()->create();
        $viewer->adminRoles()->attach(AdminRole::query()->create(['name' => 'Role ' . uniqid(), 'color' => '#3b9eea', 'permissions' => ['design.view']]));
        $viewer->refresh();

        $this->web();
        $this->actingAs($viewer)->get('/admin/design/effects')->assertOk();
        $this->actingAs($viewer)->patch('/admin/design/effects', ['event' => 'halloween'])->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/admin/design/effects')->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->patch('/admin/design/effects', ['event' => 'nope'])->assertStatus(422);
        $this->actingAs($admin)->patch('/admin/design/effects', ['event' => 'halloween'])->assertRedirect('/admin/design/effects');
        $this->assertSame('halloween', config('hydra.effects.event'));
        $this->assertSame('halloween', Setting::query()->where('key', 'settings::hydra:effects:event')->value('value'));

        $this->actingAs($admin)->get('/admin/design/effects')->assertOk()->assertSee('hydra/effects.js', false);
    }
}
