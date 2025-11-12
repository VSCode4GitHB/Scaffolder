<?php
declare(strict_types=1);

namespace Tests\Config;

use App\Config\Environment;
use PHPUnit\Framework\TestCase;

class EnvironmentTest extends TestCase
{
    private Environment $env;
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
        
        // Créer un .env de test
        file_put_contents($this->testEnvPath, "
DB_DRIVER=mysql
DB_HOST=test-host
DB_PORT=3307
DB_NAME=test_db
DB_USER=test_user
DB_PASS=test_pass
APP_ENV=testing
        ");
        
        rename($this->testEnvPath, $this->originalEnvPath);
        
        $this->env = Environment::getInstance();
    }

    protected function tearDown(): void
    {
        // Restaurer l'original
        if (file_exists($this->originalEnvPath . '.backup')) {
            rename($this->originalEnvPath . '.backup', $this->originalEnvPath);
        } else {
            unlink($this->originalEnvPath);
        }
        
        parent::tearDown();
    }

    public function testCanGetEnvironmentValues(): void
    {
        $this->env->load();
        
        $this->assertEquals('mysql', $this->env->get('DB_DRIVER'));
        $this->assertEquals('test-host', $this->env->get('DB_HOST'));
        $this->assertEquals('3307', $this->env->get('DB_PORT'));
        $this->assertEquals('test_db', $this->env->get('DB_NAME'));
        $this->assertEquals('test_user', $this->env->get('DB_USER'));
        $this->assertEquals('test_pass', $this->env->get('DB_PASS'));
        $this->assertEquals('testing', $this->env->get('APP_ENV'));
    }

    public function testDefaultValues(): void
    {
        $this->env->load();
        
        $this->assertEquals('default', $this->env->get('NON_EXISTENT', 'default'));
        $this->assertNull($this->env->get('NON_EXISTENT'));
    }

    public function testEnvironmentDetection(): void
    {
        $this->env->load();
        
        $this->assertFalse($this->env->isProduction());
        $this->assertFalse($this->env->isDevelopment());
    }
}