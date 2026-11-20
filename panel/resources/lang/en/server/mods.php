<?php

/*
 * Hydra: mod manager (addon "mods").
 */
return [
    'sources' => [
        'modrinth' => 'Modrinth',
        'curseforge' => 'CurseForge',
    ],
    'errors' => [
        'unreachable' => ':source cannot be reached right now. Please try again later.',
        'key_invalid' => ':source refused the API key. Ask an administrator to check it.',
        'search_forbidden' => ':source does not allow searching with the API key of this panel. Ask an administrator to check the access of the key.',
        'not_found' => 'This mod was not found.',
        'no_version' => 'There is no version of this mod for the chosen mod loader and Minecraft version.',
        'external' => 'The author only allows downloads on CurseForge. Open its page to download it and upload it in the file manager.',
        'unknown_source' => 'This source is not available.',
        'unknown_loader' => 'This mod loader is not supported.',
        'no_folder' => 'This server has no "mods" folder.',
        'download_failed' => 'The node could not download the mod. Check that remote downloads are allowed and try again.',
        'file_missing' => 'This mod file does not exist (anymore).',
    ],
];
