<?php

/*
 * Hydra: plugin manager (addon "plugins").
 */
return [
    'sources' => [
        'modrinth' => 'Modrinth',
        'hangar' => 'Hangar',
        'spigot' => 'SpigotMC',
    ],
    'errors' => [
        'unreachable' => ':source cannot be reached right now. Please try again later.',
        'not_found' => 'This plugin was not found.',
        'no_version' => 'There is no version of this plugin for the chosen software and Minecraft version.',
        'external' => 'This plugin is hosted on another website and cannot be installed automatically. Open its page to download it.',
        'premium' => 'This is a premium plugin. Buy and download it on SpigotMC and upload it in the file manager.',
        'unsupported' => ':source has no plugins for this software.',
        'unknown_source' => 'This source is not available.',
        'unknown_software' => 'This server software is not supported.',
        'no_folder' => 'This server has no "plugins" folder.',
        'download_failed' => 'The node could not download the plugin. Check that remote downloads are allowed and try again.',
        'file_missing' => 'This plugin file does not exist (anymore).',
    ],
];
