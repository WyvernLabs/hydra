<?php

/*
 * Hydra: support tickets in the account (tickets addon).
 */
return [
    'title' => 'Tickets',
    'new' => 'New ticket',
    'new_title' => 'Open a ticket',
    'empty' => 'You have no tickets here.',
    'filter' => [
        'open' => 'Open',
        'closed' => 'Closed',
        'all' => 'All',
    ],

    'subject' => 'Subject',
    'subject_placeholder' => 'What is it about?',
    'language' => 'Language',
    'category' => 'Category',
    'priority' => 'Priority',
    'server' => 'Server',
    'no_server' => 'No specific server',
    'message' => 'Message',
    'create' => 'Open ticket',
    'last_reply' => 'Last answer :time',
    'opened' => 'Opened :time',
    'back' => 'Back to your tickets',

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

    'team' => 'Team',
    'deleted_user' => 'Deleted user',
    'reply' => 'Answer',
    'send' => 'Send',
    'close' => 'Close ticket',
    'close_confirm_title' => 'Close ticket?',
    'close_confirm_text' => 'You can still answer later, which opens the ticket again.',
    'closed_notice' => 'This ticket is closed. Answering opens it again.',

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
        'placeholder' => 'Describe your request… Markdown works.',
        'hint' => 'Markdown is supported. Add images (PNG, JPG, GIF, WebP, up to :size MB) with the button, by pasting or by dragging them in.',
        'uploading' => 'Uploading',
        'too_large' => 'The image is larger than :size MB.',
        'failed' => 'The image could not be uploaded.',
        'empty' => 'Nothing to preview.',
    ],

    'errors' => [
        'max_open' => 'You already have :count open tickets. Please wait for them to be answered or close one first.',
    ],
];
