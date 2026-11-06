<?php

/*
 * Hydra: messages of the Discord bot and errors while linking.
 */
return [
    'code_message' => "**:panel**: Your code to link the panel account **:username** is:\n\n`:code`\n\nIt is valid for :minutes minutes. Only enter it in the panel and do not share it. If this was not you, you can ignore this message.",
    'ticket_message' => "**:panel**: New answer in your ticket \":subject\".\n:url",
    'errors' => [
        'taken' => 'This Discord account is already linked to another panel account.',
        'already_linked' => 'Your account is already linked to Discord. Disconnect it first.',
        'cooldown' => 'A code was just sent to this Discord account. Try again in :seconds seconds.',
        'dm_closed' => 'The bot cannot message you. Join the Discord server and allow direct messages from server members.',
        'unknown_user' => 'There is no Discord account with this ID. Check your user ID.',
        'unavailable' => 'Discord cannot be reached right now. Try again later.',
        'code_expired' => 'The code has expired. Request a new one.',
        'code_wrong' => 'The code is not correct.',
        'too_many_attempts' => 'Too many wrong attempts. Request a new code.',
    ],
];
