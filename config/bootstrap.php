<?php

declare(strict_types=1);

use Dotenv\Dotenv;

// 1. Définition du chemin racine
define('ROOT_DIR', dirname(__DIR__));
// 2. Chargement de l'autoloader de Composer
require_once ROOT_DIR . '/vendor/autoload.php';
// 3. Initialisation de dotenv
$dotenv = Dotenv::createImmutable(ROOT_DIR);
// En production, ne pas lever d'erreur si .env n'existe pas
if (file_exists(ROOT_DIR . '/.env')) {
    $dotenv->load();
}

// 4. Validation des variables requises
$dotenv->required([
    'DB_DRIVER',
    'DB_NAME'
])->notEmpty();
// 5. Validation des valeurs spécifiques
$dotenv->required('DB_DRIVER')->allowedValues(['mysql', 'pgsql', 'mariadb', 'sqlite']);
$dotenv->required('APP_ENV')->allowedValues(['development', 'testing', 'production']);
// 6. Configuration de PHP
if (isset($_ENV['APP_TIMEZONE'])) {
    date_default_timezone_set($_ENV['APP_TIMEZONE']);
}

if ($_ENV['APP_ENV'] === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
}

// 7. Retour de l'instance dotenv pour utilisation éventuelle
return $dotenv;
