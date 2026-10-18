<?php

/*
 * E-Mails des Panels. Sie werden in der Sprache des Empfängers verschickt
 * (User::preferredLocale()).
 */
return [
    'hello' => 'Hallo!',
    'hello_name' => 'Hallo :name!',
    'whoops' => 'Hoppla!',
    'regards' => 'Viele Grüße,',
    'trouble' => 'Falls die Schaltfläche „:action“ nicht funktioniert, kopiere diese Adresse in deinen Browser:',
    'rights' => 'Alle Rechte vorbehalten.',

    'account_created' => [
        'subject' => 'Dein Konto bei :app',
        'intro' => 'Für dich wurde ein Konto bei :app angelegt.',
        'username' => 'Benutzername: :username',
        'email' => 'E-Mail: :email',
        'action' => 'Konto einrichten',
    ],
    'added_to_server' => [
        'subject' => 'Du wurdest zu einem Server hinzugefügt',
        'intro' => 'Du wurdest als Unterbenutzer zu diesem Server hinzugefügt und kannst ihn jetzt teilweise verwalten.',
        'server' => 'Server: :name',
        'action' => 'Server öffnen',
    ],
    'removed_from_server' => [
        'subject' => 'Du wurdest von einem Server entfernt',
        'intro' => 'Du wurdest als Unterbenutzer von diesem Server entfernt.',
        'server' => 'Server: :name',
        'action' => 'Panel öffnen',
    ],
    'server_installed' => [
        'subject' => 'Dein Server ist bereit',
        'intro' => 'Dein Server ist fertig installiert und kann jetzt genutzt werden.',
        'server' => 'Server: :name',
        'action' => 'Anmelden und loslegen',
    ],
    'password_reset' => [
        'subject' => 'Passwort zurücksetzen',
        'intro' => 'Du erhältst diese E-Mail, weil für dein Konto das Zurücksetzen des Passworts angefordert wurde.',
        'action' => 'Passwort zurücksetzen',
        'outro' => 'Wenn du das nicht angefordert hast, musst du nichts weiter tun.',
    ],
    'test' => [
        'subject' => 'Testnachricht von :app',
        'line' => 'Das ist ein Test des E-Mail-Versands von :app. Alles funktioniert!',
    ],
];
