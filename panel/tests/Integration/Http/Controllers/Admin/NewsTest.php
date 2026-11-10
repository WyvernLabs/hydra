<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Pterodactyl\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Services\Hydra\NewsService;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;

/**
 * Hydra: version, build and news on the admin overview.
 */
class NewsTest extends HttpTestCase
{
    private const URL = 'https://news.test/changelog.json';

    public function setUp(): void
    {
        parent::setUp();

        config()->set('hydra.news.url', self::URL);
        Cache::forget(NewsService::CACHE_KEY);
    }

    private function changelog(array $releases): string
    {
        return json_encode(['releases' => $releases]);
    }

    private function release(string $version, int $build, string $text = 'Something new'): array
    {
        return [
            'version' => $version,
            'build' => $build,
            'date' => '2026-12-01',
            'title' => ['en' => "Release $version", 'de' => "Version $version"],
            'changes' => [['type' => 'new', 'en' => $text, 'de' => "Neu: $text"]],
        ];
    }

    /**
     * The configured version must be the newest entry of the bundled changelog,
     * otherwise the overview would report an update that does not exist.
     */
    public function testConfiguredVersionMatchesTheChangelog(): void
    {
        $releases = $this->app->make(NewsService::class)->localReleases();

        $this->assertNotEmpty($releases);
        $this->assertSame(config('app.version'), $releases[0]['version']);
        $this->assertSame(config('app.build'), $releases[0]['build']);
        $this->assertNotSame('1.0.0-dev', config('app.version'));

        $builds = array_column($releases, 'build');
        $this->assertSame(count($builds), count(array_unique($builds)), 'Every release needs its own build number.');

        foreach ($releases as $release) {
            $this->assertArrayHasKey('de', $release['title'], $release['version']);
            $this->assertArrayHasKey('en', $release['title'], $release['version']);
            foreach ($release['changes'] as $change) {
                $this->assertArrayHasKey('de', $change['text'], $release['version']);
                $this->assertArrayHasKey('en', $change['text'], $release['version']);
            }
        }
    }

    public function testOverviewShowsVersionAndNewerReleases(): void
    {
        $build = config('app.build');
        Http::fake([self::URL => Http::response($this->changelog([$this->release('9.0.0', $build + 5, 'Flying <b>servers</b>')]))]);

        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->getJson('/admin/news')->assertOk()->assertJsonPath('has_update', true);

        $html = $response->json('html');
        $this->assertStringContainsString('9.0.0', $html);
        $this->assertStringContainsString('Flying &lt;b&gt;servers&lt;/b&gt;', $html);
        $this->assertStringContainsString(config('app.version'), $html);
        $this->assertStringContainsString('9.0.0', $response->json('system'));

        $news = $this->app->make(NewsService::class)->overview();
        $this->assertTrue($news['releases'][0]['is_newer']);
        $this->assertSame('9.0.0', $news['latest']['version']);
        $installed = array_values(array_filter($news['releases'], fn ($release) => $release['is_installed']));
        $this->assertSame(config('app.version'), $installed[0]['version']);
    }

    public function testRemoteIsCachedUntilRefreshed(): void
    {
        Http::fake([self::URL => Http::response($this->changelog([$this->release('1.0.0', 1)]))]);
        $service = $this->app->make(NewsService::class);

        $service->remote();
        $service->remote();
        Http::assertSentCount(1);

        // A forced refresh right after a check is throttled …
        $service->remote(true);
        Http::assertSentCount(1);

        // … but goes through once the short pause is over.
        $this->travel(NewsService::MIN_REFRESH_SECONDS + 1)->seconds();
        $service->remote(true);
        Http::assertSentCount(2);

        // Without a refresh it checks again after the interval.
        $this->travel($service->interval())->minutes();
        $service->remote();
        Http::assertSentCount(3);
    }

    public function testUnreachableSourceFallsBackToKnownReleases(): void
    {
        Http::fake([self::URL => Http::response('nope', 500)]);

        $news = $this->app->make(NewsService::class)->overview();

        $this->assertTrue($news['error']);
        $this->assertFalse($news['has_update']);
        $this->assertSame(config('app.version'), $news['latest']['version']);
    }

    public function testDisabledSourceOnlyShowsBundledNews(): void
    {
        config()->set('hydra.news.url', '');
        Http::fake();

        $news = $this->app->make(NewsService::class)->overview(true);

        Http::assertNothingSent();
        $this->assertFalse($news['error']);
        $this->assertSame(config('app.version'), $news['latest']['version']);
    }

    public function testInvalidEntriesAreDropped(): void
    {
        $releases = NewsService::parse($this->changelog([
            ['version' => 'evil', 'build' => 3],
            ['version' => '2.0.0', 'build' => '4'],
            $this->release('1.2.0', 5) + ['changes' => []],
            array_replace($this->release('1.3.0', 6), ['changes' => [['type' => 'boom', 'en' => 'x'], ['type' => 'new'], 'text'], 'date' => 'tomorrow']),
        ]));

        $this->assertSame(['1.3.0', '1.2.0'], array_column($releases, 'version'));
        $this->assertSame([['type' => 'improved', 'text' => ['en' => 'x']]], $releases[0]['changes']);
        $this->assertNull($releases[0]['date']);
        $this->assertSame([], NewsService::parse('not json'));
    }

    public function testTextFallsBackToEnglish(): void
    {
        $this->assertSame('Hallo', NewsService::text(['en' => 'Hello', 'de' => 'Hallo'], 'de'));
        $this->assertSame('Hello', NewsService::text(['en' => 'Hello', 'de' => 'Hallo'], 'fr'));
        $this->assertSame('Hallo', NewsService::text(['de' => 'Hallo'], 'fr'));
    }

    public function testOverviewPageRequiresAdmin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/admin/news')->assertForbidden();
    }
}
