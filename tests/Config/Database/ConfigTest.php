<?php
declare(strict_types=1);

namespace Tests\Config\Database;

use App\Config\Database\Config;
use App\Config\Environment;
use PHPUnit\Framework\TestCase;
use PDO;

class ConfigTest extends TestCase
{
    private string $originalEnvPath;
    private string $testEnvPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $rootDir = dirname(__DIR__, 2);
        $this->originalEnvPath = $rootDir . '/.env';
        $this->testEnvPath = $rootDir . '/.env.test';
        
        // Sauvegarder l'original s'il existe
        if (file_exists($this->originalEnvPath)) {
            copy($this->originalEnvPath, $this->originalEnvPath . '.backup');
        }
    }

    protected function tearDown(): void
    {
        // Restaurer l'original
        if (file_exists($this->originalEnvPath . '.backup')) {
            // Restaurer le fichier original
            if (file_exists($this->originalEnvPath)) {
                @unlink($this->originalEnvPath);
            }
            rename($this->originalEnvPath . '.backup', $this->originalEnvPath);
        } else {
            // Supprimer le fichier .env créé pour le test s'il existe
            if (file_exists($this->originalEnvPath)) {
                @unlink($this->originalEnvPath);
            }
        }

        // Réinitialiser les variables d'environnement spécifiques aux tests
        foreach (['DB_DRIVER', 'DB_NAME', 'DB_HOST', 'DB_PORT', 'DB_USER', 'DB_PASS', 'DB_CHARSET'] as $var) {
            if (isset($_ENV[$var])) {
                unset($_ENV[$var]);
            }
        }

        parent::tearDown();
    }

    public function testCanCreateSqliteConnection(): void
    {
        // Setup
        file_put_contents($this->testEnvPath, "
DB_DRIVER=sqlite
DB_NAME=:memory:
        ");
        rename($this->testEnvPath, $this->originalEnvPath);
        
        Environment::getInstance()->load();
        
        // Test
        $config = new Config();
        
        // Assert
        $this->assertInstanceOf(PDO::class, $config->connection);
        $this->assertEquals(':memory:', $config->dbName);
        
        // Test que la connexion fonctionne
        $config->connection->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)');
        $config->connection->exec('INSERT INTO test (name) VALUES ("test")');
        $result = $config->connection->query('SELECT * FROM test')->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('test', $result['name']);
    }
    
    public function testCanLoadMinimalConfig(): void
    {
        // Setup - fichier .env minimal
        file_put_contents($this->testEnvPath, "
DB_DRIVER=sqlite
DB_NAME=:memory:
        ");
        rename($this->testEnvPath, $this->originalEnvPath);
        
        Environment::getInstance()->load();
        
        // Test
        $config = new Config();
        
        // Assert
        $this->assertInstanceOf(PDO::class, $config->connection);
        $this->assertEquals('test_db', $config->dbName);
    }

    public function testThrowsExceptionForInvalidDriver(): void
    {
        // Setup
        file_put_contents($this->testEnvPath, "
DB_DRIVER=invaliddb
DB_NAME=:memory:
        ");
        rename($this->testEnvPath, $this->originalEnvPath);
        
        Environment::getInstance()->load();
        
        // Test & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Driver de base de données non supporté');
        new Config();
    }

    public function testThrowsExceptionForMissingCredentials(): void
    {
        // Setup - MySQL but with missing credentials
        file_put_contents($this->testEnvPath, "
DB_DRIVER=mysql
DB_NAME=testdb
DB_HOST=localhost
DB_PORT=3306
DB_USER=
DB_PASS=
        ");
        rename($this->testEnvPath, $this->originalEnvPath);
        
        Environment::getInstance()->load();
        
        // Test & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Credentials');
        new Config();
    }

    public function testThrowsExceptionForInvalidPort(): void
    {
        // Setup
        file_put_contents($this->testEnvPath, "
DB_DRIVER=mysql
DB_NAME=testdb
DB_HOST=localhost
DB_PORT=99999
DB_USER=user
DB_PASS=pass
        ");
        rename($this->testEnvPath, $this->originalEnvPath);
        
        Environment::getInstance()->load();
        
        // Test & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Port invalide');
        new Config();
    }

    public function testThrowsExceptionForUnsupportedCharset(): void
    {
        // Setup
        file_put_contents($this->testEnvPath, "
DB_DRIVER=mysql
DB_NAME=testdb
DB_HOST=localhost
DB_PORT=3306
DB_USER=user
DB_PASS=pass
DB_CHARSET=unsupported_charset
        ");
        rename($this->testEnvPath, $this->originalEnvPath);
        
        Environment::getInstance()->load();
        
        // Test & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Charset non supporté');
        new Config();
    }

    public function testDiagnosticsArePopulated(): void
    {
        // Setup
        file_put_contents($this->testEnvPath, "
DB_DRIVER=sqlite
DB_NAME=:memory:
        ");
        rename($this->testEnvPath, $this->originalEnvPath);
        
        Environment::getInstance()->load();
        
        // Test
        $config = new Config();
        $diagnostics = $config->getDiagnostics();
        
        // Assert
        $this->assertIsArray($diagnostics);
        $this->assertArrayHasKey('connection_success', $diagnostics);
        $this->assertArrayHasKey('driver', $diagnostics['connection_success']);
        $this->assertArrayHasKey('database', $diagnostics['connection_success']);
        $this->assertEquals('sqlite', $diagnostics['connection_success']['driver']);
    }
}