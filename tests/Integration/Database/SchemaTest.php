<?php
declare(strict_types=1);

namespace Tests\Integration\Database;

use PHPUnit\Framework\TestCase;
use PDO;

final class SchemaTest extends TestCase
{
    private string $dbFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dbFile = dirname(__DIR__, 2) . '/var/test_schema.sqlite';

        // Clean up previous file
        if (file_exists($this->dbFile)) {
            @unlink($this->dbFile);
        }

        // Ensure environment variables for Phinx test environment
        $_ENV['DB_DRIVER'] = 'sqlite';
        $_ENV['DB_NAME'] = $this->dbFile;
    // Ensure child processes see the env vars too
    putenv('DB_DRIVER=sqlite');
    putenv('DB_NAME=' . $this->dbFile);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->dbFile)) {
            @unlink($this->dbFile);
        }

        // Clean env
        foreach (['DB_DRIVER', 'DB_NAME'] as $v) {
            if (isset($_ENV[$v])) {
                unset($_ENV[$v]);
            }
            putenv($v);
        }

        parent::tearDown();
    }

    /**
     * @todo Integration test for Phinx migrations.
     *        Known issue: Phinx change() with SQLite transactions causes "table already exists" error.
     *        This is a framework limitation to be addressed in a future iteration.
     *        For now, migrations are tested manually: composer run migrate
     */
    public function testMigrationsCreateExpectedTables(): void
    {
        // Implement a deterministic Phinx invocation using a temporary absolute config
        $projectRoot = dirname(__DIR__, 3);

        // Ensure tests/var exists and is writable
        $varDir = $projectRoot . '/tests/var';
        if (!is_dir($varDir)) {
            mkdir($varDir, 0777, true);
        }

        // Unique IDs per-run to isolate phinxlog and logs
        $uniq = bin2hex(random_bytes(6));
        $tempConfig = $varDir . "/phinx.test.{$uniq}.php";
        $logFile = $varDir . "/phinx_{$uniq}.log";

        // Remove any previous DB files matching the base name (covers .sqlite, .sqlite3, .sqlite.phinx.log etc.)
        $matches = glob($this->dbFile . '*');
        if (!empty($matches)) {
            try {
                // Attempt to open each matching file and drop expected tables
                foreach ($matches as $m) {
                    try {
                        $pdoCleanup = new PDO('sqlite:' . $m);
                        foreach (['projects', 'templates', 'components'] as $t) {
                            $pdoCleanup->exec("DROP TABLE IF EXISTS `{$t}`;");
                        }
                        $rows = $pdoCleanup->query("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'phinxlog_test_%'");
                        if ($rows) {
                            foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) {
                                $pdoCleanup->exec('DROP TABLE IF EXISTS ' . $r['name']);
                            }
                        }
                    } catch (\Throwable $inner) {
                        // ignore per-file errors and continue
                    }
                }
            } catch (\Throwable $e) {
                // best-effort cleanup; if it fails, fall back to unlink
                // nothing to do here; we'll unlink matches below
            }
            // Remove any matched files to start from a clean slate
            foreach ($matches as $m) {
                @unlink($m);
            }
        }

        // Build absolute Phinx config array (no getenv(), no relative __DIR__)
        $config = [
            'paths' => [
                'migrations' => $projectRoot . '/migrations',
            ],
            'environments' => [
                'default_migration_table' => 'phinxlog_test_' . $uniq,
                'default_database' => 'test',
                'test' => [
                    'adapter' => 'sqlite',
                    'name' => $this->dbFile,
                ],
            ],
        ];

        // Write temp config (returns an array for Phinx to include)
        $phpContent = "<?php\nreturn " . var_export($config, true) . ";\n";
        file_put_contents($tempConfig, $phpContent);

        // Run Phinx programmatically (Manager) to avoid child-process env/config issues
        // Persist temp config content for inspection
        file_put_contents($varDir . "/phinx_config_{$uniq}.php", $phpContent);

        $outputText = '';
        $exitCode = 1;

        // Dump pre-migration DB state for debugging (if file exists)
        $preStateFile = $varDir . "/phinx_prestate_{$uniq}.log";
        if (file_exists($this->dbFile)) {
            try {
                $pdoPre = new PDO('sqlite:' . $this->dbFile);
                $rows = $pdoPre->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
                $pre = array_map(fn($r) => $r['name'], $rows);
                file_put_contents($preStateFile, implode(PHP_EOL, $pre));
            } catch (\Throwable $e) {
                file_put_contents($preStateFile, 'ERR: ' . $e->getMessage());
            }
        } else {
            file_put_contents($preStateFile, 'MISSING');
        }

        try {
            // Lazy-load classes from vendor; autoload is available in PHPUnit environment
            $phinxConfigObj = new \Phinx\Config\Config($config);

            $input = new \Symfony\Component\Console\Input\ArrayInput([
                'command' => 'migrate',
                'environment' => 'test',
            ]);

            $output = new \Symfony\Component\Console\Output\BufferedOutput();

            $manager = new \Phinx\Migration\Manager($phinxConfigObj, $input, $output);
            // Run migrations for 'test' environment
            $manager->migrate('test');

            $outputText = $output->fetch();
            $exitCode = 0;
        } catch (\Throwable $e) {
            $outputText = $e->getMessage() . PHP_EOL . $e->getTraceAsString();
            // If the Symfony BufferedOutput exists, try to fetch any buffered content
            if (isset($output) && $output instanceof \Symfony\Component\Console\Output\BufferedOutput) {
                $outputText = $output->fetch() . PHP_EOL . $outputText;
            }
            $exitCode = 1;
        }

        // Persist logs for post-mortem
        file_put_contents($logFile, $outputText);

        // Clean up the temp config file (keep logs and sqlite file for inspection)
        @unlink($tempConfig);

        // Assert Phinx succeeded
        $this->assertSame(
            0,
            $exitCode,
            "Phinx migrate failed with exit code $exitCode. Log: {$logFile}" . PHP_EOL . $outputText
        );

    // Verify SQLite file was created (allow for .sqlite3 or other suffixes) and has content
        $dbMatches = glob($this->dbFile . '*');
        $this->assertNotEmpty($dbMatches, "No DB file found matching {$this->dbFile}*");

        // Prefer an actual SQLite file; try opening each match until one works
        $dbPath = null;
        $tables = [];
        foreach ($dbMatches as $candidate) {
            if (!is_file($candidate) || filesize($candidate) === 0) {
                continue;
            }
            try {
                $pdoCandidate = new PDO('sqlite:' . $candidate);
                $stmt = $pdoCandidate->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
                if ($stmt !== false) {
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $tables = array_map(fn($r) => $r['name'], $rows);
                    $dbPath = $candidate;
                    break;
                }
            } catch (\Throwable $e) {
                // Not a valid sqlite DB file, continue to next candidate
                continue;
            }
        }

        $this->assertNotNull($dbPath, "No valid SQLite DB found among matches: " . implode(', ', $dbMatches));
        $this->assertFileExists($dbPath, "SQLite file should exist at {$dbPath} after phinx migrate");
        $fileSize = filesize($dbPath);
        $this->assertGreaterThan(0, $fileSize, "SQLite file should not be empty; size: {$fileSize} bytes");

        $this->assertNotEmpty($tables, "No tables found in SQLite file after migration. See {$logFile}");

        foreach (['components', 'projects', 'templates'] as $table) {
            $this->assertContains($table, $tables, "Expected table '$table' to exist in {$dbPath} (Log: {$logFile})");
        }
    }
}
