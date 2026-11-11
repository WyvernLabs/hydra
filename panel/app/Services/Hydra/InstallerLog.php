<?php

namespace Pterodactyl\Services\Hydra;

/**
 * Hydra: output of the installers (panel and wings) when they run without a
 * terminal, e.g. the node installation over SSH or an update from the admin
 * area. Besides the normal lines they write "##hydra-progress <percent> <text>",
 * which become a progress bar here and are removed from the log.
 */
final class InstallerLog
{
    private const MARKER = '/^##hydra-progress (\d{1,3})(?: (.*))?$/m';

    /**
     * @return array{log: string, progress: ?int, stage: ?string}
     */
    public static function parse(string $log): array
    {
        $progress = null;
        $stage = null;
        if (preg_match_all(self::MARKER, $log, $matches, PREG_SET_ORDER)) {
            // The highest value counts: when an update brings a newer installer, that one
            // starts again at 0 % although the work before it is already done.
            foreach ($matches as $match) {
                $value = min(100, (int) $match[1]);
                if ($progress === null || $value >= $progress) {
                    $progress = $value;
                    $stage = trim($match[2] ?? '') ?: $stage;
                }
            }
        }

        return [
            'log' => self::strip($log),
            'progress' => $progress,
            'stage' => $stage,
        ];
    }

    /**
     * The log without the progress lines.
     */
    public static function strip(string $log): string
    {
        return preg_replace('/^##hydra-progress\b.*(?:\r?\n|$)/m', '', $log) ?? $log;
    }
}
