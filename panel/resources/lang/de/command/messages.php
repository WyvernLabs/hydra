<?php

return [
    'location' => [
        'no_location_found' => 'Kein Standort mit diesem Kürzel gefunden.',
        'ask_short' => 'Kürzel des Standorts',
        'ask_long' => 'Beschreibung des Standorts',
        'created' => 'Neuer Standort (:name) mit der ID :id erstellt.',
        'deleted' => 'Der Standort wurde gelöscht.',
    ],
    'user' => [
        'search_users' => 'Benutzername, Benutzer-ID oder E-Mail-Adresse eingeben',
        'select_search_user' => 'ID des zu löschenden Benutzers (\'0\' für eine neue Suche)',
        'deleted' => 'Der Benutzer wurde aus dem Panel gelöscht.',
        'confirm_delete' => 'Soll dieser Benutzer wirklich aus dem Panel gelöscht werden?',
        'no_users_found' => 'Für diesen Suchbegriff wurden keine Benutzer gefunden.',
        'multiple_found' => 'Es wurden mehrere Konten gefunden. Wegen --no-interaction kann kein Benutzer gelöscht werden.',
        'ask_admin' => 'Ist dieser Benutzer Administrator?',
        'ask_email' => 'E-Mail-Adresse',
        'ask_username' => 'Benutzername',
        'ask_name_first' => 'Vorname',
        'ask_name_last' => 'Nachname',
        'ask_password' => 'Passwort',
        'ask_password_tip' => 'Soll das Konto ein zufälliges Passwort per E-Mail erhalten, brich ab (STRG+C) und starte den Befehl mit `--no-password` neu.',
        'ask_password_help' => 'Passwörter müssen mindestens 8 Zeichen lang sein und mindestens einen Großbuchstaben und eine Zahl enthalten.',
        '2fa_help_text' => [
            'Dieser Befehl deaktiviert die Zwei-Faktor-Anmeldung eines Kontos. Er ist nur zur Wiederherstellung gedacht, wenn sich ein Benutzer ausgesperrt hat.',
            'Wenn du das nicht möchtest, brich mit STRG+C ab.',
        ],
        '2fa_disabled' => 'Die Zwei-Faktor-Anmeldung für :email wurde deaktiviert.',
    ],
    'schedule' => [
        'output_line' => 'Starte die erste Aufgabe von `:schedule` (:hash).',
    ],
    'maintenance' => [
        'deleting_service_backup' => 'Lösche Backup-Datei :file.',
    ],
    'server' => [
        'rebuild_failed' => 'Neuaufbau von ":name" (#:id) auf Node ":node" fehlgeschlagen: :message',
        'reinstall' => [
            'failed' => 'Neuinstallation von ":name" (#:id) auf Node ":node" fehlgeschlagen: :message',
            'confirm' => 'Du bist dabei, mehrere Server neu zu installieren. Fortfahren?',
        ],
        'power' => [
            'confirm' => 'Du bist dabei, :action für :count Server auszuführen. Fortfahren?',
            'action_failed' => 'Energieaktion für ":name" (#:id) auf Node ":node" fehlgeschlagen: :message',
        ],
    ],
    'environment' => [
        'mail' => [
            'ask_smtp_host' => 'SMTP-Server (z. B. smtp.gmail.com)',
            'ask_smtp_port' => 'SMTP-Port',
            'ask_smtp_username' => 'SMTP-Benutzername',
            'ask_smtp_password' => 'SMTP-Passwort',
            'ask_mailgun_domain' => 'Mailgun-Domain',
            'ask_mailgun_endpoint' => 'Mailgun-Endpunkt',
            'ask_mailgun_secret' => 'Mailgun-Schlüssel',
            'ask_mandrill_secret' => 'Mandrill-Schlüssel',
            'ask_postmark_username' => 'Postmark-API-Schlüssel',
            'ask_driver' => 'Welcher Treiber soll zum Versenden von E-Mails verwendet werden?',
            'ask_mail_from' => 'Absenderadresse für E-Mails',
            'ask_mail_name' => 'Absendername für E-Mails',
            'ask_encryption' => 'Verschlüsselungsverfahren',
        ],
    ],
];
