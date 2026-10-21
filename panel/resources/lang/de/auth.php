<?php

return [
    'sign_in' => 'Anmelden',
    'go_to_login' => 'Zur Anmeldung',
    'failed' => 'Mit diesen Zugangsdaten wurde kein Konto gefunden.',
    // Hydra: gesperrte Konten.
    'banned' => 'Dieses Konto wurde gesperrt. Grund: :reason',
    'banned_until' => 'Dieses Konto ist bis :date gesperrt. Grund: :reason',
    'banned_notice' => 'Du wurdest abgemeldet, weil dieses Konto gesperrt wurde.',

    'forgot_password' => [
        'label' => 'Passwort vergessen?',
        'label_help' => 'Gib die E-Mail-Adresse deines Kontos ein, um eine Anleitung zum Zurücksetzen deines Passworts zu erhalten.',
        'button' => 'Konto wiederherstellen',
    ],

    'reset_password' => [
        'button' => 'Zurücksetzen und anmelden',
    ],

    'two_factor' => [
        'label' => 'Zwei-Faktor-Code',
        'label_help' => 'Für dieses Konto ist eine zweite Sicherheitsstufe aktiv. Gib den Code ein, den dein Gerät erzeugt, um die Anmeldung abzuschließen.',
        'checkpoint_failed' => 'Der Zwei-Faktor-Code war ungültig.',
    ],

    'throttle' => 'Zu viele Anmeldeversuche. Bitte versuche es in :seconds Sekunden erneut.',
    'password_requirements' => 'Das Passwort muss mindestens 8 Zeichen lang sein und sollte nur für diese Seite verwendet werden.',
    '2fa_must_be_enabled' => 'Der Administrator verlangt, dass für dein Konto die Zwei-Faktor-Anmeldung aktiviert ist, um das Panel zu nutzen.',

    'captcha_failed' => 'Die Captcha-Prüfung ist fehlgeschlagen. Bitte versuche es erneut.',

    'register' => [
        'email_taken' => 'Mit dieser E-Mail-Adresse gibt es bereits ein Konto.',
        'domain_not_allowed' => 'Mit E-Mail-Adressen dieser Domain können keine Konten angelegt werden.',
        'wait' => 'Bitte warte :seconds Sekunden, bevor du einen neuen Code anforderst.',
        'code_invalid' => 'Der Code ist nicht richtig.',
        'code_expired' => 'Der Code ist abgelaufen. Bitte fordere einen neuen an.',
        'too_many_attempts' => 'Der Code wurde zu oft falsch eingegeben. Bitte fordere einen neuen an.',
        'not_verified' => 'Bitte bestätige zuerst deine E-Mail-Adresse.',
    ],
];
