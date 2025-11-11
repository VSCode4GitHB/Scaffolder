<?php

declare(strict_types=1);

namespace App\Config;

use Dotenv\Dotenv;
use RuntimeException;

class Environment
{
    private static ?self $instance = null;
    private bool $loaded = false;
    private function __construct()
    {
        // Singleton
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $rootDir = dirname(__DIR__, 2);
// Remonte de 2 niveaux depuis src/Config

        // Charge le .env s'il existe
        if (file_exists($rootDir . '/.env')) {
            $dotenv = Dotenv::createImmutable($rootDir);
            $dotenv->load();
// Validation des variables requises
            $dotenv->required([
                'DB_DRIVER',
                'DB_NAME',
                'DB_USER',
                'DB_PASS'
            ])->notEmpty();
// Validation des valeurs spécifiques
            $dotenv->required('DB_DRIVER')->allowedValues(['mysql', 'pgsql', 'sqlite']);
            $dotenv->required('APP_ENV')->allowedValues(['development', 'testing', 'production']);
        } else {
        // En production, on s'attend à ce que les variables soient déjà définies
            // dans l'environnement du serveur
            if (!getenv('APP_ENV') && !isset($_ENV['APP_ENV'])) {
                throw new RuntimeException('No .env file found and APP_ENV not set in environment variables. ' .
                    'Copy .env.example to .env and configure it.');
            }
        }

        $this->loaded = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->loaded) {
            $this->load();
        }

        return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
    }

    public function isDevelopment(): bool
    {
        return $this->get('APP_ENV', 'production') === 'development';
    }

    public function isProduction(): bool
    {
        return $this->get('APP_ENV', 'production') === 'production';
    }
}
