<?php

/**
 * Übersetzungen für die Einträge im Aktivitätsprotokoll. Die Schlüssel entsprechen
 * dem Teil vor dem Doppelpunkt (:) im Ereignisnamen.
 */
return [
    'auth' => [
        'fail' => 'Fehlgeschlagene Anmeldung',
        'success' => 'Angemeldet',
        'password-reset' => 'Passwort zurückgesetzt',
        'reset-password' => 'Zurücksetzen des Passworts angefordert',
        'checkpoint' => 'Zwei-Faktor-Anmeldung angefordert',
        'recovery-token' => 'Zwei-Faktor-Wiederherstellungscode verwendet',
        'token' => 'Zwei-Faktor-Abfrage bestanden',
        'ip-blocked' => 'Anfrage von nicht freigegebener IP-Adresse für :identifier blockiert',
        'sftp' => [
            'fail' => 'Fehlgeschlagene SFTP-Anmeldung',
        ],
    ],
    'user' => [
        'user' => [
            'create' => 'Neuen Benutzer :email angelegt',
        ],
        'account' => [
            'email-changed' => 'E-Mail-Adresse von :old in :new geändert',
            'password-changed' => 'Passwort geändert',
        ],
        'api-key' => [
            'create' => 'Neuen API-Schlüssel :identifier erstellt',
            'delete' => 'API-Schlüssel :identifier gelöscht',
        ],
        'ssh-key' => [
            'create' => 'SSH-Schlüssel :fingerprint zum Konto hinzugefügt',
            'delete' => 'SSH-Schlüssel :fingerprint vom Konto entfernt',
        ],
        'two-factor' => [
            'create' => 'Zwei-Faktor-Anmeldung aktiviert',
            'delete' => 'Zwei-Faktor-Anmeldung deaktiviert',
        ],
    ],
    'server' => [
        'reinstall' => 'Server neu installiert',
        'console' => [
            'command' => '":command" auf dem Server ausgeführt',
        ],
        'power' => [
            'start' => 'Server gestartet',
            'stop' => 'Server gestoppt',
            'restart' => 'Server neu gestartet',
            'kill' => 'Serverprozess beendet',
        ],
        'backup' => [
            'download' => 'Backup :name heruntergeladen',
            'delete' => 'Backup :name gelöscht',
            'restore' => 'Backup :name wiederhergestellt (Dateien gelöscht: :truncate)',
            'restore-complete' => 'Wiederherstellung von Backup :name abgeschlossen',
            'restore-failed' => 'Wiederherstellung von Backup :name fehlgeschlagen',
            'start' => 'Neues Backup :name gestartet',
            'complete' => 'Backup :name als abgeschlossen markiert',
            'fail' => 'Backup :name als fehlgeschlagen markiert',
            'lock' => 'Backup :name gesperrt',
            'unlock' => 'Backup :name entsperrt',
        ],
        'database' => [
            'create' => 'Neue Datenbank :name angelegt',
            'rotate-password' => 'Passwort für Datenbank :name erneuert',
            'delete' => 'Datenbank :name gelöscht',
        ],
        'file' => [
            'compress_one' => ':directory:files.0 komprimiert',
            'compress_other' => ':count Dateien in :directory komprimiert',
            'read' => 'Inhalt von :file angesehen',
            'copy' => 'Kopie von :file erstellt',
            'create-directory' => 'Ordner :directory:name erstellt',
            'decompress' => ':files in :directory entpackt',
            'delete_one' => ':directory:files.0 gelöscht',
            'delete_other' => ':count Dateien in :directory gelöscht',
            'download' => ':file heruntergeladen',
            'pull' => 'Externe Datei von :url nach :directory heruntergeladen',
            'rename_one' => ':directory:files.0.from in :directory:files.0.to umbenannt',
            'rename_other' => ':count Dateien in :directory umbenannt',
            'write' => 'Neuen Inhalt in :file geschrieben',
            'upload' => 'Datei-Upload gestartet',
            'uploaded' => ':directory:file hochgeladen',
        ],
        'sftp' => [
            'denied' => 'SFTP-Zugriff wegen fehlender Berechtigung blockiert',
            'create_one' => ':files.0 erstellt',
            'create_other' => ':count neue Dateien erstellt',
            'write_one' => 'Inhalt von :files.0 geändert',
            'write_other' => 'Inhalt von :count Dateien geändert',
            'delete_one' => ':files.0 gelöscht',
            'delete_other' => ':count Dateien gelöscht',
            'create-directory_one' => 'Ordner :files.0 erstellt',
            'create-directory_other' => ':count Ordner erstellt',
            'rename_one' => ':files.0.from in :files.0.to umbenannt',
            'rename_other' => ':count Dateien umbenannt oder verschoben',
        ],
        'allocation' => [
            'create' => ':allocation zum Server hinzugefügt',
            'notes' => 'Notiz für :allocation von ":old" in ":new" geändert',
            'primary' => ':allocation als primäre Adresse des Servers festgelegt',
            'delete' => 'Adresse :allocation gelöscht',
        ],
        'schedule' => [
            'create' => 'Zeitplan :name erstellt',
            'update' => 'Zeitplan :name aktualisiert',
            'execute' => 'Zeitplan :name manuell ausgeführt',
            'delete' => 'Zeitplan :name gelöscht',
        ],
        'task' => [
            'create' => 'Neue Aufgabe ":action" für den Zeitplan :name erstellt',
            'update' => 'Aufgabe ":action" im Zeitplan :name aktualisiert',
            'delete' => 'Aufgabe aus dem Zeitplan :name gelöscht',
        ],
        'settings' => [
            'rename' => 'Server von :old in :new umbenannt',
            'description' => 'Serverbeschreibung von :old in :new geändert',
        ],
        'startup' => [
            'edit' => 'Variable :variable von ":old" in ":new" geändert',
            'image' => 'Docker-Image des Servers von :old auf :new geändert',
        ],
        'subuser' => [
            'create' => ':email als Unterbenutzer hinzugefügt',
            'update' => 'Berechtigungen des Unterbenutzers :email aktualisiert',
            'delete' => ':email als Unterbenutzer entfernt',
        ],
    ],
];
