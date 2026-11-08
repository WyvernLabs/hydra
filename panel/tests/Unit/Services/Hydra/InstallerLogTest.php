<?php

namespace Pterodactyl\Tests\Unit\Services\Hydra;

use Pterodactyl\Tests\TestCase;
use Pterodactyl\Services\Hydra\InstallerLog;

class InstallerLogTest extends TestCase
{
    public function testProgressLinesBecomeProgressAndLeaveTheLog(): void
    {
        $parsed = InstallerLog::parse(implode("\n", [
            '==> Pakete installieren',
            '##hydra-progress 3 Pakete installieren',
            '    ✓ Basis-Pakete installieren (0:04)',
            '##hydra-progress 10',
            '==> Hydra Wings bereitstellen',
            '##hydra-progress 50 Hydra Wings bereitstellen',
            '    … Baue Hydra Wings läuft noch (1:15)',
        ]));

        $this->assertSame(50, $parsed['progress']);
        $this->assertSame('Hydra Wings bereitstellen', $parsed['stage']);
        $this->assertStringNotContainsString('##hydra-progress', $parsed['log']);
        $this->assertStringContainsString('Baue Hydra Wings läuft noch', $parsed['log']);
    }

    public function testKeepsTheLastStageAndCapsTheValue(): void
    {
        $parsed = InstallerLog::parse("##hydra-progress 40 Datenbank migrieren\n##hydra-progress 140\n");

        $this->assertSame(100, $parsed['progress']);
        $this->assertSame('Datenbank migrieren', $parsed['stage']);
        $this->assertSame('', $parsed['log']);
    }

    public function testLogWithoutProgress(): void
    {
        $parsed = InstallerLog::parse("==> System prüfen\n    Debian 13 ok");

        $this->assertNull($parsed['progress']);
        $this->assertNull($parsed['stage']);
        $this->assertSame("==> System prüfen\n    Debian 13 ok", $parsed['log']);
    }
}
