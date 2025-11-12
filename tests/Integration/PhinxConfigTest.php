<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PhinxConfigTest extends TestCase
{
    public function testPhinxConfigAndMigrationsExist(): void
    {
        $this->assertFileExists(__DIR__ . '/../../phinx.php', 'phinx.php must exist in project root');
        $this->assertFileExists(__DIR__ . '/../../phinx.test.php', 'phinx.test.php must exist for test environment');
        $this->assertDirectoryExists(__DIR__ . '/../../migrations', 'migrations directory must exist');
        $this->assertFileExists(__DIR__ . '/../../migrations/20251104124502_initial_database_schema.php', 'initial migration must exist');
    }
}
