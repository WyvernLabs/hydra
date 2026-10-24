<?php

/*
 * Titles and labels of the subuser permissions (Pterodactyl\Models\Permission).
 * The English descriptions live in the model itself and are used as fallback.
 */
return [
    'websocket' => ['title' => 'Websocket', 'labels' => ['connect' => 'Connect']],
    'control' => [
        'title' => 'Control',
        'labels' => ['console' => 'Console', 'start' => 'Start', 'stop' => 'Stop', 'restart' => 'Restart'],
    ],
    'user' => [
        'title' => 'Subusers',
        'labels' => ['create' => 'Create', 'read' => 'View', 'update' => 'Edit', 'delete' => 'Delete'],
    ],
    'file' => [
        'title' => 'Files',
        'labels' => [
            'create' => 'Create',
            'read' => 'View',
            'read-content' => 'Read Content',
            'update' => 'Edit',
            'delete' => 'Delete',
            'archive' => 'Archives',
            'sftp' => 'SFTP',
        ],
    ],
    'backup' => [
        'title' => 'Backups',
        'labels' => ['create' => 'Create', 'read' => 'View', 'delete' => 'Delete', 'download' => 'Download', 'restore' => 'Restore', 'sftp' => 'Own SFTP Server'],
    ],
    'allocation' => [
        'title' => 'Network',
        'labels' => ['read' => 'View', 'create' => 'Add', 'update' => 'Edit', 'delete' => 'Delete'],
    ],
    'startup' => [
        'title' => 'Startup',
        'labels' => ['read' => 'View', 'update' => 'Edit', 'docker-image' => 'Docker Image'],
    ],
    'database' => [
        'title' => 'Databases',
        'labels' => ['create' => 'Create', 'read' => 'View', 'update' => 'Edit', 'delete' => 'Delete', 'view_password' => 'View Password', 'browse' => 'View Data', 'edit' => 'Edit Data', 'import' => 'Import', 'export' => 'Export', 'phpmyadmin' => 'phpMyAdmin'],
    ],
    'schedule' => [
        'title' => 'Schedules',
        'labels' => ['create' => 'Create', 'read' => 'View', 'update' => 'Edit', 'delete' => 'Delete'],
    ],
    'settings' => [
        'title' => 'Settings',
        'labels' => ['rename' => 'Rename', 'reinstall' => 'Reinstall', 'game' => 'Switch Game', 'version' => 'Change Minecraft Version'],
    ],
    'activity' => ['title' => 'Activity', 'labels' => ['read' => 'View']],
];
