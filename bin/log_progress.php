<?php

// bin/log_progress.php
// Simple CLI helper to append structured progress entries to docs/PROGRESS_JOURNAL.md
// Usage:
//  php bin/log_progress.php start --id=A.05 --phase="Phase B" --title="Run tests" --owner="alice" --desc="Run unit tests"
//  php bin/log_progress.php finish --id=A.05 --notes="All green" --artifacts="tests/" --commands="vendor\\bin\\phpunit.bat"


// ==========================================================
// 1. Déclaration des symboles (Fonctions)
// ==========================================================

/**
 * Parse les arguments de ligne de commande au format --key=value ou --flag
 * @param array<int, string> $argv Liste des arguments (sans le nom du script)
 * @return array<string, string|bool> Map des options parsées [nom => valeur]
 */
function parseArgs(array $argv): array
{
    // ... (logique parseArgs non modifiée)
    $out = [];
    foreach ($argv as $a) {
        if (str_starts_with($a, '--')) {
            $p = substr($a, 2);
            $parts = explode('=', $p, 2);
            $k = $parts[0];
            $v = $parts[1] ?? null;
            $out[$k] = $v ?? true;
        }
    }
    return $out;
}


// ==========================================================
// 2. Logique d'exécution (Effets secondaires)
// ==========================================================

/**
 * Fonction principale d'exécution du script.
 */
function run_log_progress_script(): void // <--- Ajout de : void
{
    global $argv; // Accès aux arguments globaux
    // ... (Reste de la logique d'exécution non modifiée, terminant par exit(0) ou similaire)
    if (PHP_SAPI !== 'cli') {
        fwrite(STDERR, "This script must be run from CLI\n");
        exit(1);
    }

    // Basic argument parsing
    $argv0 = array_shift($argv);
    $action = array_shift($argv) ?: '';

    $params = parseArgs($argv);
    // outputs
    $outJournal = $params['out-journal'] ?? __DIR__ . '/../docs/PROGRESS_JOURNAL.md';
    $outJson = is_string($params['out-json'] ?? null) ? $params['out-json'] : __DIR__ . '/../docs/progress_entries.jsonl';
    $journal = is_string($outJournal) ? $outJournal : __DIR__ . '/../docs/PROGRESS_JOURNAL.md';
    // flags
    $noMd = isset($params['no-md']) || isset($params['no_md']);
    // rotation size in bytes (default 5MB)
    $rotateSize = isset($params['rotate-size']) ? (int)$params['rotate-size'] : 5 * 1024 * 1024;
    if (!file_exists($journal)) {
        fwrite(STDERR, "Journal file not found: $journal\n");
        exit(1);
    }

    $now = (new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get())))->format(DateTime::ATOM);
    $id = $params['id'] ?? $params['ID'] ?? ($params['Id'] ?? null);
    $phase = $params['phase'] ?? 'Unspecified';
    $title = $params['title'] ?? 'Untitled';
    $owner = $params['owner'] ?? 'unknown';
    $desc = $params['desc'] ?? $params['description'] ?? '';
    $notes = $params['notes'] ?? '';
    $artifacts = $params['artifacts'] ?? '';
    $commands = $params['commands'] ?? '';
    if (!$id) {
        fwrite(STDERR, "Missing --id parameter (ex: --id=A.05)\n");
        exit(2);
    }

    // Build Markdown entry always (human readable)
    $entry = "\n---\n\n";
    if ($action === 'start') {
        $entry .= "### ID: {$id} — {$title}\n";
        $entry .= "Phase: {$phase}\n";
        $entry .= "Statut: in-progress\n";
        $entry .= "Démarré: {$now}\n";
        $entry .= "Responsable: {$owner}\n\n";
        $entry .= "Description:\n\n{$desc}\n\n";
        $entry .= "Artéfacts:\n\n- {$artifacts}\n\n";
        $entry .= "Commandes:\n\n````powershell\n{$commands}\n````\n\n";
        $entry .= "Vérification:\n\n- (à compléter)\n\n";
    } elseif ($action === 'finish') {
        // Append finish notes under the last matching ID by inserting near the top (before the marker)
        // Simpler approach: append a completion block referencing the ID
        $entry .= "### ID: {$id} — {$title} (completion)\n";
        $entry .= "Statut: completed\n";
        $entry .= "Terminé: {$now}\n";
        $entry .= "Responsable: {$owner}\n\n";
        if ($notes) {
            $entry .= "Notes:\n\n{$notes}\n\n";
        }
        if ($artifacts) {
            $entry .= "Artéfacts:\n\n- {$artifacts}\n\n";
        }
        if ($commands) {
            $entry .= "Commandes:\n\n````powershell\n{$commands}\n````\n\n";
        }
    } else {
        fwrite(STDOUT, "Unknown action. Use start or finish\n");
        exit(3);
    }

    // Append markdown entry
    // Append markdown entry unless disabled
    if (!$noMd) {
        file_put_contents($journal, $entry, FILE_APPEND | LOCK_EX);
    }

    // Also optionally write JSON line if requested (accept 'json' or 'jsonl')
    if (in_array(($params['format'] ?? ''), ['json', 'jsonl', 'md,json', 'json,md', 'jsonl,md', 'md,jsonl'], true)) {
        // prepare JSON object
        $obj = [
            'id' => $id,
            'phase' => $phase,
            'title' => $title,
            'status' => ($action === 'start') ? 'in-progress' : 'completed',
            'timestamp' => $now,
            'owner' => $owner,
            'description' => $desc,
            'artifacts' => array_filter(array_map('trim', explode(',', (string)$artifacts))),
            'commands' => array_filter(array_map('trim', explode(',', (string)$commands))),
            'notes' => $notes,
        ];
        $jsonLine = json_encode($obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        // rotate if file exists and is larger than threshold
        if (file_exists($outJson) && ($size = filesize($outJson)) !== false && $size > $rotateSize) {
            $content = file_get_contents($outJson);
            if ($content !== false) {
                $bak = $outJson . '.' . date('Ymd_His') . '.gz';
                $compressed = gzencode($content);
                if ($compressed !== false) {
                    file_put_contents($bak, $compressed);
                    // truncate original file (start fresh)
                    @unlink($outJson);
                }
            }
        }

        file_put_contents($outJson, $jsonLine, FILE_APPEND | LOCK_EX);
    }
    fwrite(STDOUT, "Entry appended" . ($noMd ? " (no-md)" : " to {$journal}") . ((in_array(($params['format'] ?? ''), ['json', 'jsonl'], true)) ? " and JSON written to {$outJson}" : "") . "\n");
    exit(0);
}


// ==========================================================
// 3. Lancement de l'exécution
// ==========================================================

// Appel unique de la fonction pour démarrer le script.
run_log_progress_script();
