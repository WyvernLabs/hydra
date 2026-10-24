<?php

/*
 * Hydra: Cloudflare-Domains des Subdomain-Addons.
 */
return [
    'title' => 'Domains',
    'subtitle' => 'Cloudflare-Domains, unter denen Benutzer Subdomains anlegen können.',
    'list' => 'Domains',
    'name' => 'Domain',
    'subdomains' => 'Subdomains',
    'state' => 'Zustand',
    'none' => 'Noch keine Domains.',
    'enable' => 'Aktivieren',
    'disable' => 'Deaktivieren',
    'delete_hint' => 'Nur Domains ohne Subdomains können entfernt werden.',
    'add' => 'Domain hinzufügen',
    'add_button' => 'Prüfen und hinzufügen',
    'token' => 'Cloudflare-API-Token',
    'token_help' => 'Lege es in Cloudflare unter „Mein Profil > API-Tokens“ mit dem Recht „Zone > DNS > Bearbeiten“ für diese Zone an. Es wird verschlüsselt gespeichert.',
    'info' => 'Deaktivierte Domains behalten ihre Subdomains, es können aber keine neuen angelegt werden. Von Hand in Cloudflare angelegte Einträge werden nie verändert.',
    'added' => ':name wurde hinzugefügt.',
    'updated' => ':name wurde gespeichert.',
    'deleted' => ':name wurde entfernt.',
    'in_use' => 'Diese Domain wird noch von Subdomains benutzt.',
    'zone_not_found' => 'Das Token hat keinen Zugriff auf die Zone :name.',
    'unreachable' => 'Cloudflare ist nicht erreichbar.',
    'api_error' => 'Cloudflare: :message',
];
