<?php

return [
    'overview' => [
        'title' => 'Kontoübersicht',
        'two_factor_required_title' => 'Zwei-Faktor-Anmeldung erforderlich',
        'two_factor_required' => 'Für dein Konto muss die Zwei-Faktor-Anmeldung aktiviert sein, bevor du fortfahren kannst.',
    ],

    'language' => [
        'title' => 'Sprache',
        'label' => 'Sprache des Panels',
        'description' => 'Gilt für das Panel und für E-Mails an dich.',
        'updated' => 'Deine Sprache wurde geändert.',
    ],

    'password' => [
        'title' => 'Passwort ändern',
        'current' => 'Aktuelles Passwort',
        'new' => 'Neues Passwort',
        'new_help' => 'Dein neues Passwort sollte mindestens 8 Zeichen lang sein und nur hier verwendet werden.',
        'confirm' => 'Neues Passwort bestätigen',
        'submit' => 'Passwort ändern',
        'current_required' => 'Bitte gib dein aktuelles Passwort ein.',
        'mismatch' => 'Die Bestätigung stimmt nicht mit dem neuen Passwort überein.',
    ],

    'email' => [
        'title' => 'E-Mail-Adresse ändern',
        'email' => 'E-Mail',
        'confirm_password' => 'Passwort bestätigen',
        'submit' => 'E-Mail ändern',
        'updated' => 'Deine E-Mail-Adresse wurde geändert.',
        'password_required' => 'Bitte gib dein aktuelles Passwort ein.',
    ],

    'two_factor' => [
        'title' => 'Zwei-Faktor-Anmeldung',
        'enabled_text' => 'Die Zwei-Faktor-Anmeldung ist für dein Konto aktiv.',
        'disabled_text' => 'Die Zwei-Faktor-Anmeldung ist für dein Konto noch nicht aktiv. Klicke auf die Schaltfläche, um sie einzurichten.',
        'enable_button' => 'Aktivieren',
        'disable_button' => 'Deaktivieren',
        'enable_title' => 'Zwei-Faktor-Anmeldung aktivieren',
        'enable_description' => 'Schütze dein Konto vor unbefugtem Zugriff. Bei jeder Anmeldung wird ein Bestätigungscode abgefragt.',
        'scan_help' => 'Scanne den QR-Code mit einer Authenticator-App deiner Wahl und gib dann den 6-stelligen Code unten ein.',
        'account_password' => 'Passwort deines Kontos',
        'waiting_qr' => 'QR-Code wird geladen …',
        'enter_code_and_password' => 'Gib den 6-stelligen Code und dein Passwort ein, um fortzufahren.',
        'enable' => 'Aktivieren',
        'disable_title' => 'Zwei-Faktor-Anmeldung deaktivieren',
        'disable_description' => 'Ohne Zwei-Faktor-Anmeldung ist dein Konto weniger gut geschützt.',
        'password' => 'Passwort',
        'enter_password' => 'Gib dein Passwort ein, um fortzufahren.',
        'disable' => 'Deaktivieren',
        'enabled_title' => 'Zwei-Faktor-Anmeldung aktiviert',
        'recovery_description' => 'Bewahre die folgenden Codes sicher auf. Verlierst du dein Telefon, kannst du dich mit diesen Wiederherstellungscodes anmelden.',
        'recovery_warning' => 'Diese Codes werden nicht noch einmal angezeigt.',
        'done' => 'Fertig',
    ],

    'api' => [
        'title' => 'API-Zugangsdaten',
        'create_title' => 'API-Schlüssel erstellen',
        'keys_title' => 'API-Schlüssel',
        'delete_title' => 'API-Schlüssel löschen',
        'delete_confirm' => 'Schlüssel löschen',
        'delete_text' => 'Alle Anfragen mit dem Schlüssel <code>:identifier</code> werden ungültig.',
        'empty' => 'Für dieses Konto gibt es keine API-Schlüssel.',
        'last_used' => 'Zuletzt verwendet: :date',
        'never' => 'Nie',
        'description' => 'Beschreibung',
        'description_help' => 'Eine Beschreibung für diesen API-Schlüssel.',
        'allowed_ips' => 'Erlaubte IP-Adressen',
        'allowed_ips_help' => 'Leer lassen, um jede IP-Adresse zu erlauben. Sonst jede erlaubte IP-Adresse in eine eigene Zeile schreiben.',
        'create' => 'Erstellen',
        'your_key' => 'Dein API-Schlüssel',
        'your_key_help' => 'Hier ist dein neuer API-Schlüssel. Bewahre ihn sicher auf, er wird nicht noch einmal angezeigt.',
    ],

    'ssh' => [
        'title' => 'SSH-Schlüssel',
        'add_title' => 'SSH-Schlüssel hinzufügen',
        'keys_title' => 'SSH-Schlüssel',
        'empty' => 'Für dieses Konto gibt es keine SSH-Schlüssel.',
        'added_on' => 'Hinzugefügt am: :date',
        'name' => 'Name des Schlüssels',
        'public_key' => 'Öffentlicher Schlüssel',
        'public_key_help' => 'Füge deinen öffentlichen SSH-Schlüssel ein.',
        'delete_title' => 'SSH-Schlüssel löschen',
        'delete_confirm' => 'Schlüssel löschen',
        'delete_text' => 'Nach dem Entfernen kann der SSH-Schlüssel <code>:name</code> im Panel nicht mehr verwendet werden.',
    ],

    'activity' => [
        'title' => 'Kontoaktivität',
        'clear_filters' => 'Filter entfernen',
    ],
];
