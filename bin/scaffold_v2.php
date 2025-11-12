<?php

declare(strict_types=1);

// bin/scaffold_v2.php
// Usage: php bin/scaffold_v2.php [TABLE_NAME || ENTITY_NAME] --table=[TABLE_NAME || ENTITY_NAME] --force

namespace Scaffold;

use PDO;
use RuntimeException;
use App\Config\Database\Config;

const ROOT = __DIR__ . '/..';
// 1) Lecture arg

$options = getopt('', ['table:', 'force', 'skip-views', 'help', 'no-session-check', 'dry-run']);
$table = is_string($options['table'] ?? null) ? $options['table'] : (is_string($argv[1] ?? null) ? $argv[1] : null);
if (isset($options['help']) || !$table) {
    fwrite(STDERR, "Usage: php bin/scaffold.php <table> [options]\n");
    fwrite(STDERR, "Options:\n");
    fwrite(STDERR, "  --table=name        Table name (required)\n");
    fwrite(STDERR, "  --force             Overwrite existing files\n");
    fwrite(STDERR, "  --skip-views        Skip controller/view generation\n");
    fwrite(STDERR, "  --no-session-check  Do not auto-insert session_start() in generated layout\n");
    fwrite(STDERR, "  --help              Show this help\n");
    exit(1);
}

$force = isset($options['force']);
$skipViews = isset($options['skip-views']);
$noSessionCheck = isset($options['no-session-check']);
// If provided, do not write files; only show what would be created
$dryRun = isset($options['dry-run']);
// 3) Connexion PDO
require_once ROOT . '/vendor/autoload.php';
$dbConfig = new Config();
$pdo = $dbConfig->connection;
// 4) Colonnes
$sql = "SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, EXTRA,
               COLUMN_COMMENT, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table
        ORDER BY ORDINAL_POSITION";
$cols = $pdo->prepare($sql);
$cols->execute(['schema' => $dbConfig->dbName, 'table' => $table]);
$columns = $cols->fetchAll();
if (!$columns) {
    throw new RuntimeException("Table '$table' introuvable.");
}

// 5) Foreign keys
$foreignKeys = [];
try {
    $fkSql = "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
              FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
              WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table
                AND REFERENCED_TABLE_NAME IS NOT NULL";
    $fkStmt = $pdo->prepare($fkSql);
    $fkStmt->execute(['schema' => $dbConfig->dbName, 'table' => $table]);
    $foreignKeys = $fkStmt->fetchAll();
} catch (\Exception $e) {
    fwrite(STDERR, "Avertissement: impossible de récupérer les FK\n");
}

// 6) PK
$primaryKeys = [];
$autoIncrement = false;
$aiPkName = null;
foreach ($columns as $c) {
    if ($c['COLUMN_KEY'] === 'PRI') {
        $primaryKeys[] = $c['COLUMN_NAME'];
        if (strpos((string)$c['EXTRA'], 'auto_increment') !== false) {
            $autoIncrement = true;
            $aiPkName = $c['COLUMN_NAME'];
        }
    }
}
$compositePk = count($primaryKeys) > 1;
if ($compositePk) {
    fwrite(STDERR, "Warning: PK composite détectée: " . implode(',', $primaryKeys) . "\n");
}
if (!$primaryKeys) {
    throw new RuntimeException("Aucune PK détectée");
}
$pk = $primaryKeys[0];

// 7) Helpers
/**
 * Parse les valeurs d'une colonne ENUM/SET MySQL
 * @param string $columnType Type de la colonne (ex: "enum('a','b','c')")
 * @return array<int, string> Liste des valeurs possibles
 */
function parseEnumValues(string $columnType): array
{

    if (!preg_match("/^(enum|set)\((.*)\)$/i", $columnType, $m)) {
        return [];
    }
    $inside = $m[2];
    $vals = [];
    if (preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $inside, $m2)) {
        foreach ($m2[1] as $v) {
            $vals[] = stripcslashes($v);
        }
    }
    return $vals;
}
/**
 * Write or simulate writing a generated file
 *
 * @param string $path
 * @param string $content
 * @param bool $force
 * @param bool $dryRun
 */
function writeGeneratedFile(string $path, string $content, bool $force = false, bool $dryRun = false): void
{
    if ($dryRun) {
        fwrite(STDOUT, "DRY-RUN: would write $path\n");
        return;
    }

    if (file_exists($path) && !$force) {
        throw new RuntimeException("Le fichier existe déjà et --force n'est pas fourni: $path");
    }

    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    file_put_contents($path, $content);
}
/**
 * Détermine les types PHP et autres métadonnées pour une colonne
 * @param array<string, mixed> $col Définition de la colonne depuis INFORMATION_SCHEMA
 * @return array{0: string, 1: string, 2: bool, 3: string, 4: string} Tuple [type PHP, type SQL, nullable, commentaire, type colonne]
 */
