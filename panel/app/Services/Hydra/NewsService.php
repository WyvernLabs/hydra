<?php

namespace Pterodactyl\Services\Hydra;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

/**
 * Hydra: version, build and the news of every release (Admin > Overview).
 *
 * The releases come from resources/hydra/changelog.json. The installed panel
 * reads its own copy; the newest copy is fetched from the source repository
 * (config hydra.news.url), so every installation sees what an update brings
 * before installing it. The remote file is cached for the refresh interval
 * of the overview page.
 */
class NewsService
{
    public const TYPES = ['new', 'improved', 'fixed', 'security'];

    public const CACHE_KEY = 'hydra:news:remote';

    /**
     * A forced refresh (button) fetches again at most this often.
     */
    public const MIN_REFRESH_SECONDS = 30;

    private const MAX_RELEASES = 50;

    private const MAX_CHANGES = 40;

    private const MAX_TEXT = 1000;

    public static function localPath(): string
    {
        return resource_path('hydra/changelog.json');
    }

    public function version(): string
    {
        return (string) config('app.version');
    }

    public function build(): int
    {
        return (int) config('app.build');
    }

    /**
     * Releases bundled with the installed panel, newest first.
     */
    public function localReleases(): array
    {
        $path = self::localPath();

        return is_file($path) ? self::parse((string) file_get_contents($path)) : [];
    }

    /**
     * The newest releases from the source repository.
     *
     * @return array{releases: array, checked_at: string|null, error: bool}
     */
    public function remote(bool $refresh = false): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        $fresh = is_array($cached) && CarbonImmutable::parse($cached['checked_at'])->addMinutes($this->interval())->isFuture();
        $recent = is_array($cached) && CarbonImmutable::parse($cached['checked_at'])->addSeconds(self::MIN_REFRESH_SECONDS)->isFuture();

        // No source configured (HYDRA_NEWS_URL empty): only the bundled news.
        if (empty(config('hydra.news.url'))) {
            return ['releases' => [], 'checked_at' => null, 'error' => false];
        }

        if (($fresh && !$refresh) || $recent) {
            return $cached;
        }

        try {
            $response = Http::timeout(6)->connectTimeout(4)->accept('application/json')->get(config('hydra.news.url'));
            $releases = $response->successful() ? self::parse($response->body()) : [];
            $result = ['releases' => $releases, 'checked_at' => CarbonImmutable::now()->toIso8601String(), 'error' => empty($releases)];
        } catch (\Throwable) {
            $result = ['releases' => [], 'checked_at' => CarbonImmutable::now()->toIso8601String(), 'error' => true];
        }

        // A failed check keeps showing the last good list.
        if ($result['error'] && !empty($cached['releases'])) {
            $result['releases'] = $cached['releases'];
        }

        Cache::put(self::CACHE_KEY, $result, now()->addDay());

        return $result;
    }

    /**
     * Everything the overview page shows: local and remote releases merged,
     * each marked as installed or newer than the installation.
     */
    public function overview(bool $refresh = false): array
    {
        $remote = $this->remote($refresh);

        $releases = [];
        foreach (array_merge($this->localReleases(), $remote['releases']) as $release) {
            // The remote copy wins, it may contain corrections.
            $releases[$release['build']] = $release;
        }
        krsort($releases);

        $build = $this->build();
        $releases = array_map(fn (array $release) => $release + [
            'is_installed' => $release['build'] === $build,
            'is_newer' => $release['build'] > $build,
        ], array_values($releases));

        $latest = $releases[0] ?? null;

        return [
            'version' => $this->version(),
            'build' => $build,
            'latest' => $latest,
            'has_update' => $latest !== null && $latest['build'] > $build,
            'releases' => $releases,
            'checked_at' => $remote['checked_at'],
            'error' => $remote['error'],
        ];
    }

    /**
     * Refresh interval of the overview page in minutes.
     */
    public function interval(): int
    {
        return max(1, (int) config('hydra.news.interval', 10));
    }

    /**
     * Reads and validates a changelog. Anything unexpected is dropped, the
     * texts are plain strings (escaped when shown).
     */
    public static function parse(string $json): array
    {
        $data = json_decode($json, true);
        if (!is_array($data) || !is_array($data['releases'] ?? null)) {
            return [];
        }

        $releases = [];
        foreach (array_slice($data['releases'], 0, self::MAX_RELEASES) as $release) {
            if (!is_array($release) || !is_string($release['version'] ?? null) || !is_int($release['build'] ?? null)) {
                continue;
            }

            if (!preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $release['version']) || $release['build'] < 1) {
                continue;
            }

            $changes = [];
            foreach (array_slice(is_array($release['changes'] ?? null) ? $release['changes'] : [], 0, self::MAX_CHANGES) as $change) {
                $texts = is_array($change) ? self::texts($change) : [];
                if (!empty($texts)) {
                    $changes[] = ['type' => in_array($change['type'] ?? null, self::TYPES, true) ? $change['type'] : 'improved', 'text' => $texts];
                }
            }

            $date = is_string($release['date'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $release['date']) ? $release['date'] : null;

            $releases[] = [
                'version' => $release['version'],
                'build' => $release['build'],
                'date' => $date,
                'title' => self::texts(is_array($release['title'] ?? null) ? $release['title'] : []),
                'changes' => $changes,
            ];
        }

        usort($releases, fn (array $a, array $b) => $b['build'] <=> $a['build']);

        return $releases;
    }

    /**
     * Picks the text in the given language, falling back to English, German
     * and then any other language.
     */
    public static function text(array $texts, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $texts[$locale] ?? $texts['en'] ?? $texts['de'] ?? (string) (reset($texts) ?: '');
    }

    /**
     * Language => text pairs of a changelog entry ("de", "en", …).
     */
    private static function texts(array $values): array
    {
        $texts = [];
        foreach ($values as $locale => $text) {
            if (is_string($locale) && preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/', $locale) && is_string($text) && trim($text) !== '') {
                $texts[$locale] = mb_substr(trim($text), 0, self::MAX_TEXT);
            }
        }

        return $texts;
    }
}
