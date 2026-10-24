<?php

/*
 * Hydra: subdomains, reverse proxies and ports on a dedicated node.
 */
return [
    'subdomain_invalid' => 'Use only lowercase letters, digits and hyphens (at most 63 characters, not at the start or end).',
    'subdomain_blocked' => 'The name ":name" cannot be used.',
    'subdomain_taken' => ':name is already taken.',
    'subdomain_limit' => 'This server cannot have more subdomains.',
    'domain_unavailable' => 'This domain cannot be used right now.',
    'allocation_invalid' => 'This port does not belong to the server.',
    'srv_invalid' => 'Unknown SRV service.',
    'domain_invalid' => 'This is not a valid domain.',
    'domain_not_pointing' => ':domain does not point to the node yet. Create an A record to the address of :target (or a CNAME to :target) and try again once it is active.',
    'proxy_taken' => 'A proxy for :domain exists already.',
    'proxy_limit' => 'This server cannot have more reverse proxies.',
    'proxy_errors' => [
        'caddy_missing' => 'The node has no web server for reverse proxies yet. An admin has to run "hydra-node update" on it.',
        'caddy_invalid' => 'The web server of the node refused the configuration.',
        'caddy_reload_failed' => 'The web server of the node could not be reloaded.',
        'invalid_request' => 'The node refused the domain or port.',
        'command_failed' => 'The node could not set up the reverse proxy.',
    ],
    'not_dedicated' => 'Only servers on a dedicated node can pick their ports.',
    'port_range' => 'The port has to be between :min and 65535.',
    'port_limit' => 'This server cannot have more ports.',
    'port_reserved' => 'Port :port is used by the node itself.',
    'port_taken' => 'Port :port is already in use by another server.',
    'port_yours' => 'Port :port is already assigned to this server.',
];
