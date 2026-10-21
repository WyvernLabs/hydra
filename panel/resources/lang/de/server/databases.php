<?php

// Hydra: Meldungen der Datenbankverwaltung, des Imports und des Exports im Kundenbereich.
return [
    'errors' => [
        'connection' => 'Keine Verbindung zur Datenbank möglich: :message',
        'table_missing' => 'Die Tabelle „:table“ gibt es nicht.',
        'unknown_column' => 'Die Spalte „:column“ gibt es nicht oder sie kann hier nicht geändert werden.',
        'no_primary_key' => 'Diese Tabelle hat keinen Primärschlüssel, ihre Zeilen können hier nicht geändert werden. Nutze stattdessen die SQL-Konsole.',
        'row_missing' => 'Die Zeile gibt es nicht mehr. Lade die Tabelle neu.',
        'tool_missing' => 'Auf dem Panelserver fehlen die MariaDB-Client-Programme. Führe dort „hydra-panel update“ aus.',
        'import_read' => 'Die hochgeladene Datei konnte nicht gelesen werden.',
        'import_failed' => 'Der Import ist fehlgeschlagen: :message',
        'phpmyadmin' => 'phpMyAdmin ist nicht verfügbar.',
    ],
];