function phpType(array $col): array
{

    $dataType = strtolower((string)($col['DATA_TYPE'] ?? ''));
    $colType  = strtolower((string)($col['COLUMN_TYPE'] ?? ''));
    $nullable = ($col['IS_NULLABLE'] === 'YES');
    if (!is_string($dataType) || !is_string($colType)) {
        return ['string', 'string', $nullable, '', ''];
    }
    $map = match (true) {
        $dataType === 'tinyint' && preg_match('/tinyint\(1\)/', $colType) => ['bool','bool'],
        in_array($dataType, ['int','integer','smallint','mediumint','bigint','tinyint']) => ['int','int'],
        in_array($dataType, ['decimal','float','double','real']) => ['float','float'],
        in_array($dataType, ['date','datetime','timestamp']) => ['\\DateTimeImmutable','datetime'],
        $dataType === 'time' => ['string','time'],
        $dataType === 'year' => ['int','year'],
        $dataType === 'json' => ['array','json'],
        in_array($dataType, ['set','enum']) => ['string','enum'],
        default => ['string','string'],
    };
    $php = $map[0];
    $kind = $map[1];
    $comment = is_string($col['COLUMN_COMMENT'] ?? null) ? $col['COLUMN_COMMENT'] : '';
    $columnType = is_string($col['COLUMN_TYPE'] ?? null) ? $col['COLUMN_TYPE'] : '';
    return [$nullable ? ('?' . $php) : $php, $kind, $nullable, $comment, $columnType];
}
function normalizeColumnForCode(string $name): string
{

    $trans = @iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: $name;
    $clean = preg_replace('/[^a-z0-9_]+/i', '_', $trans);
    $clean = trim(preg_replace('/_+/', '_', $clean), '_');
    if ($clean === '' || preg_match('/^[0-9]/', $clean)) {
        $clean = 'f_' . $clean;
    }
    return implode('', array_map(fn($p)=>ucfirst(strtolower($p)), explode('_', $clean)));
}
function classNameFromTable(string $t): string
{
    $t = preg_replace('/[^a-z0-9_]/i', '_', $t);
    return implode('', array_map(fn($p)=>ucfirst(strtolower($p)), explode('_', $t)));
}

// 8) Vérification des fichiers existants
function ensureWritable(string $path, bool $force): void
{
    if (file_exists($path) && !$force) {
        throw new RuntimeException("Le fichier existe déjà et --force n'est pas fourni: $path");
    }
}
function assertNoUnresolvedPlaceholders(string $content, string $path): void
{
    if (preg_match('/\{\$[A-Za-z0-9_]+\}/', $content)) {
        fwrite(STDERR, "Warning: contenu généré pour $path contient des placeholders non résolus\n");
    }
}

$Class = classNameFromTable($table);
$PkMethod = normalizeColumnForCode($pk);
$entityDir   = ROOT . "/src/Domain/Entity";
$hydratorDir = ROOT . "/src/Infrastructure/Hydration";
$repoIfaceDir = ROOT . "/src/Domain/Repository";
$repoImplDir = ROOT . "/src/Infrastructure/Repository";
$serviceDir  = ROOT . "/src/Domain/Service";
$ctrlDir     = ROOT . "/src/Application/Controller";
$layoutDir   = ROOT . "/templates/layouts";
$tplDir      = ROOT . "/templates/" . strtolower($table);
$routesDir   = ROOT . "/config";
$testDir     = ROOT . "/tests/Domain/Entity";
$factoryDir  = ROOT . "/tests/Factories";
@mkdir($entityDir, 0777, true);
@mkdir($hydratorDir, 0777, true);
@mkdir($repoIfaceDir, 0777, true);
@mkdir($repoImplDir, 0777, true);
@mkdir($serviceDir, 0777, true);
if (!$skipViews) {
    @mkdir($ctrlDir, 0777, true);
    @mkdir($layoutDir, 0777, true);
    @mkdir($tplDir, 0777, true);
}
@mkdir($routesDir, 0777, true);
@mkdir($testDir, 0777, true);
@mkdir($factoryDir, 0777, true);
$entityPath    = "$entityDir/{$Class}.php";
$interfacePath = "$entityDir/{$Class}Interface.php";
$hydratorPath  = "$hydratorDir/{$Class}Hydrator.php";
$repoIfacePath = "$repoIfaceDir/{$Class}RepositoryInterface.php";
$repoImplPath  = "$repoImplDir/{$Class}Repository.php";
$servicePath   = "$serviceDir/{$Class}Service.php";
$ctrlPath      = "$ctrlDir/{$Class}Controller.php";
$pathsToCheck = [$entityPath, $interfacePath, $hydratorPath, $repoIfacePath, $repoImplPath, $servicePath];
if (!$skipViews) {
    $pathsToCheck[] = $ctrlPath;
    $pathsToCheck[] = "$layoutDir/base.php";
    $pathsToCheck[] = "$tplDir/index.php";
    $pathsToCheck[] = "$tplDir/show.php";
    $pathsToCheck[] = "$tplDir/form.php";
}
foreach ($pathsToCheck as $p) {
    ensureWritable($p, $force);
}

