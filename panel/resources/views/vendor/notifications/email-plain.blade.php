<?php

if (! empty($greeting)) {
    echo $greeting, "\n\n";
} else {
    echo $level == 'error' ? __('mail.whoops') : __('mail.hello'), "\n\n";
}

if (! empty($introLines)) {
    // Hydra: lines can be HTML (e.g. the registration code), the plain text mail shows them without tags.
    echo implode("\n", array_map(fn ($line) => strip_tags((string) $line), $introLines)), "\n\n";
}

if (isset($actionText)) {
    echo "{$actionText}: {$actionUrl}", "\n\n";
}

if (! empty($outroLines)) {
    echo implode("\n", $outroLines), "\n\n";
}

echo __('mail.regards'), "\n";
echo config('app.name'), "\n";
echo "\n", \Pterodactyl\Services\Hydra\BrandingService::CREDIT, "\n";
