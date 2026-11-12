<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\Permission;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\HydraBoardTag;
use Pterodactyl\Models\HydraBoardCard;
use Pterodactyl\Models\HydraBoardColumn;
use Pterodactyl\Models\HydraBoardChecklistItem;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Pterodactyl\Services\Hydra\Addons\BoardAddon;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;

/**
 * Hydra: Kanban board of a server (addon "board"). Every change answers with
 * the whole board, so the page always shows the order the panel stored, also
 * when several people work on the board at the same time.
 */
class BoardController extends ClientApiController
{
    private const COLOR = 'regex:/^#[0-9a-fA-F]{6}$/';

    public function __construct(private BoardAddon $addon)
    {
        parent::__construct();
    }

    public function index(Request $request, Server $server): array
    {
        $this->check($request, $server, Permission::ACTION_BOARD_READ);

        return $this->board($request, $server);
    }

    // Columns ---------------------------------------------------------------

    public function storeColumn(Request $request, Server $server): array
    {
        $this->check($request, $server, Permission::ACTION_BOARD_COLUMNS);
        $data = $request->validate([
            'name' => 'required|string|max:64',
            'color' => ['nullable', 'string', self::COLOR],
        ]);

        if ($server->boardColumns()->count() >= $this->addon->maxColumns()) {
            throw ValidationException::withMessages(['name' => trans('server/board.errors.max_columns', ['max' => $this->addon->maxColumns()])]);
        }

        $server->boardColumns()->create([
            'name' => trim($data['name']),
            'color' => $data['color'] ?? null,
            'sort' => (int) $server->boardColumns()->max('sort') + 1,
        ]);

        Activity::event('server:board.column.create')->property('name', trim($data['name']))->log();

        return $this->board($request, $server);
    }