// 9) Préparer champs
$fields = [];
foreach ($columns as $col) {
    [$phpT,$kind,$nullable,$comment,$columnType] = phpType($col);
    $fields[] = [
        'name'       => $col['COLUMN_NAME'],
        'method'     => normalizeColumnForCode($col['COLUMN_NAME']),
        'phpType'    => $phpT,
        'kind'       => $kind,
        'nullable'   => $nullable,
        'comment'    => $comment,
        'maxLength'  => $col['CHARACTER_MAXIMUM_LENGTH'] ?? null,
        'precision'  => $col['NUMERIC_PRECISION'] ?? null,
        'scale'      => $col['NUMERIC_SCALE'] ?? null,
        'enumValues' => parseEnumValues($columnType)
    ];
}

// 10) Générer ENTITÉ
$propsBlock       = implode("\n", array_map(fn($f) => "    private {$f['phpType']} \${$f['name']};" . ($f['comment'] ? " // {$f['comment']}" : ''), $fields));
$constructorBlock = implode(",\n", array_map(fn($f) => "        {$f['phpType']} \${$f['name']}", $fields));
$getsBlock        = implode("\n", array_map(fn($f) => "    public function get{$f['method']}(): {$f['phpType']} { return \$this->{$f['name']}; }", $fields));
$setsBlock        = implode("\n", array_map(function ($f) use ($primaryKeys) {

    if (!in_array($f['name'], $primaryKeys, true)) {
        return "    public function set{$f['method']}({$f['phpType']} \${$f['name']}): void { \$this->{$f['name']} = \${$f['name']}; }";
    } else {
        $ret = ltrim($f['phpType'], '?');
        return "    public function with{$f['method']}({$ret} \${$f['name']}): self { \$new = clone \$this; \$new->{$f['name']} = \${$f['name']}; return \$new; }";
    }
}, $fields));
$toArrayBlock     = implode(",\n", array_map(fn($f) => "            '{$f['name']}' => \$this->{$f['name']}", $fields));
// fromArray expressions par champ
$fromArrayArgs = [];
foreach ($fields as $f) {
    $n = $f['name'];
    $k = $f['kind'];
    $nullable = $f['nullable'];
    switch ($k) {
        case 'bool':
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      $expr = "(isset(\$data['{$n}']) ? (bool)\$data['{$n}'] : null)";
            $expr = $nullable ? $expr : "({$expr}) ?? false";

            break;
        case 'int':
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      $expr = "(isset(\$data['{$n}']) ? (int)\$data['{$n}'] : null)";
            $expr = $nullable ? $expr : "({$expr}) ?? 0";

            break;
        case 'float':
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      $expr = "(isset(\$data['{$n}']) ? (float)\$data['{$n}'] : null)";
            $expr = $nullable ? $expr : "({$expr}) ?? 0.0";

            break;
        case 'datetime':
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      $expr = "(!empty(\$data['{$n}']) ? (\$data['{$n}'] instanceof \\DateTimeInterface ? new \\DateTimeImmutable(\$data['{$n}']->format(DATE_ATOM)) : new \\DateTimeImmutable((string)\$data['{$n}'])) : null)";
            $expr = $nullable ? $expr : "({$expr}) ?? throw new \\InvalidArgumentException('{$n} est requis')";

            break;
        case 'time':
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      $expr = "(isset(\$data['{$n}']) ? (string)\$data['{$n}'] : null)";
            $expr = $nullable ? $expr : "({$expr}) ?? throw new \\InvalidArgumentException('{$n} est requis')";

            break;
        case 'json':
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      $expr = "(!empty(\$data['{$n}']) ? (is_array(\$data['{$n}']) ? \$data['{$n}'] : json_decode((string)\$data['{$n}'], true, 512, JSON_THROW_ON_ERROR)) : " . ($nullable ? 'null' : '[]') . ")";

            break;
        case 'enum':
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      $expr = "(isset(\$data['{$n}']) ? (string)\$data['{$n}'] : null)";
            $expr = $nullable ? $expr : "({$expr}) ?? ''";

            break;
        default:
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      $expr = "(isset(\$data['{$n}']) ? (string)\$data['{$n}'] : null)";
            $expr = $nullable ? $expr : "({$expr}) ?? ''";

            break;
    }
    $fromArrayArgs[] = "            {$expr}";
}
$fromArrayBlock   = implode(",\n", $fromArrayArgs);
$entityCode = <<<PHP
<?php
declare(strict_types=1);

namespace App\Domain\Entity;

/**
 * Entity for table {$table}
 */
class {$Class} implements {$Class}Interface
{
$propsBlock

    public function __construct(
$constructorBlock
    ) {}

$getsBlock

$setsBlock

    public function toArray(): array
    {
        return [
$toArrayBlock
        ];
    }

    public static function fromArray(array \$data): self
    {
        return new self(
$fromArrayBlock
        );
    }
}
PHP;
assertNoUnresolvedPlaceholders($entityCode, $entityPath);
    writeGeneratedFile($entityPath, $entityCode, $force, $dryRun);
// 11) Interface
$interfaceCode = <<<PHP
<?php
declare(strict_types=1);

namespace App\Domain\Entity;

interface {$Class}Interface
{
    public function toArray(): array;
    public static function fromArray(array \$data): self;
}
PHP;
assertNoUnresolvedPlaceholders($interfaceCode, $interfacePath);
    writeGeneratedFile($interfacePath, $interfaceCode, $force, $dryRun);
