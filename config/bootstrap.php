<?php
/**
 * Fichier d'initialisation du projet.
 * PSR-1: Toutes les déclarations de symboles sont en haut.
 */

// ==========================================================
// 1. Déclaration des symboles (Fonctions, Classes, etc.)
// ==========================================================

/**
 * Nettoie un chemin de fichier pour assurer la cohérence entre les OS.
 */
function clean_file_path(string $path): string {
    // Logique originale de la fonction clean_file_path (ligne 8)
    return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

// ==========================================================
// 2. Logique d'exécution (Effets secondaires)
// ==========================================================

/**
 * Exécute la logique d'initialisation de l'environnement.
 */
function run_bootstrap() {
    // Initialisation des constantes (Effets secondaires, ligne 10 et suivantes)
    if (!defined('ROOT')) {
        define('ROOT', dirname(__DIR__));
    }
    if (!defined('APP_ROOT')) {
        define('APP_ROOT', ROOT . '/src');
    }
    
    // Chargement de l'autoloader Composer (Effet secondaire)
    require_once ROOT . '/vendor/autoload.php';
}

// ==========================================================
// 3. Lancement de l'exécution
// ==========================================================

// Appel unique de la fonction pour démarrer l'initialisation.
run_bootstrap();