<?php

namespace Pterodactyl\Services\Hydra\Addons;

/**
 * Hydra: Kanban board per server (page "Board"). Users with the permissions
 * create colored columns, cards ("blocks") with a description, a checklist
 * and tags, move them by drag and drop and claim cards they work on. The
 * admin sets how many columns, cards and tags a server may have.
 */
class BoardAddon extends Addon
{
    public const BUTTON = 'board';

    public function key(): string
    {
        return 'board';
    }

    public function icon(): string
    {
        return 'square-kanban';
    }

    public function rules(): array
    {
        return [
            'max_columns' => 'required|integer|min:1|max:100',
            'max_cards' => 'required|integer|min:1|max:5000',
            'max_tags' => 'required|integer|min:0|max:200',
            'max_items' => 'required|integer|min:0|max:500',
        ];
    }

    public function summary(): array
    {
        return [trans('admin/addons.board.summary', [
            'columns' => $this->maxColumns(),
            'cards' => $this->maxCards(),
        ])];
    }

    /**
     * Columns a server may have.
     */
    public function maxColumns(): int
    {
        return max(1, min(100, (int) $this->get('max_columns', 20)));
    }

    /**
     * Cards a server may have over all columns.
     */
    public function maxCards(): int
    {
        return max(1, min(5000, (int) $this->get('max_cards', 500)));
    }

    /**
     * Tags a server may have, 0 turns tags off.
     */
    public function maxTags(): int
    {
        return max(0, min(200, (int) $this->get('max_tags', 30)));
    }

    /**
     * Checklist entries per card, 0 turns checklists off.
     */
    public function maxItems(): int
    {
        return max(0, min(500, (int) $this->get('max_items', 50)));
    }

    public function limits(): array
    {
        return [
            'columns' => $this->maxColumns(),
            'cards' => $this->maxCards(),
            'tags' => $this->maxTags(),
            'items' => $this->maxItems(),
        ];
    }
}
