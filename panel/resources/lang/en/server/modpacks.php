<?php

/*
 * Hydra: modpack installer (addon "modpacks").
 */
return [
    'errors' => [
        'not_found' => 'This modpack was not found.',
        'no_version' => 'This version of the modpack cannot be installed.',
        'invalid_pack' => 'The file of this modpack version could not be read.',
        'download_failed' => 'The modpack could not be downloaded. Please try again later.',
        'unknown_loader' => 'This modpack needs a mod loader the panel does not support.',
        'no_server' => 'There is no server software :loader for Minecraft :version.',
        'busy' => 'The server is busy (e.g. installing or transferring). Try again when it is done.',
        'running' => 'A modpack is being installed on this server already.',
        'no_profiles' => 'Game profiles are not available.',
        'failed' => 'The installation failed. Details are in the panel log.',
        'stalled' => 'The installation stopped without finishing (e.g. because the panel was restarted). Please start it again.',
    ],
];
