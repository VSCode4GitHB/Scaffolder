<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function testBasicScaffolderRequirements(): void
    {
        // Vérifie que les dossiers essentiels existent
        $this->assertDirectoryExists(__DIR__ . '/../../src/Domain');
        $this->assertDirectoryExists(__DIR__ . '/../../src/Application');
        $this->assertDirectoryExists(__DIR__ . '/../../src/Infrastructure');
        $this->assertDirectoryExists(__DIR__ . '/../../src/UI');
        
        // Vérifie que la configuration de la base de données existe
        $this->assertFileExists(__DIR__ . '/../../config/Database/Config.php');
    }
}