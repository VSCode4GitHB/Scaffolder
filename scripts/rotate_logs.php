<?php
declare(strict_types=1);

/**
 * Script de rotation des logs JSONL
 * Garde les fichiers des X derniers jours et archive les plus anciens
 */

const RETENTION_DAYS = 30;
const LOGS_DIR = __DIR__ . '/../docs';
const ARCHIVE_DIR = __DIR__ . '/../docs/archives';

// Créer le dossier d'archives si nécessaire
if (!is_dir(ARCHIVE_DIR)) {
    mkdir(ARCHIVE_DIR, 0755, true);
}

// Trouver les fichiers JSONL
$files = glob(LOGS_DIR . '/progress_entries*.jsonl') ?: [];
$now = time();

foreach ($files as $file) {
    $mtime = filemtime($file);
    if ($mtime === false) {
        continue;
    }
    $days_old = floor(($now - $mtime) / (60 * 60 * 24));
    
    if ($days_old > RETENTION_DAYS) {
        $basename = basename($file);
        $archive_name = ARCHIVE_DIR . '/' . date('Y-m', $mtime) . '_' . $basename . '.gz';
        
        // Compresser et archiver
        $content = file_get_contents($file);
        file_put_contents("compress.zlib://$archive_name", $content);
        
        // Supprimer l'original
        unlink($file);
        
        echo sprintf(
            "Archived %s (%.1f days old) to %s\n",
            $basename,
            $days_old,
            basename($archive_name)
        );
    }
}

echo "Log rotation completed.\n";