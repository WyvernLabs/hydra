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
            'username-changed' => 'Benutzername von :old in :new geändert',
            // Hydra: Verwarnungen, Sperren und das Autodelete-Addon.
            'warned' => 'Konto verwarnt: :reason',
            'banned' => 'Konto gesperrt: :reason',
            'unbanned' => 'Sperre aufgehoben',
            'ban-expired' => 'Sperre abgelaufen',
            'auto-deleted' => 'Konto :username automatisch gelöscht',
        ],
        // Hydra: Ressourcenpool.
        'pool' => [
            'server-created' => 'Server :name aus dem Ressourcenpool erstellt',
            'server-updated' => 'Ressourcen von Server :name geändert',
            'server-deleted' => 'Server :name aus dem Ressourcenpool gelöscht',
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
        // Hydra: autodelete addon.
        'auto-deleted' => 'Server :name automatisch gelöscht',
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
            'import' => 'Backup :name vom eigenen SFTP-Server übernommen',
            'sftp-update' => 'SFTP-Server :host für Backups eingerichtet',
            'sftp-delete' => 'SFTP-Server :host für Backups entfernt',
        ],
        'database' => [
            'create' => 'Neue Datenbank :name angelegt',
            'rotate-password' => 'Passwort für Datenbank :name erneuert',
            'delete' => 'Datenbank :name gelöscht',
            'query' => 'SQL-Befehl in Datenbank :name ausgeführt',
            'import' => ':file in Datenbank :name importiert',
            'export' => 'Datenbank :name exportiert',
            'phpmyadmin' => 'Datenbank :name in phpMyAdmin geöffnet',
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
            // Hydra: Papierkorb des Dateimanagers.
            'trash_one' => ':directory:files.0 in den Papierkorb verschoben',
            'trash_other' => ':count Dateien in :directory in den Papierkorb verschoben',
            'trash-restore_one' => ':files.0 aus dem Papierkorb wiederhergestellt',
            'trash-restore_other' => ':count Dateien aus dem Papierkorb wiederhergestellt',
            'trash-delete_one' => ':files.0 endgültig aus dem Papierkorb gelöscht',
            'trash-delete_other' => ':count Dateien endgültig aus dem Papierkorb gelöscht',
            'trash-empty' => 'Papierkorb geleert',
        ],
        'minecraft' => [
            'install' => ':software :version (Build :build) installiert',
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
        'role' => [
            'create' => 'Rolle :name angelegt',
            'update' => 'Rolle :name geändert',
            'delete' => 'Rolle :name gelöscht',
        ],
        'import' => [
            'start' => 'Import von :host (:source) nach :target gestartet (Modus: :mode)',
        ],
        'game' => [
            'switch' => 'Spiel von :from zu :to gewechselt (Dateien behalten: :kept)',
            'load' => 'Spielprofil :name geladen (Dateien behalten: :kept)',
            'delete' => 'Spielprofil :name gelöscht',
        ],
        'subdomain' => [
            'create' => 'Subdomain :name angelegt',
            'delete' => 'Subdomain :name gelöscht',
        ],
        'proxy' => [
            'create' => 'Reverse Proxy für :domain auf :allocation angelegt',
            'delete' => 'Reverse Proxy für :domain gelöscht',
        ],
        'schedule' => [
            'create' => 'Zeitplan :name erstellt',
            'update' => 'Zeitplan :name aktualisiert',
            'execute' => 'Zeitplan :name manuell ausgeführt',
            'delete' => 'Zeitplan :name gelöscht',
            'triggered' => 'Zeitplan :name wurde vom Server gestartet (:reason)',
        ],
        'task' => [
            'create' => 'Neue Aufgabe ":action" für den Zeitplan :name erstellt',
            'update' => 'Aufgabe ":action" im Zeitplan :name aktualisiert',
            'delete' => 'Aufgabe aus dem Zeitplan :name gelöscht',
        ],
        'settings' => [
            'rename' => 'Server von :old in :new umbenannt',
            'description' => 'Serverbeschreibung von :old in :new geändert',
            'lockdown-enable' => 'Server in den Lockdown versetzt',
            'lockdown-disable' => 'Lockdown des Servers aufgehoben',
        ],
        'console' => [
            'mclogs' => ':file zu mclo.gs hochgeladen: :url',
        ],
        'startup' => [
            'command' => 'Startbefehl von ":old" in ":new" geändert',
            'done' => 'Meldungen, die den Server als gestartet markieren, von ":old" in ":new" geändert',
            'edit' => 'Variable :variable von ":old" in ":new" geändert',
            'image' => 'Docker-Image des Servers von :old auf :new geändert',
        ],
        'subuser' => [
            'create' => ':username als Unterbenutzer hinzugefügt',
            'update' => 'Berechtigungen des Unterbenutzers :username aktualisiert',
            'delete' => ':username als Unterbenutzer entfernt',
        ],
    ],
];
