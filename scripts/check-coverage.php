#!/usr/bin/env php
<?php
/**
 * Coverage checker: reads coverage.xml and ensures coverage is >= threshold
 * Usage: php scripts/check-coverage.php <threshold_percent> [coverage.xml path]
 */

$threshold = isset($argv[1]) ? (int)$argv[1] : 70;
$coveragePath = isset($argv[2]) ? $argv[2] : 'coverage.xml';

if (!file_exists($coveragePath)) {
    fprintf(STDERR, "ERROR: Coverage file not found: %s\n", $coveragePath);
    exit(1);
}

try {
    $xml = new SimpleXMLElement(file_get_contents($coveragePath));

    // Prefer Clover project-level metrics (PHPUnit 10)
    $projectMetrics = $xml->xpath('/coverage/project/metrics');

    $covered = 0;
    $total   = 0;

    if (!empty($projectMetrics)) {
        $m = $projectMetrics[0];
        // Attributes: statements, coveredstatements
        $total   = (int)($m['statements'] ?? 0);
        $covered = (int)($m['coveredstatements'] ?? 0);
    } else {
        // Fallback: sum class-level metrics if present
        $classMetrics = $xml->xpath('//file/class/metrics');
        if (!empty($classMetrics)) {
            foreach ($classMetrics as $m) {
                $total   += (int)($m['statements'] ?? 0);
                $covered += (int)($m['coveredstatements'] ?? 0);
            }
        } else {
            // Last resort: sum file-level metrics
            $fileMetrics = $xml->xpath('//file/metrics');
            if (!empty($fileMetrics)) {
                foreach ($fileMetrics as $m) {
                    $total   += (int)($m['statements'] ?? 0);
                    $covered += (int)($m['coveredstatements'] ?? 0);
                }
            }
        }
    }

    if ($total === 0) {
        fprintf(STDERR, "ERROR: No statements found in coverage (parsed total=0)\n");
        exit(1);
    }

    $coverage = $total > 0 ? ($covered / $total) * 100 : 0.0;

    printf("Coverage: %.2f%% (%d/%d statements)\n", $coverage, $covered, $total);
    printf("Threshold: %d%%\n", $threshold);

    if ($coverage >= $threshold) {
        printf("✓ Coverage meets threshold\n");
        exit(0);
    } else {
        printf("✗ Coverage below threshold (%.2f%% < %d%%)\n", $coverage, $threshold);
        exit(1);
    }
} catch (Exception $e) {
    fprintf(STDERR, "ERROR: %s\n", $e->getMessage());
    exit(1);
}
