<?php

/*
 * Hydra: Modmanager (Addon "mods").
 */
return [
    'sources' => [
        'modrinth' => 'Modrinth',
        'curseforge' => 'CurseForge',
    ],
    'errors' => [
        'unreachable' => ':source ist gerade nicht erreichbar. Bitte versuche es später noch einmal.',
        'key_invalid' => ':source hat den API-Key abgelehnt. Bitte einen Administrator, ihn zu prüfen.',
        'search_forbidden' => ':source erlaubt mit dem API-Key dieses Panels keine Suche. Bitte einen Administrator, die Freigaben des Keys zu prüfen.',
        'not_found' => 'Diese Mod wurde nicht gefunden.',
        'no_version' => 'Es gibt keine Version dieser Mod für den gewählten Mod-Loader und die Minecraft-Version.',
        'external' => 'Der Autor erlaubt Downloads nur auf CurseForge. Öffne die Seite, lade die Mod dort herunter und lade sie im Dateimanager hoch.',
        'unknown_source' => 'Diese Quelle ist nicht verfügbar.',
        'unknown_loader' => 'Dieser Mod-Loader wird nicht unterstützt.',
        'no_folder' => 'Dieser Server hat keinen „mods“-Ordner.',
        'download_failed' => 'Der Node konnte die Mod nicht herunterladen. Prüfe, ob Downloads aus dem Internet erlaubt sind, und versuche es erneut.',
        'file_missing' => 'Diese Mod-Datei gibt es nicht (mehr).',
    ],
];
