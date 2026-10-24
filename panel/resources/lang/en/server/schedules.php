<?php

/*
 * Hydra: triggers, conditions and actions of schedules.
 */
return [
    'invalid_regex' => 'This is not a valid regular expression.',
    'task_limit' => 'A schedule can have at most :limit tasks.',
    'wait_timeout' => 'The server did not reach the state in time.',
    'webhook_failed' => 'The webhook answered with status :status.',
    'payload' => [
        'state' => 'Choose a server state.',
        'timeout' => 'The maximum wait has to be between 10 and 3600 seconds.',
        'paths' => 'Enter up to 100 files or folders, one per line.',
        'url' => 'The webhook has to be an https address on the internet.',
        'message' => 'Enter a message of up to 2000 characters.',
        'condition' => 'The condition is not complete.',
    ],
];
