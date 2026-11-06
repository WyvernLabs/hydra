<?php

return [
    'title' => 'Status page: applications',
    'subtitle' => 'Own applications next to the nodes on the status page.',
    'list' => 'Applications',
    'none' => 'There are no applications yet. The nodes are on the status page automatically.',
    'open_page' => 'Open status page',
    'add' => 'Add application',
    'edit' => 'Edit application',
    'name' => 'Name',
    'type' => 'Check',
    'types' => [
        'http' => 'Website (HTTP/HTTPS)',
        'tcp' => 'Port (TCP)',
    ],
    'type_help' => 'Website: online as long as it answers, even with a login prompt; server errors (500 and up) count as offline. Port: online when the port accepts a connection, e.g. for databases or game servers.',
    'target' => 'Target',
    'target_help' => 'An address like https://example.com or host:port like db.example.com:3306. The target is not public, only the name is.',
    'target_invalid_http' => 'Enter an address starting with http:// or https://.',
    'target_invalid_tcp' => 'Enter the target as host:port, e.g. db.example.com:3306.',
    'state' => 'State',
    'sort' => 'Order',
    'enabled' => 'Check and show on the status page',
    'saved' => '":name" was saved.',
    'deleted' => '":name" was deleted.',
];
