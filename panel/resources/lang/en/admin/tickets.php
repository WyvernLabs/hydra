<?php

/*
 * Hydra: support tickets in the admin area (Community > Tickets, tickets addon).
 */
return [
    'title' => 'Tickets',
    'subtitle' => 'Support requests of your users.',
    'list' => 'Tickets',
    'view_title' => 'Ticket #:id',
    'search' => 'Search subject, user or #ID',
    'empty' => 'No tickets here.',

    'tabs' => [
        'active' => 'Unclosed',
        'open' => 'Waiting for the team',
        'answered' => 'Answered',
        'closed' => 'Closed',
        'all' => 'All',
    ],

    'statuses' => [
        'open' => 'Waiting for the team',
        'answered' => 'Answered',
        'closed' => 'Closed',
    ],

    'priorities' => [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
        'urgent' => 'Urgent',
    ],

    'assigned_any' => 'Every team member',
    'assigned_me' => 'Assigned to me',
    'assigned_none' => 'Not assigned',

    'subject' => 'Subject',
    'user' => 'User',
    'server' => 'Server',
    'language' => 'Language',
    'language_any' => 'Every language',
    'category' => 'Category',
    'priority' => 'Priority',
    'status' => 'Status',
    'assignee' => 'Assigned to',
    'nobody' => 'Nobody',
    'last_reply' => 'Last answer',
    'created' => 'Created',
    'details' => 'Details',
    'manage' => 'Manage',
    'other_tickets' => 'More tickets of this user',

    'team' => 'Team',
    'internal_note' => 'Internal note',
    'deleted_user' => 'Deleted user',

    'reply' => 'Answer',
    'send' => 'Send',
    'internal' => 'Internal note',
    'internal_help' => 'Only the team sees internal notes. They do not change the status and send no email.',
    'close_after' => 'Close the ticket after sending',

    'delete' => 'Delete Ticket',
    'delete_text' => 'Deletes the ticket with all messages and images. This cannot be undone.',
    'delete_confirm' => 'Really delete this ticket?',

    'editor' => [
        'bold' => 'Bold',
        'italic' => 'Italic',
        'code' => 'Code',
        'quote' => 'Quote',
        'list' => 'List',
        'link' => 'Link',
        'image' => 'Upload image',
        'write' => 'Write',
        'preview' => 'Preview',
        'placeholder' => 'Write your answer… Markdown works.',
        'hint' => 'Markdown is supported. Add images (PNG, JPG, GIF, WebP, up to :size MB) with the button, by pasting or by dragging them in.',
        'uploading' => 'Uploading',
        'too_large' => 'The image is larger than :size MB.',
        'failed' => 'The image could not be uploaded.',
        'empty' => 'Nothing to preview.',
    ],

    'alerts' => [
        'replied' => 'Your answer has been sent.',
        'note_added' => 'The internal note has been saved.',
        'updated' => 'The ticket has been updated.',
        'deleted' => 'Ticket #:id has been deleted.',
    ],
];