// 12) HYDRATOR
$fromRow = [];
$toRow   = [];
foreach ($fields as $f) {
    $expr = "\$row['{$f['name']}']";
    $to   = "\$e->get{$f['method']}()";
    switch ($f['kind']) {
        case 'bool':
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      $from = "(isset($expr) ? (bool)(int)$expr : null)";
            $toExpr = "({$to} === null ? null : ({$to} ? 1 : 0))";

            break;
        case 'int':
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      $from = "(isset($expr) ? (int)$expr : null)";
            $toExpr = $to;

            break;
        case 'float':
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      $from = "(isset($expr) ? (float)$expr : null)";
            $toExpr = $to;

            break;
        case 'datetime':
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      $from = "(!empty($expr) ? new \\DateTimeImmutable((string)$expr) : null)";
            $toExpr = "({$to} ? {$to}->format('Y-m-d H:i:s') : null)";

            break;
        case 'time':
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      $from = "(isset($expr) ? (string)$expr : null)";
            $toExpr = $to;

            break;
        case 'json':
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      $from = "(!empty($expr) ? json_decode((string)$expr, true, 512, JSON_THROW_ON_ERROR) : " . ($f['nullable'] ? 'null' : '[]') . ")";
            $toExpr = "({$to} === null ? null : json_encode({$to}, JSON_THROW_ON_ERROR))";

            break;
        default:
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      $from = "(isset($expr) ? (string)$expr : null)";
            $toExpr = $to;
    }
    $defaultValue = match ($f['kind']) {
        'bool' => 'false',
        'int' => '0',
        'float' => '0.0',
        'string', 'enum', 'time' => "''",
        'datetime' => 'null',
        'json' => $f['nullable'] ? 'null' : '[]',
        default => 'null'
    };
    $fromRow[] = "            '{$f['name']}' => " . ($f['nullable'] ? $from : "($from) ?? $defaultValue") . ",";
    $toRow[]   = "            '{$f['name']}' => $toExpr,";
}
$fromRowBlock = implode("\n", $fromRow);
$toRowBlock   = implode("\n", $toRow);
$hydratorCode = <<<PHP
<?php
declare(strict_types=1);

namespace App\Infrastructure\Hydration;

use App\Domain\Entity\\{$Class};
use App\Domain\Entity\\{$Class}Interface;

final class {$Class}Hydrator
{
    public static function fromRow(array \$row): {$Class}Interface
    {
        return new {$Class}(
$fromRowBlock
        );
    }

    public static function toRow({$Class}Interface \$e): array
    {
        return [
$toRowBlock
        ];
    }

    /**
     * @return array<{$Class}Interface>
     */
    public static function fromRows(array \$rows): array
    {
        return array_map([self::class, 'fromRow'], \$rows);
    }
}
PHP;
assertNoUnresolvedPlaceholders($hydratorCode, $hydratorPath);
    writeGeneratedFile($hydratorPath, $hydratorCode, $force, $dryRun);
// 13) REPOSITORY
$colNames       = array_map(fn($f) => $f['name'], $fields);
$colListSelect  = implode(', ', $colNames);
// Colonnes pour INSERT/UPDATE
$insertColNames = $colNames;
if ($autoIncrement && $aiPkName !== null) {
    $insertColNames = array_values(array_filter($insertColNames, fn($n) => $n !== $aiPkName));
}
$updateColNames = array_values(array_filter($colNames, fn($n) => $n !== $pk));
$placeholdersInsert = implode(', ', array_map(fn($n) => ":$n", $insertColNames));
$colListInsert      = implode(', ', $insertColNames);
$updates            = implode(",\n                ", array_map(fn($n) => "$n = :$n", $updateColNames));
$repoIfaceCode = <<<PHP
<?php
declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\\{$Class}Interface;

interface {$Class}RepositoryInterface
{
    public function find(\$id): ?{$Class}Interface;

    public function findAll(): array;

    public function findBy(array \$criteria, ?array \$orderBy = null, ?int \$limit = null, ?int \$offset = null): array;

    public function findOneBy(array \$criteria): ?{$Class}Interface;

    public function save({$Class}Interface \$entity): {$Class}Interface;

    public function delete({$Class}Interface \$entity): void;

    public function count(array \$criteria = []): int;
}
PHP;
assertNoUnresolvedPlaceholders($repoIfaceCode, $repoIfacePath);
    writeGeneratedFile($repoIfacePath, $repoIfaceCode, $force, $dryRun);
$isInsertLogic = $autoIncrement
    ? "        \$isInsert = (empty(\$data['{$pk}']) || \$data['{$pk}'] === 0 || \$data['{$pk}'] === '0');"
    : "        \$isInsert = (\$this->find(\$data['{$pk}']) === null);";
$repoImplCode = <<<PHP
<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entity\\{$Class}Interface;
use App\Domain\Repository\\{$Class}RepositoryInterface;
use App\Infrastructure\Hydration\\{$Class}Hydrator;
use PDO;

class {$Class}Repository implements {$Class}RepositoryInterface
{
    public function __construct(private readonly PDO \$pdo) {}

