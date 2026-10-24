<?php

/*
 * Hydra: Cloudflare domains of the subdomains addon.
 */
return [
    'title' => 'Domains',
    'subtitle' => 'Cloudflare domains users can create subdomains of.',
    'list' => 'Domains',
    'name' => 'Domain',
    'subdomains' => 'Subdomains',
    'state' => 'State',
    'none' => 'No domains yet.',
    'enable' => 'Enable',
    'disable' => 'Disable',
    'delete_hint' => 'Only domains without subdomains can be removed.',
    'add' => 'Add domain',
    'add_button' => 'Check and add',
    'token' => 'Cloudflare API token',
    'token_help' => 'Create it in Cloudflare under My Profile > API Tokens with the permission "Zone > DNS > Edit" for this zone. It is stored encrypted.',
    'info' => 'Disabled domains keep their subdomains but no new ones can be created. Records made by hand in Cloudflare are never changed.',
    'added' => ':name was added.',
    'updated' => ':name was saved.',
    'deleted' => ':name was removed.',
    'in_use' => 'Subdomains still use this domain.',
    'zone_not_found' => 'The token has no access to the zone :name.',
    'unreachable' => 'Cloudflare could not be reached.',
    'api_error' => 'Cloudflare: :message',
];
