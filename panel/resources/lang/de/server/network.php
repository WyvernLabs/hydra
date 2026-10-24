<?php

/*
 * Hydra: Subdomains, Reverse Proxys und Ports auf einer dedizierten Node.
 */
return [
    'subdomain_invalid' => 'Erlaubt sind nur Kleinbuchstaben, Ziffern und Bindestriche (höchstens 63 Zeichen, nicht am Anfang oder Ende).',
    'subdomain_blocked' => 'Der Name „:name“ kann nicht verwendet werden.',
    'subdomain_taken' => ':name ist bereits vergeben.',
    'subdomain_limit' => 'Dieser Server kann keine weiteren Subdomains haben.',
    'domain_unavailable' => 'Diese Domain kann gerade nicht verwendet werden.',
    'allocation_invalid' => 'Dieser Port gehört nicht zum Server.',
    'srv_invalid' => 'Unbekannter SRV-Dienst.',
    'domain_invalid' => 'Das ist keine gültige Domain.',
    'domain_not_pointing' => ':domain zeigt noch nicht auf die Node. Lege einen A-Eintrag auf die Adresse von :target an (oder einen CNAME auf :target) und versuche es erneut, sobald er aktiv ist.',
    'proxy_taken' => 'Für :domain gibt es bereits einen Proxy.',
    'proxy_limit' => 'Dieser Server kann keine weiteren Reverse Proxys haben.',
    'proxy_errors' => [
        'caddy_missing' => 'Die Node hat noch keinen Webserver für Reverse Proxys. Ein Admin muss dort „hydra-node update“ ausführen.',
        'caddy_invalid' => 'Der Webserver der Node hat die Konfiguration abgelehnt.',
        'caddy_reload_failed' => 'Der Webserver der Node konnte nicht neu geladen werden.',
        'invalid_request' => 'Die Node hat die Domain oder den Port abgelehnt.',
        'command_failed' => 'Die Node konnte den Reverse Proxy nicht einrichten.',
    ],
    'not_dedicated' => 'Nur Server auf einer dedizierten Node können ihre Ports selbst wählen.',
    'port_range' => 'Der Port muss zwischen :min und 65535 liegen.',
    'port_limit' => 'Dieser Server kann keine weiteren Ports haben.',
    'port_reserved' => 'Port :port wird von der Node selbst benutzt.',
    'port_taken' => 'Port :port wird bereits von einem anderen Server benutzt.',
    'port_yours' => 'Port :port ist diesem Server bereits zugewiesen.',
];