    public function find(\$id): ?{$Class}Interface
    {
        \$stmt = \$this->pdo->prepare("SELECT {$colListSelect} FROM {$table} WHERE {$pk} = :id");
        \$stmt->execute(['id' => \$id]);
        \$row = \$stmt->fetch();

        return \$row ? {$Class}Hydrator::fromRow(\$row) : null;
    }

    public function findAll(): array
    {
        \$sql = "SELECT {$colListSelect} FROM {$table} ORDER BY {$pk} ASC";
        \$rows = \$this->pdo->query(\$sql)->fetchAll();

        return {$Class}Hydrator::fromRows(\$rows);
    }

    public function findBy(array \$criteria, ?array \$orderBy = null, ?int \$limit = null, ?int \$offset = null): array
    {
        \$allowed = 
PHP
. var_export($colNames, true) . ";\n" . <<<PHP
        \$whereParts = [];
        \$params = [];

        foreach (\$criteria as \$field => \$value) {
            if (!in_array(\$field, \$allowed, true)) {
                continue;
            }
            \$whereParts[] = "\$field = :\$field";
            \$params[\$field] = \$value;
        }

        \$sql = "SELECT {$colListSelect} FROM {$table}";

        if (!empty(\$whereParts)) {
            \$sql .= " WHERE " . implode(' AND ', \$whereParts);
        }

        if (\$orderBy) {
            \$orderParts = [];
            foreach (\$orderBy as \$field => \$direction) {
                if (!in_array(\$field, \$allowed, true)) {
                    continue;
                }
                \$dir = (strtoupper((string)\$direction) === 'DESC') ? 'DESC' : 'ASC';
                \$orderParts[] = "\$field \$dir";
            }
            if (!empty(\$orderParts)) {
                \$sql .= " ORDER BY " . implode(', ', \$orderParts);
            }
        }

        if (\$limit !== null) {
            \$sql .= " LIMIT " . (int)\$limit;
        }

        if (\$offset !== null) {
            \$sql .= " OFFSET " . (int)\$offset;
        }

        \$stmt = \$this->pdo->prepare(\$sql);
        \$stmt->execute(\$params);
        \$rows = \$stmt->fetchAll();

        return {$Class}Hydrator::fromRows(\$rows);
    }

    public function findOneBy(array \$criteria): ?{$Class}Interface
    {
        \$results = \$this->findBy(\$criteria, null, 1);
        return !empty(\$results) ? \$results[0] : null;
    }

    public function save({$Class}Interface \$entity): {$Class}Interface
    {
        \$data = {$Class}Hydrator::toRow(\$entity);

$isInsertLogic

        if (\$isInsert) {
            \$this->insert(\$data);
PHP
. ($autoIncrement ? "            \$lastId = (int)\$this->pdo->lastInsertId(); if (\$lastId > 0) { \$entity = \$entity->with{$PkMethod}(\$lastId); }\n" : "") . <<<PHP
        } else {
            \$this->update(\$data);
        }

        return \$entity;
    }