    public function updateColumn(Request $request, Server $server, int $column): array
    {
        $this->check($request, $server, Permission::ACTION_BOARD_COLUMNS);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:64',
            'color' => ['sometimes', 'nullable', 'string', self::COLOR],
        ]);

        $model = $this->column($server, $column);
        if (array_key_exists('name', $data)) {
            $model->name = trim($data['name']);
        }
        if (array_key_exists('color', $data)) {
            $model->color = $data['color'];
        }
        $model->save();

        return $this->board($request, $server);
    }

    public function deleteColumn(Request $request, Server $server, int $column): array
    {
        $this->check($request, $server, Permission::ACTION_BOARD_COLUMNS);

        $model = $this->column($server, $column);
        $name = $model->name;
        $cards = $model->cards()->count();
        $model->delete();

        Activity::event('server:board.column.delete')->property(['name' => $name, 'cards' => $cards])->log();

        return $this->board($request, $server);
    }

    /**
     * Stores the order of the columns, given as the list of all column ids.
     */
    public function orderColumns(Request $request, Server $server): array
    {
        $this->check($request, $server, Permission::ACTION_BOARD_COLUMNS);
        $data = $request->validate([
            'columns' => 'required|array|max:100',
            'columns.*' => 'integer',
        ]);

        $ids = $server->boardColumns()->pluck('id')->all();
        $order = array_values(array_unique(array_map('intval', $data['columns'])));
        // Columns the list does not know (created meanwhile) keep their place at the end.
        $order = array_merge(array_values(array_intersect($order, $ids)), array_values(array_diff($ids, $order)));

        DB::transaction(function () use ($server, $order) {
            foreach ($order as $index => $id) {
                HydraBoardColumn::query()->where('server_id', $server->id)->whereKey($id)->update(['sort' => $index]);
            }
        });

        return $this->board($request, $server);
    }

    // Cards -----------------------------------------------------------------

    public function storeCard(Request $request, Server $server): array
    {
        $this->check($request, $server, Permission::ACTION_BOARD_CREATE);
        $data = $request->validate([
            'column' => 'required|integer',
            'name' => 'required|string|max:191',
            'description' => 'nullable|string|max:10000',
            'tags' => 'array',
            'tags.*' => 'integer',
        ]);

        $column = $this->column($server, (int) $data['column']);
        if ($server->boardCards()->count() >= $this->addon->maxCards()) {
            throw ValidationException::withMessages(['name' => trans('server/board.errors.max_cards', ['max' => $this->addon->maxCards()])]);
        }

        /** @var HydraBoardCard $card */
        $card = $column->cards()->create([
            'server_id' => $server->id,
            'name' => trim($data['name']),
            'description' => $this->text($data['description'] ?? null),
            'sort' => (int) $column->cards()->max('sort') + 1,
            'created_by' => $request->user()->id,
        ]);
        if (!empty($data['tags']) && $this->allowed($request, $server, Permission::ACTION_BOARD_UPDATE)) {
            $card->tags()->sync($this->tagIds($server, $data['tags']));
        }

        Activity::event('server:board.card.create')->property(['name' => $card->name, 'column' => $column->name])->log();

        return $this->board($request, $server);
    }

    public function updateCard(Request $request, Server $server, int $card): array
    {
        $this->check($request, $server, Permission::ACTION_BOARD_UPDATE);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:191',
            'description' => 'sometimes|nullable|string|max:10000',
            'tags' => 'sometimes|array',
            'tags.*' => 'integer',
        ]);

        $model = $this->card($server, $card);
        if (array_key_exists('name', $data)) {
            $model->name = trim($data['name']);
        }
        if (array_key_exists('description', $data)) {
            $model->description = $this->text($data['description']);
        }
        $model->save();
        if (array_key_exists('tags', $data)) {
            $model->tags()->sync($this->tagIds($server, $data['tags']));
        }

        return $this->board($request, $server);
    }

    /**
     * Moves a card into a column (also the same one) at the given position.
     */
    public function moveCard(Request $request, Server $server, int $card): array
    {
        $this->check($request, $server, Permission::ACTION_BOARD_UPDATE);
        $data = $request->validate([
            'column' => 'required|integer',
            'position' => 'required|integer|min:0',
        ]);

        $model = $this->card($server, $card);
        $column = $this->column($server, (int) $data['column']);

        DB::transaction(function () use ($model, $column, $data) {
            $ids = $column->cards()->whereKeyNot($model->id)->pluck('id')->all();
            array_splice($ids, min((int) $data['position'], count($ids)), 0, [$model->id]);

            $model->column_id = $column->id;
            $model->save();
            foreach ($ids as $index => $id) {
                HydraBoardCard::query()->whereKey($id)->update(['sort' => $index]);
            }
        });

        return $this->board($request, $server);
    }

    public function deleteCard(Request $request, Server $server, int $card): array
    {
        $this->check($request, $server, Permission::ACTION_BOARD_DELETE);

        $model = $this->card($server, $card);
        $name = $model->name;
        $model->delete();

        Activity::event('server:board.card.delete')->property('name', $name)->log();

        return $this->board($request, $server);
    }

    /**
     * Claims a card for the current user. A card claimed by someone else has
     * to be released first.
     */
    public function claim(Request $request, Server $server, int $card): array
    {
        $this->check($request, $server, Permission::ACTION_BOARD_CLAIM);

        $model = $this->card($server, $card);
        if (!is_null($model->claimed_by) && $model->claimed_by !== $request->user()->id) {
            throw ValidationException::withMessages(['card' => trans('server/board.errors.claimed', ['user' => $model->claimer?->username ?? '?'])]);
        }

        $model->forceFill(['claimed_by' => $request->user()->id, 'claimed_at' => now()])->save();

        return $this->board($request, $server);
    }

    /**
     * Releases a claim. Everyone may release their own claim; claims of
     * others only the owner of the server and admins with access to it.
     */
    public function release(Request $request, Server $server, int $card): array
    {
        $model = $this->card($server, $card);
        $own = $model->claimed_by === $request->user()->id;
        if ($own) {
            $this->check($request, $server, Permission::ACTION_BOARD_CLAIM);
        } elseif (!is_null($model->claimed_by) && !$this->isOwner($request->user(), $server)) {
            throw new AuthorizationException();
        }

        $model->forceFill(['claimed_by' => null, 'claimed_at' => null])->save();

        return $this->board($request, $server);
    }

    // Checklist -------------------------------------------------------------

    public function storeItem(Request $request, Server $server, int $card): array
    {
        $this->check($request, $server, Permission::ACTION_BOARD_UPDATE);
        $data = $request->validate(['text' => 'required|string|max:255']);

        $model = $this->card($server, $card);
        if ($model->checklist()->count() >= $this->addon->maxItems()) {
            throw ValidationException::withMessages(['text' => trans('server/board.errors.max_items', ['max' => $this->addon->maxItems()])]);
        }

        $model->checklist()->create([
            'text' => trim($data['text']),
            'done' => false,
            'sort' => (int) $model->checklist()->max('sort') + 1,
        ]);

        return $this->board($request, $server);
    }

    public function updateItem(Request $request, Server $server, int $card, int $item): array
    {
        $this->check($request, $server, Permission::ACTION_BOARD_UPDATE);
        $data = $request->validate([
            'text' => 'sometimes|required|string|max:255',
            'done' => 'sometimes|required|boolean',
        ]);

        $model = $this->item($server, $card, $item);
        if (array_key_exists('text', $data)) {
            $model->text = trim($data['text']);
        }
        if (array_key_exists('done', $data)) {
            $model->done = (bool) $data['done'];
        }
        $model->save();

        return $this->board($request, $server);
    }

    public function deleteItem(Request $request, Server $server, int $card, int $item): array
    {
        $this->check($request, $server, Permission::ACTION_BOARD_UPDATE);

        $this->item($server, $card, $item)->delete();

        return $this->board($request, $server);
    }

    // Tags ------------------------------------------------------------------

    public function storeTag(Request $request, Server $server): array
    {
        $this->check($request, $server, Permission::ACTION_BOARD_TAGS);
        $data = $request->validate([
            'name' => 'required|string|max:32',
            'color' => ['required', 'string', self::COLOR],
        ]);

        if ($server->boardTags()->count() >= $this->addon->maxTags()) {
            throw ValidationException::withMessages(['name' => trans('server/board.errors.max_tags', ['max' => $this->addon->maxTags()])]);
        }

        $server->boardTags()->create(['name' => trim($data['name']), 'color' => $data['color']]);

        return $this->board($request, $server);
    }

    public function updateTag(Request $request, Server $server, int $tag): array
    {
        $this->check($request, $server, Permission::ACTION_BOARD_TAGS);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:32',
            'color' => ['sometimes', 'required', 'string', self::COLOR],
        ]);

        $model = $this->tag($server, $tag);
        if (array_key_exists('name', $data)) {
            $model->name = trim($data['name']);
        }
        if (array_key_exists('color', $data)) {
            $model->color = $data['color'];
        }
        $model->save();

        return $this->board($request, $server);
    }

    public function deleteTag(Request $request, Server $server, int $tag): array
    {
        $this->check($request, $server, Permission::ACTION_BOARD_TAGS);

        $this->tag($server, $tag)->delete();

        return $this->board($request, $server);
    }

    // Helpers ---------------------------------------------------------------

    /**
     * The whole board with what the current user may do on it.
     */
    private function board(Request $request, Server $server): array
    {
        $columns = $server->boardColumns()
            ->with(['cards.tags:id', 'cards.checklist', 'cards.claimer'])
            ->get();

        return [
            'columns' => $columns->map(fn (HydraBoardColumn $column) => [
                'id' => $column->id,
                'name' => $column->name,
                'color' => $column->color,
                'cards' => $column->cards->map(fn (HydraBoardCard $card) => [
                    'id' => $card->id,
                    'name' => $card->name,
                    'description' => $card->description,
                    'tags' => $card->tags->pluck('id')->all(),
                    'checklist' => $card->checklist->map(fn (HydraBoardChecklistItem $item) => [
                        'id' => $item->id,
                        'text' => $item->text,
                        'done' => $item->done,
                    ])->values(),
                    'claimed_by' => $card->claimer ? [
                        'id' => $card->claimer->id,
                        'username' => $card->claimer->username,
                        'avatar' => $card->claimer->avatarUrl(),
                    ] : null,
                    'claimed_at' => $card->claimed_at?->toAtomString(),
                    'created_at' => $card->created_at->toAtomString(),
                    'updated_at' => $card->updated_at->toAtomString(),
                ])->values(),
            ])->values(),
            'tags' => $server->boardTags()->get()->map(fn (HydraBoardTag $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ])->values(),
            'limits' => $this->addon->limits(),
            'me' => [
                'id' => $request->user()->id,
                // May release the claims of other users.
                'release_any' => $this->isOwner($request->user(), $server),
            ],
        ];
    }

    private function allowed(Request $request, Server $server, string $permission): bool
    {
        return $request->user()->can($permission, $server);
    }

    /**
     * @throws AuthorizationException
     */
    private function check(Request $request, Server $server, string $permission): void
    {
        if (!$this->allowed($request, $server, $permission)) {
            throw new AuthorizationException();
        }
    }

    private function isOwner(User $user, Server $server): bool
    {
        return $user->id === $server->owner_id || $user->canAccessAllServers();
    }

    private function column(Server $server, int $id): HydraBoardColumn
    {
        /* @var HydraBoardColumn */
        return $server->boardColumns()->findOrFail($id);
    }

    private function card(Server $server, int $id): HydraBoardCard
    {
        /* @var HydraBoardCard */
        return $server->boardCards()->findOrFail($id);
    }

    private function item(Server $server, int $card, int $id): HydraBoardChecklistItem
    {
        /* @var HydraBoardChecklistItem */
        return $this->card($server, $card)->checklist()->findOrFail($id);
    }

    private function tag(Server $server, int $id): HydraBoardTag
    {
        /* @var HydraBoardTag */
        return $server->boardTags()->findOrFail($id);
    }

    /**
     * Ids of the given tags that belong to the server.
     *
     * @return int[]
     */
    private function tagIds(Server $server, array $ids): array
    {
        return $server->boardTags()->whereIn('id', array_map('intval', $ids))->pluck('id')->all();
    }

    private function text(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
