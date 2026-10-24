<?php

/*
 * Hydra: Support-Tickets im Konto (Addon Tickets).
 */
return [
    'title' => 'Tickets',
    'new' => 'Neues Ticket',
    'new_title' => 'Ticket erstellen',
    'empty' => 'Hier hast du keine Tickets.',
    'filter' => [
        'open' => 'Offen',
        'closed' => 'Geschlossen',
        'all' => 'Alle',
    ],

    'subject' => 'Betreff',
    'subject_placeholder' => 'Worum geht es?',
    'language' => 'Sprache',
    'category' => 'Kategorie',
    'priority' => 'Priorität',
    'server' => 'Server',
    'no_server' => 'Kein bestimmter Server',
    'message' => 'Nachricht',
    'create' => 'Ticket erstellen',
    'last_reply' => 'Letzte Antwort :time',
    'opened' => 'Erstellt :time',
    'back' => 'Zurück zu deinen Tickets',

    'statuses' => [
        'open' => 'Wartet auf Team',
        'answered' => 'Beantwortet',
        'closed' => 'Geschlossen',
    ],

    'priorities' => [
        'low' => 'Niedrig',
        'normal' => 'Normal',
        'high' => 'Hoch',
        'urgent' => 'Dringend',
    ],

    'team' => 'Team',
    'deleted_user' => 'Gelöschter Benutzer',
    'reply' => 'Antworten',
    'send' => 'Senden',
    'close' => 'Ticket schließen',
    'close_confirm_title' => 'Ticket schließen?',
    'close_confirm_text' => 'Du kannst später trotzdem antworten, dann wird das Ticket wieder geöffnet.',
    'closed_notice' => 'Dieses Ticket ist geschlossen. Mit einer Antwort öffnest du es wieder.',

    'editor' => [
        'bold' => 'Fett',
        'italic' => 'Kursiv',
        'code' => 'Code',
        'quote' => 'Zitat',
        'list' => 'Liste',
        'link' => 'Link',
        'image' => 'Bild hochladen',
        'write' => 'Schreiben',
        'preview' => 'Vorschau',
        'placeholder' => 'Beschreibe dein Anliegen … Markdown wird unterstützt.',
        'hint' => 'Markdown wird unterstützt. Bilder (PNG, JPG, GIF, WebP, bis :size MB) fügst du per Knopf, Einfügen oder Hineinziehen hinzu.',
        'uploading' => 'Wird hochgeladen',
        'too_large' => 'Das Bild ist größer als :size MB.',
        'failed' => 'Das Bild konnte nicht hochgeladen werden.',
        'empty' => 'Nichts für die Vorschau.',
    ],

    'errors' => [
        'max_open' => 'Du hast bereits :count offene Tickets. Bitte warte auf eine Antwort oder schließe zuerst eines.',
    ],
];
