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
        'unreachable' => ':source ist gerade nicht erreichbar. Bitte versuche es später erneut.',
        'not_found' => 'Dieses Plugin wurde nicht gefunden.',
        'no_version' => 'Für die gewählte Software und Minecraft-Version gibt es keine Version dieses Plugins.',
        'external' => 'Dieses Plugin liegt auf einer anderen Website und kann nicht automatisch installiert werden. Öffne seine Seite, um es herunterzuladen.',
        'premium' => 'Das ist ein Premium-Plugin. Kaufe es auf SpigotMC, lade es dort herunter und lade es im Dateimanager hoch.',
        'unsupported' => ':source hat keine Plugins für diese Software.',
        'unknown_source' => 'Diese Quelle ist nicht verfügbar.',
        'unknown_software' => 'Diese Server-Software wird nicht unterstützt.',
        'no_folder' => 'Dieser Server hat keinen „plugins“-Ordner.',
        'download_failed' => 'Der Node konnte das Plugin nicht herunterladen. Prüfe, ob Downloads aus dem Internet erlaubt sind, und versuche es erneut.',
        'file_missing' => 'Diese Plugin-Datei gibt es nicht (mehr).',
    ],
];
