<?php

/*
 * Hydra: Nachrichten des Discord-Bots und Fehlermeldungen beim Verknüpfen.
 */
return [
    'code_message' => "**:panel**: Dein Code zum Verknüpfen mit dem Panel-Konto **:username** lautet:\n\n`:code`\n\nEr gilt :minutes Minuten. Gib ihn nur im Panel ein und gib ihn nicht weiter. Falls du das nicht warst, kannst du diese Nachricht ignorieren.",
    'ticket_message' => "**:panel**: Neue Antwort in deinem Ticket „:subject“.\n:url",
    'errors' => [
        'taken' => 'Dieses Discord-Konto ist schon mit einem anderen Panel-Konto verknüpft.',
        'already_linked' => 'Dein Konto ist schon mit Discord verknüpft. Trenne die Verbindung zuerst.',
        'cooldown' => 'An dieses Discord-Konto wurde gerade ein Code gesendet. Versuche es in :seconds Sekunden erneut.',
        'dm_closed' => 'Der Bot kann dir keine Nachricht schicken. Tritt dem Discord-Server bei und erlaube Direktnachrichten von Servermitgliedern.',
        'unknown_user' => 'Zu dieser ID gibt es kein Discord-Konto. Prüfe deine Benutzer-ID.',
        'unavailable' => 'Discord ist gerade nicht erreichbar. Versuche es später erneut.',
        'code_expired' => 'Der Code ist abgelaufen. Lass dir einen neuen senden.',
        'code_wrong' => 'Der Code stimmt nicht.',
        'too_many_attempts' => 'Zu viele falsche Versuche. Lass dir einen neuen Code senden.',
    ],
];
