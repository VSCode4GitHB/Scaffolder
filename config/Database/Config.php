<?php

declare(strict_types=1);

namespace App\Config\Database;

use PDO;
use RuntimeException;

/**
 * Configuration de la base de données
 */
class Config
{
    public readonly string $dbName;
    public readonly PDO $connection;
    /** @var array<string, array<string, mixed>> */
    private array $diagnostics = [];
    public function __construct()
    {
        $this->dbName = $this->detectDbName();
        $this->validateDatabaseAccess();
        $this->connection = $this->createConnection();
    }

    /**
     * Retourne les informations de diagnostic de la dernière connexion
     */
    /**
     * @return array<string, array<string, mixed>>
     */
    public function getDiagnostics(): array
    {
        return $this->diagnostics;
    }

    /**
     * Vérifie si la base de données est accessible
     * @throws RuntimeException si la base n'est pas accessible
     */
    private function validateDatabaseAccess(): void
    {
        $dbDriver = strtolower($_ENV['DB_DRIVER'] ?? 'mysql');
        $host = $this->detectHost();
        $port = (int)($_ENV['DB_PORT'] ?? ($dbDriver === 'pgsql' ? 5432 : 3306));
// Test de connexion réseau basique
        if (@fsockopen($host, $port, $errno, $errstr, 5) === false) {
            throw new RuntimeException("La base de données n'est pas accessible sur $host:$port - " .
                "Erreur ($errno): $errstr");
        }

        $this->diagnostics['network_check'] = [
            'host' => $host,
            'port' => $port,
            'status' => 'ok',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    private function detectHost(): string
    {
        if (!empty($_ENV['DB_HOST'])) {
            return $_ENV['DB_HOST'];
        }
        $hostname = gethostname();
        if ($hostname === false) {
            return '127.0.0.1';
        }
        $host = gethostbyname($hostname);
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }
        return '127.0.0.1';
    }

    private function detectDbName(): string
    {
        return $_ENV['DB_NAME'] ?? 'cywsdb';
    }

    /**
     * Configure les options PDO spécifiques au driver
     */
    /**
     * @return array<int, mixed>
     */
    private function getDriverOptions(string $driver): array
    {
        /** @var array<int, mixed> */
        $common = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        return match ($driver) {
            'mysql', 'mariadb' => array_merge($common, [
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                PDO::MYSQL_ATTR_FOUND_ROWS => true,
            ]),
            'pgsql' => array_merge($common, [
                PDO::PGSQL_ATTR_DISABLE_PREPARES => false,
            ]),
                default => $common
        };
    }

    private function createConnection(): PDO
    {
        // 1. Validation du driver et configuration spécifique
        $dbDriver = strtolower($_ENV['DB_DRIVER'] ?? 'mysql');
        if (!in_array($dbDriver, ['mysql', 'pgsql', 'mariadb'], true)) {
            throw new RuntimeException("Driver de base de données non supporté: $dbDriver");
        }

        $this->diagnostics['connection_attempt'] = [
            'driver' => $dbDriver,
            'timestamp' => date('Y-m-d H:i:s')
        ];
// 2. Détection et validation de l'hôte
        $dbHost = $this->detectHost();
        $isValidIp = filter_var($dbHost, FILTER_VALIDATE_IP);
        $isValidHostname = filter_var($dbHost, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
        if (!$isValidIp && !$isValidHostname) {
            throw new RuntimeException("Hôte de base de données invalide: $dbHost");
        }

        // 3. Configuration du port selon le driver avec validation
            $defaultPort = match ($dbDriver) {
                'pgsql' => 5432,
              default => 3306
            };
        $dbPort = (int)($_ENV['DB_PORT'] ?? $defaultPort);
        if ($dbPort <= 0 || $dbPort > 65535) {
            throw new RuntimeException("Port invalide: $dbPort");
        }

        // 4. Validation des credentials
        $dbUser = $_ENV['DB_USER'] ?? 'webmaster@cyws';
        $dbPass = $_ENV['DB_PASS'] ?? 'cy025@ws_PJ';
        if (empty($dbUser) || empty($dbPass)) {
            throw new RuntimeException("Credentials de base de données manquants");
        }

        // 5. Configuration du charset avec validation
        $dbCharset = strtolower($_ENV['DB_CHARSET'] ?? 'utf8mb4');
        if (!in_array($dbCharset, ['utf8', 'utf8mb4', 'latin1'], true)) {
            throw new RuntimeException("Charset non supporté: $dbCharset");
        }

        // 6. Construction du DSN selon le driver
        $dsn = match ($dbDriver) {
            'pgsql' => "pgsql:host=$dbHost;port=$dbPort;dbname={$this->dbName}",
            'mysql', 'mariadb' => "mysql:host=$dbHost;port=$dbPort;dbname={$this->dbName};charset=$dbCharset",
        };
        try {
            $connection = new PDO($dsn, $dbUser, $dbPass, $this->getDriverOptions($dbDriver));
        // Enregistrement du succès dans les diagnostics
            $this->diagnostics['connection_success'] = [
                'driver' => $dbDriver,
                'host' => $dbHost,
                'database' => $this->dbName,
                'charset' => $dbCharset,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            return $connection;
        } catch (\PDOException $e) {
        // Enregistrement de l'erreur dans les diagnostics
            $this->diagnostics['connection_error'] = [
                'driver' => $dbDriver,
                'host' => $dbHost,
                'database' => $this->dbName,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
            $errorMessage = sprintf(
                "Erreur de connexion (%s): [%s] %s\nHôte: %s, Base: %s",
                $dbDriver,
                $e->getCode(),
                $e->getMessage(),
                $dbHost,
                $this->dbName
            );
            throw new RuntimeException($errorMessage);
        }
    }
}
