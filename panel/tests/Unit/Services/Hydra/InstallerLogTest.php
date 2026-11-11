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

    public function testANewerInstallerStartingAgainDoesNotSetTheProgressBack(): void
    {
        $parsed = InstallerLog::parse(implode("
", [
            '##hydra-progress 3 Pakete installieren',
            '##hydra-progress 40 Hydra Panel bereitstellen',
            '    Neuere Installer-Version gefunden – starte sie',
            '##hydra-progress 0 System prüfen',
            '##hydra-progress 3 Pakete installieren',
        ]));

        $this->assertSame(40, $parsed['progress']);
        $this->assertSame('Hydra Panel bereitstellen', $parsed['stage']);

        $parsed = InstallerLog::parse("##hydra-progress 40 Bereitstellen
##hydra-progress 0 Neu
##hydra-progress 43
");
        $this->assertSame(43, $parsed['progress']);
        $this->assertSame('Bereitstellen', $parsed['stage']);
    }

    public function testLogWithoutProgress(): void
    {
        $parsed = InstallerLog::parse("==> System prüfen\n    Debian 13 ok");

        $this->assertNull($parsed['progress']);
        $this->assertNull($parsed['stage']);
        $this->assertSame("==> System prüfen\n    Debian 13 ok", $parsed['log']);
    }
}