    private function insert(array \$data): void
    {
        \$sql = "INSERT INTO {$table} ({$colListInsert}) VALUES ({$placeholdersInsert})";
        \$payload = array_intersect_key(\$data, array_flip(
PHP
. var_export($insertColNames, true) . "));\n" . <<<PHP
        \$this->pdo->prepare(\$sql)->execute(\$payload);
    }

    private function update(array \$data): void
    {
        \$sql = "UPDATE {$table} SET
                {$updates}
            WHERE {$pk} = :{$pk}";
        \$payload = array_intersect_key(\$data, array_flip(
PHP
. var_export(array_merge($updateColNames, [$pk]), true) . "));\n" . <<<PHP
        \$this->pdo->prepare(\$sql)->execute(\$payload);
    }

    public function delete({$Class}Interface \$entity): void
    {
        \$this->pdo->prepare("DELETE FROM {$table} WHERE {$pk} = :id")
                 ->execute(['id' => \$entity->get{$PkMethod}()]);
    }

    public function count(array \$criteria = []): int
    {
        \$allowed = 
PHP
. var_export($colNames, true) . ";\n" . <<<PHP
        \$whereParts = [];
        \$params = [];

        foreach (\$criteria as \$field => \$value) {
            if (!in_array(\$field, \$allowed, true)) {
                continue;
            }
            \$whereParts[] = "\$field = :\$field";
            \$params[\$field] = \$value;
        }

        \$sql = "SELECT COUNT(*) FROM {$table}";

        if (!empty(\$whereParts)) {
            \$sql .= " WHERE " . implode(' AND ', \$whereParts);
        }

        \$stmt = \$this->pdo->prepare(\$sql);
        \$stmt->execute(\$params);

        return (int)\$stmt->fetchColumn();
    }
}
PHP;
assertNoUnresolvedPlaceholders($repoImplCode, $repoImplPath);
    writeGeneratedFile($repoImplPath, $repoImplCode, $force, $dryRun);
// 14) SERVICE
$serviceCode = <<<PHP
<?php
declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\\{$Class};
use App\Domain\Entity\\{$Class}Interface;
use App\Domain\Repository\\{$Class}RepositoryInterface;

class {$Class}Service
{
    public function __construct(private {$Class}RepositoryInterface \$repository) {}

    public function create{$Class}(array \$data): {$Class}Interface
    {
        \$entity = {$Class}::fromArray(\$data);
        \$entity = \$this->repository->save(\$entity);
        return \$entity;
    }

    public function update{$Class}(\$id, array \$data): ?{$Class}Interface
    {
        \$entity = \$this->repository->find(\$id);
        if (!\$entity) {
            return null;
        }

        foreach (\$data as \$key => \$value) {
            if (\$key === '{$pk}') { continue; }
            \$setter = 'set' . implode('', array_map('ucfirst', preg_split('/_+/', (string)\$key)));
            if (method_exists(\$entity, \$setter)) {
                \$entity->\$setter(\$value);
            }
        }

        \$entity = \$this->repository->save(\$entity);
        return \$entity;
    }

    public function delete{$Class}(\$id): bool
    {
        \$entity = \$this->repository->find(\$id);
        if (!\$entity) {
            return false;
        }
        \$this->repository->delete(\$entity);
        return true;
    }

    public function get{$Class}(\$id): ?{$Class}Interface
    {
        return \$this->repository->find(\$id);
    }

    public function getAll{$Class}s(): array
    {
        return \$this->repository->findAll();
    }

    public function search{$Class}s(array \$criteria, ?array \$orderBy = null, ?int \$limit = null, ?int \$offset = null): array
    {
        return \$this->repository->findBy(\$criteria, \$orderBy, \$limit, \$offset);
    }
}
PHP;
assertNoUnresolvedPlaceholders($serviceCode, $servicePath);
    writeGeneratedFile($servicePath, $serviceCode, $force, $dryRun);
// 15) CONTROLLER + VUES
if (!$skipViews) {
    $ctrlNotes = $compositePk ? "    /* NOTE: table with composite PK: " . implode(', ', $primaryKeys) . ". Adaptez find/update/delete pour des identifiants composites si nécessaire. */\n\n" : "";
    $ctrlCode = <<<PHP
<?php
declare(strict_types=1);

namespace App\Application\Controller;

use App\Domain\Service\\{$Class}Service;
use App\Infrastructure\Hydration\\{$Class}Hydrator;
use Throwable;

class {$Class}Controller
{
{$ctrlNotes}    public function __construct(private readonly {$Class}Service \$service) {}

    public function index(): string
    {
        try {
            \$list = \$this->service->getAll{$Class}s();

            ob_start();
            require __DIR__ . '/../../../templates/' . strtolower('{$table}') . '/index.php';
            return (string) ob_get_clean();
        } catch (Throwable \$e) {
            http_response_code(500);
            return 'Erreur interne du serveur';
        }
    }

    public function show(string \$id): string
    {
        try {
            \$entity = \$this->service->get{$Class}((int) \$id);

            if (!\$entity) {
                http_response_code(404);
                return 'Non trouvé';
            }

            ob_start();
            require __DIR__ . '/../../../templates/' . strtolower('{$table}') . '/show.php';
            return (string) ob_get_clean();
        } catch (Throwable \$e) {
            http_response_code(500);
            return 'Erreur interne du serveur';
        }
    }

    public function create(): string
    {
        try {
            \$errors = [];
            \$data = \$_POST ?? [];

            ob_start();
            require __DIR__ . '/../../../templates/' . strtolower('{$table}') . '/form.php';
            return (string) ob_get_clean();
        } catch (Throwable \$e) {
            http_response_code(500);
            return 'Erreur interne du serveur';
        }
    }

    public function store(): string
    {
        try {
            if (!\$this->isCsrfValid(\$_POST['_token'] ?? '')) {
                http_response_code(403);
                return 'CSRF invalide';
            }

            \$data = \$_POST;
            \$errors = \$this->validateData(\$data);

            if (!empty(\$errors)) {
                http_response_code(422);
                ob_start();
                require __DIR__ . '/../../../templates/' . strtolower('{$table}') . '/form.php';
                return (string) ob_get_clean();
            }

            \$entity = \$this->service->create{$Class}(\$data);

            header('Location: /' . strtolower('{$table}') . '/' . \$entity->get{$PkMethod}());
            return '';
        } catch (Throwable \$e) {
            http_response_code(500);
            return 'Erreur interne du serveur';
        }
    }

    public function edit(string \$id): string
    {
        try {
            \$entity = \$this->service->get{$Class}((int) \$id);

            if (!\$entity) {
                http_response_code(404);
                return 'Non trouvé';
            }

            \$errors = [];
            \$data = {$Class}Hydrator::toRow(\$entity);

            ob_start();
            require __DIR__ . '/../../../templates/' . strtolower('{$table}') . '/form.php';
            return (string) ob_get_clean();
        } catch (Throwable \$e) {
            http_response_code(500);
            return 'Erreur interne du serveur';
        }
    }

    public function update(string \$id): string
    {
        try {
            if (!\$this->isCsrfValid(\$_POST['_token'] ?? '')) {
                http_response_code(403);
                return 'CSRF invalide';
            }

            \$data = \$_POST;
            \$errors = \$this->validateData(\$data, (int) \$id);

            if (!empty(\$errors)) {
                http_response_code(422);
                ob_start();
                require __DIR__ . '/../../../templates/' . strtolower('{$table}') . '/form.php';
                return (string) ob_get_clean();
            }

            \$entity = \$this->service->update{$Class}((int) \$id, \$data);

            if (!\$entity) {
                http_response_code(404);
                return 'Non trouvé';
            }

            header('Location: /' . strtolower('{$table}') . '/' . \$id);
            return '';
        } catch (Throwable \$e) {
            http_response_code(500);
            return 'Erreur interne du serveur';
        }
    }

    public function delete(string \$id): string
    {
        try {
            if (!\$this->isCsrfValid(\$_POST['_token'] ?? '')) {
                http_response_code(403);
                return 'CSRF invalide';
            }

            \$success = \$this->service->delete{$Class}((int) \$id);

            if (!\$success) {
                http_response_code(404);
                return 'Non trouvé';
            }

            header('Location: /' . strtolower('{$table}'));
            return '';
        } catch (Throwable \$e) {
            http_response_code(500);
            return 'Erreur interne du serveur';
        }
    }

    private function validateData(array \$data, ?int \$id = null): array
    {
        \$errors = [];
PHP;
// Règles dynamiques (incl. enum values + PK immuable si non composite)
    $rulesLines = [];
    foreach ($fields as $f) {
        $name = $f['name'];
        if (!$f['nullable']) {
            $rulesLines[] = "        if (!array_key_exists('{$name}', \$data) || (\$data['{$name}'] === '' || \$data['{$name}'] === null)) { \$errors[] = 'Le champ {$name} est obligatoire'; }";
        }
        if ($f['maxLength']) {
            $rulesLines[] = "        if (!empty(\$data['{$name}']) && is_string(\$data['{$name}']) && strlen(\$data['{$name}']) > {$f['maxLength']}) { \$errors[] = 'Le champ {$name} ne doit pas dépasser {$f['maxLength']} caractères'; }";
        }
        if ($f['kind'] === 'int' || $f['kind'] === 'float') {
            $rulesLines[] = "        if (isset(\$data['{$name}']) && \$data['{$name}'] !== '' && !is_numeric(\$data['{$name}'])) { \$errors[] = 'Le champ {$name} doit être un nombre'; }";
        }
        if ($f['kind'] === 'datetime') {
            $rulesLines[] = "        if (isset(\$data['{$name}']) && \$data['{$name}'] !== '' && (strtotime((string)\$data['{$name}']) === false)) { \$errors[] = 'Le champ {$name} doit être une date valide'; }";
        }
        if ($f['kind'] === 'time') {
            $rulesLines[] = "        if (isset(\$data['{$name}']) && \$data['{$name}'] !== '' && !preg_match('/^\\d{2}:\\d{2}(:\\d{2})?$/', (string)\$data['{$name}'])) { \$errors[] = 'Le champ {$name} doit être une heure valide HH:MM[:SS]'; }";
        }
        if (!empty($f['enumValues'])) {
            $allowed = var_export($f['enumValues'], true);
            $rulesLines[] = "        if (isset(\$data['{$name}']) && \$data['{$name}'] !== '' && !in_array((string)\$data['{$name}'], {$allowed}, true)) { \$errors[] = 'Le champ {$name} a une valeur invalide'; }";
        }
        if ($name === $pk && !$compositePk) {
            $rulesLines[] = "        if (\$id !== null && isset(\$data['{$name}']) && (string)\$data['{$name}'] !== (string)\$id) { \$errors[] = 'Modification de la clé primaire non autorisée'; }";
        }
    }

    $ctrlCode .= "\n" . implode("\n", $rulesLines) . "\n\n" . <<<PHP
        return \$errors;
    }

    private function isCsrfValid(string \$token): bool
    {
        return isset(\$_SESSION['csrf_token']) && \$_SESSION['csrf_token'] === \$token;
    }
}
PHP;
    assertNoUnresolvedPlaceholders($ctrlCode, $ctrlPath);
    writeGeneratedFile($ctrlPath, $ctrlCode, $force, $dryRun);
// 16) VUES
    $sessionStartSnippet = $noSessionCheck ? "" : "<?php\nif (session_status() === PHP_SESSION_NONE) { session_start(); }\n?>\n";
    $baseContent = <<<HTML
{$sessionStartSnippet}<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= \$title ?? 'Application' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1><?= \$title ?? 'Application' ?></h1>
        <?= \$content ?? '' ?>
    </div>
</body>
</html>
HTML;
    assertNoUnresolvedPlaceholders($baseContent, "$layoutDir/base.php");
    writeGeneratedFile("$layoutDir/base.php", $baseContent, $force, $dryRun);
// index.php
    $indexHeadCols = implode("\n", array_map(fn($f) => "            <th>" . ucfirst($f['name']) . "</th>", $fields));
    $indexBodyCols = implode("\n", array_map(fn($f) => "        <td><?= htmlspecialchars(\$item->get{$f['method']}()) ?></td>", $fields));
    $indexContent = <<<HTML
<?php
\$title = 'Liste des {$Class}s';
ob_start();
?>
<a href="/{$table}/create" class="btn btn-primary mb-3">Créer un nouveau {$Class}</a>
<table class="table table-striped">
    <thead>
        <tr>
$indexHeadCols
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
<?php foreach (\$list as \$item): ?>
    <tr>
$indexBodyCols
        <td>
            <a href="/{$table}/<?= \$item->get{$PkMethod}() ?>" class="btn btn-sm btn-info">Voir</a>
            <a href="/{$table}/<?= \$item->get{$PkMethod}() ?>/edit" class="btn btn-sm btn-warning">Éditer</a>
            <form action="/{$table}/<?= \$item->get{$PkMethod}() ?>/delete" method="POST" style="display:inline;">
                <input type="hidden" name="_token" value="<?= \$_SESSION['csrf_token'] ?? '' ?>">
                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Confirmer la suppression ?');">Supprimer</button>
            </form>
        </td>
    </tr>
<?php endforeach; ?>
    </tbody>
</table>
<?php
\$content = ob_get_clean();
require __DIR__ . '/../layouts/base.php';
HTML;
    assertNoUnresolvedPlaceholders($indexContent, "$tplDir/index.php");
    writeGeneratedFile("$tplDir/index.php", $indexContent, $force, $dryRun);
// show.php
    $showLines = implode("\n", array_map(fn($f) => "        <p><strong>" . ucfirst($f['name']) . ":</strong> <?= htmlspecialchars(\$entity->get{$f['method']}()) ?></p>", $fields));
    $showContent = <<<HTML
<?php
\$title = 'Détails';
ob_start();
?>
<div class="card">
    <div class="card-body">
$showLines
        <a href="/{$table}/<?= \$entity->get{$PkMethod}() ?>/edit" class="btn btn-sm btn-warning">Éditer</a>
        <a href="/{$table}" class="btn btn-sm btn-secondary">Retour</a>
    </div>
</div>
<?php
\$content = ob_get_clean();
require __DIR__ . '/../layouts/base.php';
HTML;
    assertNoUnresolvedPlaceholders($showContent, "$tplDir/show.php");
    writeGeneratedFile("$tplDir/show.php", $showContent, $force, $dryRun);
// form.php
    $formFields = '';
    foreach ($fields as $f) {
        $n = $f['name'];
        $label = ucfirst($n);
        if (!empty($f['enumValues'])) {
            $formFields .= "    <div class=\"mb-3\">\n        <label class=\"form-label\">{$label}</label>\n        <select name=\"{$n}\" class=\"form-select\">\n            <option value=\"\">--</option>\n";
            foreach ($f['enumValues'] as $val) {
                $esc = htmlspecialchars($val, ENT_QUOTES);
                $formFields .= "            <option value=\"{$esc}\" <?= (isset(\$data['{$n}']) && \$data['{$n}'] === '{$esc}') ? 'selected' : '' ?>>{$esc}</option>\n";
            }
            $formFields .= "        </select\n    </div>\n";
            continue;
        }
        $type = 'text';
        if ($f['kind'] === 'int') {
            $type = 'number';
        }
        if ($f['kind'] === 'float') {
            $type = 'number" step="any';
        }
        if ($f['kind'] === 'datetime') {
            $type = 'datetime-local';
        }
        if ($f['kind'] === 'time') {
            $type = 'time';
        }

        if ($f['kind'] === 'json') {
            $formFields .= "    <div class=\"mb-3\">\n        <label class=\"form-label\">{$label}</label>\n        <textarea name=\"{$n}\" class=\"form-control\"><?= htmlspecialchars(\$data['{$n}'] ?? '') ?></textarea>\n    </div>\n";
        } else {
            $formFields .= "    <div class=\"mb-3\">\n        <label class=\"form-label\">{$label}</label>\n        <input type=\"{$type}\" name=\"{$n}\" class=\"form-control\" value=\"<?= htmlspecialchars(\$data['{$n}'] ?? '') ?>\">\n    </div>\n";
        }
    }

    $formContent = <<<HTML
<?php
\$title = isset(\$data['{$pk}']) && \$data['{$pk}'] !== '' ? 'Éditer {$Class}' : 'Créer {$Class}';
ob_start();
?>
<?php if (!empty(\$errors)): ?>
<div class="alert alert-danger"><ul>
<?php foreach (\$errors as \$err): ?>
    <li><?= htmlspecialchars(\$err) ?></li>
<?php endforeach; ?>
</ul></div>
<?php endif; ?>

<form method="POST" action="<?= isset(\$data['{$pk}']) && \$data['{$pk}'] !== '' ? '/{$table}/' . (int)\$data['{$pk}'] . '/update' : '/{$table}/store' ?>">
    <input type="hidden" name="_token" value="<?= \$_SESSION['csrf_token'] ?? '' ?>">
$formFields
    <button class="btn btn-primary" type="submit">Enregistrer</button>
    <a href="/{$table}" class="btn btn-secondary">Annuler</a>
</form>
<?php
\$content = ob_get_clean();
require __DIR__ . '/../layouts/base.php';
HTML;
    assertNoUnresolvedPlaceholders($formContent, "$tplDir/form.php");
    writeGeneratedFile("$tplDir/form.php", $formContent, $force, $dryRun);
}

// Fin du script
