<?php

/*
 * Hydra: Auslöser, Bedingungen und Aktionen von Zeitplänen.
 */
return [
    'invalid_regex' => 'Das ist kein gültiger regulärer Ausdruck.',
    'task_limit' => 'Ein Zeitplan kann höchstens :limit Aufgaben haben.',
    'wait_timeout' => 'Der Server hat den Zustand nicht rechtzeitig erreicht.',
    'webhook_failed' => 'Der Webhook hat mit Status :status geantwortet.',
    'payload' => [
        'state' => 'Wähle einen Serverzustand.',
        'timeout' => 'Die maximale Wartezeit muss zwischen 10 und 3600 Sekunden liegen.',
        'paths' => 'Gib bis zu 100 Dateien oder Ordner ein, einen pro Zeile.',
        'url' => 'Der Webhook muss eine https-Adresse im Internet sein.',
        'message' => 'Gib eine Nachricht mit bis zu 2000 Zeichen ein.',
        'condition' => 'Die Bedingung ist nicht vollständig.',
    ],
];
