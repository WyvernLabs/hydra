<?php

/*
 * Hydra: Kanban board of a server (addon "board").
 */
return [
    'title' => 'Board',
    'summary' => ':columns columns · :cards blocks',
    'loading' => 'Loading the board…',
    'empty' => 'This board has no columns yet.',
    'empty_manage' => 'This board has no columns yet. Create a column, for example "To do", "In progress" and "Done".',

    'actions' => [
        'refresh' => 'Refresh',
        'cancel' => 'Cancel',
        'save' => 'Save',
        'close' => 'Close',
    ],

    'color' => [
        'none' => 'No color',
        'custom' => 'Other color',
    ],

    'column' => [
        'create' => 'Add column',
        'create_first' => 'Create first column',
        'edit' => 'Edit column',
        'delete' => 'Delete column',
        'delete_title' => 'Delete column ":name"?',
        'delete_with_cards_one' => 'The column and its block are deleted for good.',
        'delete_with_cards_other' => 'The column and its :count blocks are deleted for good.',
        'delete_empty' => 'The empty column is deleted.',
        'name' => 'Name',
        'name_placeholder' => 'e.g. In progress',
        'color' => 'Color',
        'empty' => 'No blocks',
    ],

    'card' => [
        'title' => 'Block',
        'name' => 'Name',
        'description' => 'Description',
        'description_placeholder' => 'Add a description…',
        'no_description' => 'No description.',
        'has_description' => 'Has a description',
        'claim' => 'Claimed by',
        'claim_action' => 'Claim',
        'release' => 'Release',
        'unclaimed' => 'Nobody works on this block.',
        'claimed_since' => 'claimed :time',
        'claimed_by' => 'Claimed by :user',
        'you' => 'You',
        'column' => 'Column',
        'tags' => 'Tags',
        'created' => 'Created :time',
        'delete' => 'Delete block',
        'delete_confirm' => 'Delete this block for good?',
        'add' => 'Add block',
        'new_placeholder' => 'Name of the block',
        'new_help' => 'Enter adds the block, Esc closes.',
        'full' => 'Maximum of :max blocks reached',
    ],

    'checklist' => [
        'title' => 'Checklist',
        'progress' => ':done of :total done',
        'empty' => 'No checklist entries.',
        'full' => 'Maximum of :max entries reached.',
        'add_placeholder' => 'Add an entry…',
        'delete' => 'Remove entry',
    ],

    'tags' => [
        'title' => 'Tags',
        'help' => 'Tags mark blocks, for example with a priority. Name and color are up to you.',
        'manage' => 'Manage tags',
        'name' => 'Name of the tag',
        'delete' => 'Delete tag',
        'empty' => 'No tags yet.',
        'full' => 'Maximum of :max tags reached.',
        'new_placeholder' => 'New tag, e.g. High priority',
        'add' => 'Add',
    ],
];
