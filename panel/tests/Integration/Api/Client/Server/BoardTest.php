<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\HydraBoardCard;
use Pterodactyl\Models\HydraBoardColumn;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

/**
 * Hydra: Kanban board of a server (addon "board").
 */
class BoardTest extends ClientApiIntegrationTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        config()->set('hydra.addons.board.enabled', true);
        config()->set('hydra.addons.board.max_columns', 3);
        config()->set('hydra.addons.board.max_cards', 3);
        config()->set('hydra.addons.board.max_tags', 2);
        config()->set('hydra.addons.board.max_items', 2);
    }

    private function column(Server $server, string $name, int $sort = 0): HydraBoardColumn
    {
        return $server->boardColumns()->create(['name' => $name, 'sort' => $sort]);
    }

    private function card(HydraBoardColumn $column, string $name, int $sort = 0): HydraBoardCard
    {
        return $column->cards()->create(['server_id' => $column->server_id, 'name' => $name, 'sort' => $sort]);
    }

    public function testRoutesOnlyExistWhileTheAddonIsOn(): void
    {
        [$user, $server] = $this->generateTestAccount();
        config()->set('hydra.addons.board.enabled', false);

        $this->actingAs($user)->getJson($this->link($server, '/board'))->assertNotFound();
    }

    public function testOwnerBuildsABoard(): void
    {
        [$user, $server] = $this->generateTestAccount();

        $this->actingAs($user)->postJson($this->link($server, '/board/columns'), ['name' => 'To do', 'color' => '#ef4444'])
            ->assertOk()
            ->assertJsonPath('columns.0.name', 'To do')
            ->assertJsonPath('columns.0.color', '#ef4444')
            ->assertJsonPath('me.release_any', true);
        $column = $server->boardColumns()->firstOrFail();

        $tag = $this->actingAs($user)->postJson($this->link($server, '/board/tags'), ['name' => 'High', 'color' => '#f97316'])
            ->assertOk()
            ->json('tags.0.id');

        $this->actingAs($user)->postJson($this->link($server, '/board/cards'), [
            'column' => $column->id,
            'name' => 'Install plugins',
            'description' => '  LuckPerms and more  ',
            'tags' => [$tag, 99999],
        ])
            ->assertOk()
            ->assertJsonPath('columns.0.cards.0.name', 'Install plugins')
            ->assertJsonPath('columns.0.cards.0.description', 'LuckPerms and more')
            ->assertJsonPath('columns.0.cards.0.tags', [$tag]);
        $card = $server->boardCards()->firstOrFail();

        $this->actingAs($user)->postJson($this->link($server, "/board/cards/$card->id/checklist"), ['text' => 'LuckPerms'])->assertOk();
        $item = $card->checklist()->firstOrFail();
        $this->actingAs($user)->patchJson($this->link($server, "/board/cards/$card->id/checklist/$item->id"), ['done' => true])
            ->assertOk()
            ->assertJsonPath('columns.0.cards.0.checklist.0.done', true);

        $this->actingAs($user)->postJson($this->link($server, "/board/cards/$card->id/claim"))
            ->assertOk()
            ->assertJsonPath('columns.0.cards.0.claimed_by.username', $user->username);

        $this->actingAs($user)->getJson($this->link($server, '/board'))
            ->assertOk()
            ->assertJsonPath('limits', ['columns' => 3, 'cards' => 3, 'tags' => 2, 'items' => 2]);
    }

    public function testLimitsAreEnforced(): void
    {
        [$user, $server] = $this->generateTestAccount();
        config()->set('hydra.addons.board.max_columns', 1);
        config()->set('hydra.addons.board.max_cards', 1);
        $column = $this->column($server, 'Only');
        $this->card($column, 'First');

        $this->actingAs($user)->postJson($this->link($server, '/board/columns'), ['name' => 'Second'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.meta.source_field', 'name');
        $this->actingAs($user)->postJson($this->link($server, '/board/cards'), ['column' => $column->id, 'name' => 'Second'])
            ->assertUnprocessable();
        $this->actingAs($user)->postJson($this->link($server, '/board/tags'), ['name' => 'Bad', 'color' => 'red'])
            ->assertUnprocessable();
    }

    public function testCardsMoveBetweenColumnsAndColumnsAreSorted(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $todo = $this->column($server, 'To do', 0);
        $done = $this->column($server, 'Done', 1);
        $a = $this->card($todo, 'A', 0);
        $b = $this->card($todo, 'B', 1);
        $c = $this->card($done, 'C', 0);

        // B to the top of "Done", before C.
        $this->actingAs($user)->putJson($this->link($server, "/board/cards/$b->id/move"), ['column' => $done->id, 'position' => 0])
            ->assertOk()
            ->assertJsonPath('columns.0.cards.*.name', ['A'])
            ->assertJsonPath('columns.1.cards.*.name', ['B', 'C']);

        // A to a position past the end lands at the end.
        $this->actingAs($user)->putJson($this->link($server, "/board/cards/$a->id/move"), ['column' => $done->id, 'position' => 50])
            ->assertOk()
            ->assertJsonPath('columns.1.cards.*.name', ['B', 'C', 'A']);

        // Within the same column.
        $this->actingAs($user)->putJson($this->link($server, "/board/cards/$a->id/move"), ['column' => $done->id, 'position' => 0])
            ->assertOk()
            ->assertJsonPath('columns.1.cards.*.name', ['A', 'B', 'C']);

        $this->actingAs($user)->putJson($this->link($server, '/board/columns/order'), ['columns' => [$done->id, $todo->id]])
            ->assertOk()
            ->assertJsonPath('columns.*.name', ['Done', 'To do']);

        // Deleting a column deletes its cards.
        $this->actingAs($user)->deleteJson($this->link($server, "/board/columns/$done->id"))->assertOk();
        $this->assertFalse(HydraBoardCard::query()->whereKey($c->id)->exists());
    }

    public function testOtherServersCannotBeTouched(): void
    {
        [$user, $server] = $this->generateTestAccount();
        [, $other] = $this->generateTestAccount();
        $foreign = $this->column($other, 'Foreign');
        $card = $this->card($foreign, 'Secret');
        $own = $this->column($server, 'Own');

        $this->actingAs($user)->patchJson($this->link($server, "/board/columns/$foreign->id"), ['name' => 'x'])->assertNotFound();
        $this->actingAs($user)->putJson($this->link($server, "/board/cards/$card->id/move"), ['column' => $own->id, 'position' => 0])->assertNotFound();
        $this->actingAs($user)->postJson($this->link($server, '/board/cards'), ['column' => $foreign->id, 'name' => 'x'])->assertNotFound();
        $this->assertSame($foreign->id, $card->refresh()->column_id);
    }

    public function testSubuserPermissions(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_BOARD_READ]);
        $column = $this->column($server, 'To do');
        $card = $this->card($column, 'Task');

        $this->actingAs($user)->getJson($this->link($server, '/board'))
            ->assertOk()
            ->assertJsonPath('me.release_any', false);
        $this->actingAs($user)->postJson($this->link($server, '/board/columns'), ['name' => 'x'])->assertForbidden();
        $this->actingAs($user)->postJson($this->link($server, '/board/cards'), ['column' => $column->id, 'name' => 'x'])->assertForbidden();
        $this->actingAs($user)->patchJson($this->link($server, "/board/cards/$card->id"), ['name' => 'x'])->assertForbidden();
        $this->actingAs($user)->deleteJson($this->link($server, "/board/cards/$card->id"))->assertForbidden();
        $this->actingAs($user)->postJson($this->link($server, "/board/cards/$card->id/claim"))->assertForbidden();
        $this->actingAs($user)->postJson($this->link($server, '/board/tags'), ['name' => 'x', 'color' => '#000000'])->assertForbidden();

        $nobody = User::factory()->create();
        Subuser::query()->create(['user_id' => $nobody->id, 'server_id' => $server->id, 'permissions' => [Permission::ACTION_WEBSOCKET_CONNECT]]);
        $this->actingAs($nobody)->getJson($this->link($server, '/board'))->assertForbidden();
    }

    public function testClaimsCanOnlyBeReleasedByTheClaimerOrTheOwner(): void
    {
        [$alice, $server] = $this->generateTestAccount([Permission::ACTION_BOARD_READ, Permission::ACTION_BOARD_CLAIM]);
        $bob = User::factory()->create();
        Subuser::query()->create(['user_id' => $bob->id, 'server_id' => $server->id, 'permissions' => [Permission::ACTION_BOARD_READ, Permission::ACTION_BOARD_CLAIM]]);
        $card = $this->card($this->column($server, 'To do'), 'Task');

        $this->actingAs($alice)->postJson($this->link($server, "/board/cards/$card->id/claim"))->assertOk();
        $this->assertSame($alice->id, $card->refresh()->claimed_by);

        // Bob can neither take over nor release Alice's claim.
        $this->actingAs($bob)->postJson($this->link($server, "/board/cards/$card->id/claim"))->assertUnprocessable();
        $this->actingAs($bob)->deleteJson($this->link($server, "/board/cards/$card->id/claim"))->assertForbidden();

        // The owner can.
        $this->actingAs($server->user)->deleteJson($this->link($server, "/board/cards/$card->id/claim"))
            ->assertOk()
            ->assertJsonPath('columns.0.cards.0.claimed_by', null);

        $this->actingAs($bob)->postJson($this->link($server, "/board/cards/$card->id/claim"))->assertOk();
        $this->actingAs($bob)->deleteJson($this->link($server, "/board/cards/$card->id/claim"))->assertOk();
        $this->assertNull($card->refresh()->claimed_by);
    }
}
