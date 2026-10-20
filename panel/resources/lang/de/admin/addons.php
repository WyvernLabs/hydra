<?php

/*
 * Hydra: die Addons des Panels (Design > Addons).
 */
return [
    'title' => 'Addons',
    'subtitle' => 'Zusätzliche Funktionen des Panels, die einzeln eingeschaltet werden können.',
    'help' => 'Klicke auf eine Box, um die Einstellungen des Addons zu ändern.',
    'active' => 'Aktiv',
    'inactive' => 'Inaktiv',
    'enable' => 'Aktivieren',
    'disable' => 'Deaktivieren',
    'settings' => 'Einstellungen',
    'back' => 'Zurück zu den Addons',
    'enabled_label' => 'Addon aktiviert',
    'saved' => 'Die Einstellungen von „:name“ wurden gespeichert.',
    'saved_not_enabled' => 'Die Einstellungen wurden gespeichert, das Addon konnte aber nicht aktiviert werden: :reason',
    'cannot_enable' => 'Das Addon kann noch nicht aktiviert werden: :reason',
    'enabled' => '„:name“ wurde aktiviert.',
    'disabled' => '„:name“ wurde deaktiviert.',

    'turnstile' => [
        'name' => 'Cloudflare Turnstile',
        'description' => 'Schützt Anmeldung, Passwort-Zurücksetzen und Registrierung mit Cloudflare Turnstile vor Bots – ein Captcha, das meist ohne Klick auskommt.',
        'keys' => 'Schlüssel',
        'keys_help' => 'Lege im <a href=":url" target="_blank" rel="noopener">Cloudflare-Dashboard unter Turnstile</a> ein Widget für die Domain des Panels an und trage seine Schlüssel hier ein.',
        'site_key' => 'Site Key',
        'secret_key' => 'Secret Key',
        'secret_key_help' => 'Nur das Panel nutzt ihn, um bei Cloudflare nachzufragen, ob eine Prüfung bestanden wurde. Wird verschlüsselt gespeichert.',
        'secret_key_set' => 'Gespeichert – leer lassen, um ihn zu behalten',
        'forms_title' => 'Geschützte Formulare',
        'forms' => [
            'login' => 'Anmeldung',
            'password' => 'Passwort zurücksetzen',
            'register' => 'Registrierung',
        ],
        'no_forms' => 'Kein Formular geschützt',
        'missing_keys' => 'Trage zuerst den Site Key und den Secret Key ein.',
    ],

    'registration' => [
        'name' => 'Registrierung',
        'description' => 'Besucher können selbst ein Konto anlegen. Die E-Mail-Adresse wird mit einem sechsstelligen Code bestätigt, danach sind sie direkt angemeldet.',
        'code_lifetime' => 'Gültigkeit des Codes',
        'code_lifetime_help' => 'Minuten, die der per E-Mail gesendete Code gültig ist (5–60).',
        'minutes' => 'Minuten',
        'allowed_domains' => 'Erlaubte E-Mail-Domains',
        'allowed_domains_help' => 'Nur Adressen dieser Domains können sich registrieren, z. B. <code>example.com, example.org</code>. Leer lassen, um jede Domain zu erlauben.',
        'mail_hint' => 'Der Code wird per E-Mail verschickt. Stelle sicher, dass die <a href=":url">E-Mail-Einstellungen</a> funktionieren.',
        'turnstile_hint' => 'Kombiniere es mit dem Addon <a href=":url">Cloudflare Turnstile</a>, damit Bots keine Konten anlegen.',
        'summary_lifetime' => 'Code :minutes Minuten gültig',
        'summary_all_domains' => 'Alle E-Mail-Domains',
        'summary_domains' => 'Nur :domains',
    ],
];
