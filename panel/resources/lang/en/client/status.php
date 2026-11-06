<?php

return [
    'title' => 'System status',
    'overall' => [
        'online' => 'All systems are running',
        'offline' => 'At least one system has a problem',
        'unknown' => 'The status is still being determined',
    ],
    'groups' => [
        'nodes' => 'Nodes',
        'services' => 'Applications',
    ],
    'states' => [
        'online' => 'Online',
        'offline' => 'Offline',
        'unknown' => 'Unknown',
    ],
    'legend' => [
        'online' => 'Online throughout',
        'partial' => 'Offline for a while',
        'offline' => 'Offline for the whole hour',
        'unknown' => 'No data',
    ],
    'uptime' => ':percent % online',
    'no_data' => 'No data',
    'seven_days_ago' => '7 days ago',
    'now' => 'Now',
    'hour_format' => 'm/d H:00',
];
