<?php

return [
    'title' => 'Systemstatus',
    'overall' => [
        'online' => 'Alle Systeme laufen',
        'offline' => 'Bei mindestens einem System gibt es eine Störung',
        'unknown' => 'Der Status wird noch ermittelt',
    ],
    'groups' => [
        'nodes' => 'Nodes',
        'services' => 'Anwendungen',
    ],
    'states' => [
        'online' => 'Online',
        'offline' => 'Offline',
        'unknown' => 'Unbekannt',
    ],
    'legend' => [
        'online' => 'Durchgehend online',
        'partial' => 'Zeitweise offline',
        'offline' => 'Die ganze Stunde offline',
        'unknown' => 'Keine Daten',
    ],
    'uptime' => ':percent % online',
    'no_data' => 'Keine Daten',
    'seven_days_ago' => 'Vor 7 Tagen',
    'now' => 'Jetzt',
    'hour_format' => 'd.m. H:00',
];
