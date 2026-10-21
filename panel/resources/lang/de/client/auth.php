<?php

return [
    'return_to_login' => 'Zurück zur Anmeldung',

    'login' => [
        'title' => 'Anmelden',
        // Hydra: nach einer Sperre abgemeldet.
        'banned_notice' => 'Du wurdest abgemeldet, weil dieses Konto gesperrt wurde. Beim Anmelden siehst du den Grund.',
        'username' => 'Benutzername',
        'password' => 'Passwort',
        'submit' => 'Anmelden',
        'forgot_password' => 'Passwort vergessen?',
        'username_required' => 'Bitte gib deinen Benutzernamen ein.',
        'password_required' => 'Bitte gib dein Passwort ein.',
        'no_account' => 'Noch kein Konto?',
        'register' => 'Registrieren',
    ],

    'checkpoint' => [
        'title' => 'Geräteprüfung',
        'recovery_code' => 'Wiederherstellungscode',
        'authentication_code' => 'Bestätigungscode',
        'recovery_help' => 'Gib einen der Wiederherstellungscodes ein, die du beim Einrichten der Zwei-Faktor-Anmeldung erhalten hast.',
        'code_help' => 'Gib den Zwei-Faktor-Code ein, den dein Gerät anzeigt.',
        'continue' => 'Weiter',
        'lost_device' => 'Ich habe mein Gerät verloren',
        'have_device' => 'Ich habe mein Gerät',
    ],

    'forgot' => [
        'title' => 'Passwort zurücksetzen',
        'email' => 'E-Mail',
        'email_help' => 'Gib die E-Mail-Adresse deines Kontos ein. Du erhältst dann eine Anleitung zum Zurücksetzen deines Passworts.',
        'submit' => 'E-Mail senden',
        'email_invalid' => 'Bitte gib eine gültige E-Mail-Adresse ein.',
    ],

    'reset' => [
        'title' => 'Neues Passwort festlegen',
        'email' => 'E-Mail',
        'new_password' => 'Neues Passwort',
        'new_password_help' => 'Passwörter müssen mindestens 8 Zeichen lang sein.',
        'confirm_password' => 'Neues Passwort bestätigen',
        'submit' => 'Passwort zurücksetzen',
        'password_required' => 'Bitte gib ein neues Passwort ein.',
        'password_min' => 'Dein neues Passwort muss mindestens 8 Zeichen lang sein.',
        'password_mismatch' => 'Die Passwörter stimmen nicht überein.',
    ],

    'captcha' => [
        'required' => 'Bitte schließe zuerst die Captcha-Prüfung ab.',
    ],

    'register' => [
        'title' => 'Konto erstellen',
        'email' => 'E-Mail',
        'email_help' => 'Wir schicken dir einen sechsstelligen Code, um deine Adresse zu bestätigen.',
        'email_invalid' => 'Bitte gib eine gültige E-Mail-Adresse ein.',
        'send_code' => 'Code senden',
        'code_title' => 'E-Mail bestätigen',
        'code' => 'Code',
        'code_help' => 'Gib den sechsstelligen Code ein, den wir an :email geschickt haben.',
        'code_invalid' => 'Der Code besteht aus sechs Ziffern.',
        'verify' => 'Bestätigen',
        'resend' => 'Neuen Code senden',
        'code_resent' => 'Ein neuer Code wurde verschickt.',
        'change_email' => 'Andere E-Mail verwenden',
        'details_title' => 'Dein Konto',
        'details_help' => 'Deine E-Mail-Adresse :email ist bestätigt. Wähle jetzt Namen, Benutzernamen und Passwort.',
        'name_first' => 'Vorname',
        'name_last' => 'Nachname',
        'username' => 'Benutzername',
        'username_help' => 'Damit meldest du dich an. Buchstaben, Ziffern, Bindestriche, Unterstriche und Punkte, mindestens 3 Zeichen.',
        'username_invalid' => 'Der Benutzername muss mit einem Buchstaben oder einer Ziffer beginnen und enden und darf nur Buchstaben, Ziffern, Bindestriche, Unterstriche und Punkte enthalten.',
        'password' => 'Passwort',
        'password_help' => 'Mindestens 8 Zeichen.',
        'confirm_password' => 'Passwort bestätigen',
        'submit' => 'Konto erstellen',
        'required' => 'Dieses Feld ist erforderlich.',
        'password_min' => 'Das Passwort muss mindestens 8 Zeichen lang sein.',
        'password_mismatch' => 'Die Passwörter stimmen nicht überein.',
        'have_account' => 'Schon ein Konto? Anmelden',
    ],
];
