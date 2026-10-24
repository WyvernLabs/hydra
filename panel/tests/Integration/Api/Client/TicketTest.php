<?php

namespace Pterodactyl\Tests\Integration\Api\Client;

use Illuminate\Support\Str;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\AdminRole;
use Illuminate\Http\UploadedFile;
use Pterodactyl\Models\TicketAttachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;
use Pterodactyl\Notifications\TicketAnswered;

/**
 * Hydra: support tickets (tickets addon) in the account and in the admin area.
 */
class TicketTest extends ClientApiIntegrationTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // The admin layout prints the render time, which public/index.php normally starts.
        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }

        config()->set('hydra.addons.tickets', [
            'enabled' => true,
            'languages' => json_encode(['de', 'en']),
            'categories' => json_encode([
                ['id' => 'billing', 'names' => ['de' => 'Abrechnung', 'en' => 'Billing']],
                ['id' => 'technical', 'names' => ['de' => 'Technik']],
            ]),
            'upload_limit' => 5,
            'max_open' => 2,
            'notify' => true,
        ]);
        Storage::fake('local');
        Notification::fake();
    }

    protected function tearDown(): void
    {
        // Settings saved in the admin area are not rolled back, other tests expect the addons off.
        \Pterodactyl\Models\Setting::query()->where('key', 'like', 'settings::hydra:addons:tickets:%')->delete();

        parent::tearDown();
    }

    public function testRoutesAreGoneWhileTheAddonIsOff(): void
    {
        config()->set('hydra.addons.tickets.enabled', false);
        $user = User::factory()->create();

        $this->login($user)->getJson('/api/client/account/tickets')->assertNotFound();
        $this->login(User::factory()->admin()->create())->get('/admin/tickets')->assertNotFound();
    }

    public function testUserOpensTicketWithImageAndTeamAnswers(): void
    {
        $user = User::factory()->create();

        $image = $this->login($user)->postJson('/api/client/account/tickets/attachments', [
            'image' => UploadedFile::fake()->image('screenshot.png', 40, 40),
        ])->assertOk()->json();

        $response = $this->login($user)->postJson('/api/client/account/tickets', [
            'subject' => 'Server does not start',
            'category' => 'technical',
            'language' => 'en',
            'priority' => 'high',
            'body' => "It crashes:\n\n![screenshot](" . $image['url'] . ")\n\n![tracker](https://example.com/pixel.png)",
        ])->assertCreated()
            ->assertJsonPath('status', 'open')
            // Without an English name the first support language is used.
            ->assertJsonPath('category', 'Technik')
            ->assertJsonPath('language', 'en');

        $ticket = Ticket::query()->findOrFail($response->json('id'));
        $attachment = TicketAttachment::query()->where('uuid', $image['uuid'])->firstOrFail();
        $this->assertSame($ticket->id, $attachment->ticket_id);

        // Own images are shown, other images become links.
        $html = $this->login($user)->getJson("/api/client/account/tickets/{$ticket->id}")
            ->assertOk()
            ->json('messages.0.html');
        $this->assertStringContainsString('<img src="' . $image['url'] . '"', $html);
        $this->assertStringNotContainsString('<img src="https://example.com', $html);
        $this->assertStringContainsString('href="https://example.com/pixel.png"', $html);

        // Another user sees neither the ticket nor the image.
        $other = User::factory()->create();
        $this->login($other)->getJson("/api/client/account/tickets/{$ticket->id}")->assertNotFound();
        $this->login($other)->get($image['url'])->assertNotFound();
        $this->login($user)->get($image['url'])->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        // The team answers and writes an internal note.
        $admin = $this->teamMember(['tickets.view', 'tickets.reply', 'tickets.manage']);
        $this->login($admin)->get("/admin/tickets/view/{$ticket->id}")->assertOk()->assertSee('Server does not start');
        $this->login($admin)->get($image['url'])->assertOk();

        $this->login($admin)->post("/admin/tickets/view/{$ticket->id}/reply", ['body' => 'Please check the logs.'])->assertRedirect();
        $this->login($admin)->post("/admin/tickets/view/{$ticket->id}/reply", ['body' => 'Secret note', 'internal' => 1])->assertRedirect();

        $this->assertSame(Ticket::STATUS_ANSWERED, $ticket->refresh()->status);
        Notification::assertSentToTimes($user, TicketAnswered::class, 1);

        $messages = $this->login($user)->getJson("/api/client/account/tickets/{$ticket->id}")->json('messages');
        $this->assertCount(2, $messages);
        $this->assertTrue($messages[1]['staff']);
        $this->assertStringNotContainsString('Secret note', json_encode($messages));

        // The user answers, which hands it back to the team, then closes it.
        $this->login($user)->postJson("/api/client/account/tickets/{$ticket->id}/messages", ['body' => 'Thanks!'])->assertNoContent();
        $this->assertSame(Ticket::STATUS_OPEN, $ticket->refresh()->status);
        $this->login($user)->postJson("/api/client/account/tickets/{$ticket->id}/close")->assertNoContent();
        $this->assertTrue($ticket->refresh()->isClosed());
    }

    public function testCategoryAndLimitOfOpenTickets(): void
    {
        $user = User::factory()->create();
        $data = ['subject' => 'Question', 'priority' => 'normal', 'body' => 'Hello', 'language' => 'de'];

        $this->login($user)->postJson('/api/client/account/tickets', $data + ['category' => 'unknown'])->assertUnprocessable();
        $this->login($user)->postJson('/api/client/account/tickets', ['language' => 'fr'] + $data + ['category' => 'billing'])->assertUnprocessable();

        $this->login($user)->postJson('/api/client/account/tickets', $data + ['category' => 'billing'])
            ->assertCreated()
            ->assertJsonPath('category', 'Abrechnung');
        $this->login($user)->postJson('/api/client/account/tickets', $data + ['category' => 'billing'])->assertCreated();
        $this->login($user)->postJson('/api/client/account/tickets', $data + ['category' => 'billing'])->assertStatus(409);
    }

    public function testAdminSetsLanguagesAndCategories(): void
    {
        $admin = User::factory()->admin()->create();

        $this->login($admin)->patch('/admin/design/addons/tickets', [
            'enabled' => 1,
            'languages' => ['de', 'fr'],
            'categories' => [
                ['id' => 'billing', 'names' => ['de' => 'Abrechnung', 'fr' => 'Facturation', 'en' => 'Billing']],
                ['id' => '', 'names' => ['de' => 'Neu', 'fr' => '']],
                ['id' => '', 'names' => ['de' => '', 'fr' => '']],
            ],
            'upload_limit' => 5,
            'max_open' => 3,
            'notify' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $addon = app(\Pterodactyl\Services\Hydra\Addons\TicketsAddon::class);
        $this->assertSame(['de', 'fr'], $addon->languages());
        $categories = $addon->categories();
        // Empty rows are dropped, names of other languages too, new rows get an ID.
        $this->assertCount(2, $categories);
        $this->assertSame(['de' => 'Abrechnung', 'fr' => 'Facturation'], $categories[0]['names']);
        $this->assertNotEmpty($categories[1]['id']);
        $this->assertSame('Facturation', $addon->categoryName('billing', 'fr'));
        $this->assertSame('Abrechnung', $addon->categoryName('billing', 'en'));

        $this->login($admin)->get('/admin/design/addons/tickets')->assertOk()->assertSee('Facturation');

        // Unknown language codes are refused.
        $this->login($admin)->patch('/admin/design/addons/tickets', [
            'enabled' => 1,
            'languages' => ['xx'],
            'upload_limit' => 5,
            'max_open' => 3,
        ])->assertStatus(422)->assertJsonPath('errors.0.meta.source_field', 'languages.0');
    }

    public function testTeamPermissions(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::query()->create(['user_id' => $user->id, 'subject' => 'Help me', 'language' => 'de', 'last_reply_at' => now()]);
        $ticket->messages()->create(['user_id' => $user->id, 'body' => 'Please']);

        $viewer = $this->teamMember(['tickets.view']);
        $this->login($viewer)->get('/admin/tickets')->assertOk()->assertSee('Help me');
        $this->login($viewer)->post("/admin/tickets/view/{$ticket->id}/reply", ['body' => 'x'])->assertForbidden();
        $this->login($viewer)->delete("/admin/tickets/view/{$ticket->id}")->assertForbidden();

        $this->login($this->teamMember([]))->get('/admin/tickets')->assertForbidden();

        $manager = $this->teamMember(['tickets.view', 'tickets.reply', 'tickets.manage', 'tickets.delete']);
        $this->login($manager)->patch("/admin/tickets/view/{$ticket->id}", [
            'status' => 'closed',
            'priority' => 'urgent',
            'assigned_to' => $manager->id,
        ])->assertRedirect();
        $ticket->refresh();
        $this->assertTrue($ticket->isClosed());
        $this->assertSame('urgent', $ticket->priority);
        $this->assertSame($manager->id, $ticket->assigned_to);

        $this->login($manager)->delete("/admin/tickets/view/{$ticket->id}")->assertRedirect('/admin/tickets');
        $this->assertNull(Ticket::query()->find($ticket->id));
    }

    private function teamMember(array $permissions): User
    {
        $user = User::factory()->create();
        if (!empty($permissions)) {
            $role = AdminRole::query()->create([
                'name' => 'Support ' . Str::random(8),
                'color' => '#3b9eea',
                'permissions' => $permissions,
            ]);
            $user->adminRoles()->attach($role);
        }

        return $user;
    }

    /**
     * Logs in as the user for the next request. The guards keep the user of
     * earlier requests and the API switches the default guard, so both are
     * reset when a test changes users.
     */
    private function login(User $user): static
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        return $this->actingAs($user);
    }
}
