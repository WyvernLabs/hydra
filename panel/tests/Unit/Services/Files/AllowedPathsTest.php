<?php

namespace Pterodactyl\Tests\Unit\Services\Files;

use Pterodactyl\Tests\TestCase;
use Pterodactyl\Services\Files\AllowedPaths;

class AllowedPathsTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('normalizeDataProvider')]
    public function testPathsAreNormalized(?string $input, string $expected)
    {
        $this->assertSame($expected, AllowedPaths::normalize($input));
    }

    public static function normalizeDataProvider(): array
    {
        return [
            [null, '/'],
            ['', '/'],
            ['/', '/'],
            ['plugins/WorldEdit', '/plugins/WorldEdit'],
            ['/plugins/WorldEdit/', '/plugins/WorldEdit'],
            ['//plugins//./WorldEdit', '/plugins/WorldEdit'],
            ['/world/build/../../server.properties', '/server.properties'],
            ['../../etc/passwd', '/etc/passwd'],
            ['plugins\WorldEdit', '/plugins/WorldEdit'],
        ];
    }

    public function testListIsCleaned()
    {
        $this->assertSame(['/plugins/WorldEdit', '/world/build'], AllowedPaths::clean(['world/build', '/plugins/WorldEdit/', 'world/build/schematics', '', 5]));
        $this->assertSame([], AllowedPaths::clean(['plugins', '/']));
        $this->assertSame(['/a', '/a-b'], AllowedPaths::clean(['/a-b', '/a', '/a/b']));
    }

    public function testUnrestrictedAllowsEverything()
    {
        $paths = new AllowedPaths();

        $this->assertFalse($paths->isRestricted());
        $this->assertTrue($paths->allows('/server.properties'));
        $this->assertTrue($paths->canBrowse('/'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('accessDataProvider')]
    public function testAccessIsLimitedToFolders(string $path, bool $allows, bool $browse)
    {
        $paths = new AllowedPaths(AllowedPaths::clean(['plugins/WorldEdit', 'world/build']));

        $this->assertSame($allows, $paths->allows($path), "allows($path)");
        $this->assertSame($browse, $paths->canBrowse($path), "canBrowse($path)");
    }

    public static function accessDataProvider(): array
    {
        return [
            ['/', false, true],
            ['/plugins', false, true],
            ['/plugins/WorldEdit', true, true],
            ['/plugins/WorldEdit/config.yml', true, true],
            ['/plugins/WorldEditor', false, false],
            ['/plugins/Essentials', false, false],
            ['/world', false, true],
            ['/world/build/region/r.0.0.mca', true, true],
            ['/world/build/../../server.properties', false, false],
            ['/world/build/../build2', false, false],
            ['/server.properties', false, false],
        ];
    }

    public function testListingOnlyShowsTheWayToAllowedFolders()
    {
        $paths = new AllowedPaths(AllowedPaths::clean(['plugins/WorldEdit']));
        $entries = [['name' => 'plugins'], ['name' => 'world'], ['name' => 'server.properties']];

        $this->assertSame([['name' => 'plugins']], $paths->filterListing('/', $entries));
        $this->assertSame(
            [['name' => 'WorldEdit']],
            $paths->filterListing('/plugins', [['name' => 'WorldEdit'], ['name' => 'Essentials']])
        );
        $this->assertSame($entries, $paths->filterListing('/plugins/WorldEdit', $entries));
    }

    public function testCovers()
    {
        $own = new AllowedPaths(AllowedPaths::clean(['plugins']));

        $this->assertTrue($own->covers(new AllowedPaths(['/plugins/WorldEdit'])));
        $this->assertFalse($own->covers(new AllowedPaths(['/world'])));
        $this->assertFalse($own->covers(new AllowedPaths()));
        $this->assertTrue((new AllowedPaths())->covers(new AllowedPaths()));
    }
}
