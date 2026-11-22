<?php

namespace Pterodactyl\Console\Commands\Hydra;

use Illuminate\Console\Command;
use Pterodactyl\Services\Hydra\DomainService;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

/**
 * Hydra: run by "hydra-panel domain" after the domain of the panel changed.
 * Points every node at the new panel URL and moves the sender address of the
 * mail settings along if it still is the default no-reply@<old domain>.
 */
class DomainChangedCommand extends Command
{
    protected $signature = 'hydra:domain:changed {--old-domain= : The domain the panel had before}';

    protected $description = 'Points the nodes at the new panel URL after the domain changed.';

    public function handle(DomainService $domains, SettingsRepositoryInterface $settings): int
    {
        $url = rtrim((string) config('app.url'), '/');
        $domain = (string) parse_url($url, PHP_URL_HOST);
        $old = DomainService::normalize((string) $this->option('old-domain'));

        if ($old !== '' && $old !== $domain && !DomainService::isIp($domain)) {
            $key = 'settings::mail:from:address';
            if ($settings->get($key, null) === "no-reply@$old") {
                $settings->set($key, "no-reply@$domain");
                $this->line("Mail sender: no-reply@$domain");
            }
        }

        $failed = 0;
        foreach ($domains->syncNodes($url) as $node) {
            if ($node['ok']) {
                $this->line("Node {$node['name']}: $url");
            } else {
                ++$failed;
                $this->line("Node {$node['name']}: {$node['code']} – {$node['error']}");
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
