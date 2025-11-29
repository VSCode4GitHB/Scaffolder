## Vision globale du projet

Donner à ton scaffold un cadre professionnel implique de transformer le script isolé en une application modulaire, testable et déployable : code propre, séparation claire des responsabilités, pipelines automatisés, sécurité et observabilité intégrées, interface d’administration (dashboard) réactive et accessible. Ci‑dessous un plan opérationnel, priorisé et détaillé pour passer de l’outil de génération à une plateforme complète de gestion/monitoring.

---

### Compatibilité PHP et dépendances (politique de base)

- Baseline du projet : PHP 8.1 ou plus (8.1+). Le code est écrit pour fonctionner à minima en 8.1 et reste compatible avec 8.2/8.3+.
- Résolution Composer figée : `composer.json` contient `config.platform.php = 8.1.0` afin d’assurer un `composer.lock` reproductible et compatible 8.1, même si des développeurs utilisent PHP 8.2/8.3 localement.
- CI multi-versions : le workflow `.github/workflows/ci.yml` exécute l’installation et les tests sur au moins PHP 8.1 et 8.3 (8.2 inclus également), sans `--ignore-platform-req`.
- Docker : l’image par défaut utilise PHP 8.2, mais la version peut être surchargée via l’argument de build `PHP_VERSION` (par ex. `PHP_VERSION=8.1`). Cette configuration reste cohérente avec l’objectif « 8.1+ ».
- Procédure en cas de modification des dépendances : exécuter `composer update --with-all-dependencies`, puis commiter le nouveau `composer.lock`.

---
## Les fichiers de base
* **Scaffold (fichier principal):** `./bin/scaffold_v2.php`
```PHP
<?php
declare(strict_types=1);

// bin/scaffold.php
// Usage: php bin/scaffold_v2.php [TABLE_NAME || ENTITY_NAME] --table=[TABLE_NAME || ENTITY_NAME] --force


namespace Scaffold;

use PDO;
use RuntimeException;

const ROOT = __DIR__ . '/..';

// 1) Lecture arg

$options = getopt('', ['table:', 'force', 'skip-views', 'help', 'no-session-check']);
$table = $options['table'] ?? $argv[1] ?? null;

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

// 3) Connexion PDO (via Config OO et autoload Composer)
require_once ROOT . '/../vendor/autoload.php';
use App\Config\Database\Config as DbConfig;
$db = new DbConfig();
$pdo = $db->connection;

// 4) Colonnes
$sql = "SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, EXTRA,
               COLUMN_COMMENT, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table
        ORDER BY ORDINAL_POSITION";
$cols = $pdo->prepare($sql);
$cols->execute(['schema' => $dbName, 'table' => $table]);
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
    $fkStmt->execute(['schema' => $dbName, 'table' => $table]);
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
function parseEnumValues(string $columnType): array {
    if (!preg_match("/^(enum|set)\((.*)\)$/i", $columnType, $m)) return [];
    $inside = $m[2];
    $vals = [];
    if (preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $inside, $m2)) {
        foreach ($m2[1] as $v) $vals[] = stripcslashes($v);
    }
    return $vals;
}
function phpType(array $col): array {
    $dataType = strtolower($col['DATA_TYPE']);
    $colType  = strtolower($col['COLUMN_TYPE']);
    $nullable = ($col['IS_NULLABLE'] === 'YES');
    $map = match (true) {
        $dataType === 'tinyint' && preg_match('/tinyint\(1\)/', $colType) => ['bool','bool'],
        in_array($dataType,['int','integer','smallint','mediumint','bigint','tinyint']) => ['int','int'],
        in_array($dataType,['decimal','float','double','real']) => ['float','float'],
        in_array($dataType,['date','datetime','timestamp']) => ['\\DateTimeImmutable','datetime'],
        $dataType==='time' => ['string','time'],
        $dataType==='year' => ['int','year'],
        $dataType==='json' => ['array','json'],
        in_array($dataType,['set','enum']) => ['string','enum'],
        default => ['string','string'],
    };
    $php = $map[0]; $kind=$map[1];
    return [$nullable?('?'.$php):$php, $kind, $nullable, $col['COLUMN_COMMENT']??'', $col['COLUMN_TYPE']??''];
}
function normalizeColumnForCode(string $name): string {
    $trans = @iconv('UTF-8','ASCII//TRANSLIT',$name) ?: $name;
    $clean = preg_replace('/[^a-z0-9_]+/i','_',$trans);
    $clean = trim(preg_replace('/_+/','_',$clean),'_');
    if ($clean===''||preg_match('/^[0-9]/',$clean)) $clean='f_'.$clean;
    return implode('',array_map(fn($p)=>ucfirst(strtolower($p)),explode('_',$clean)));
}
function classNameFromTable(string $t): string {
    $t = preg_replace('/[^a-z0-9_]/i','_',$t);
    return implode('',array_map(fn($p)=>ucfirst(strtolower($p)),explode('_',$t)));
}

// 8) Vérification des fichiers existants
function ensureWritable(string $path, bool $force): void {
    if (file_exists($path) && !$force) {
        throw new RuntimeException("Le fichier existe déjà et --force n'est pas fourni: $path");
    }
}
function assertNoUnresolvedPlaceholders(string $content, string $path): void {
    if (preg_match('/\{\$[A-Za-z0-9_]+\}/', $content)) {
        fwrite(STDERR, "Warning: contenu généré pour $path contient des placeholders non résolus\n");
    }
}

$Class = classNameFromTable($table);
$PkMethod = normalizeColumnForCode($pk);

$entityDir   = ROOT . "/src/Domain/Entity";
$hydratorDir = ROOT . "/src/Infrastructure/Hydration";
$repoIfaceDir= ROOT . "/src/Domain/Repository";
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
foreach ($pathsToCheck as $p) { ensureWritable($p, $force); }

// 9) Préparer champs
$fields=[];
foreach($columns as $col){
    [$phpT,$kind,$nullable,$comment,$columnType]=phpType($col);
    $fields[]=[
        'name'       => $col['COLUMN_NAME'],
        'method'     => normalizeColumnForCode($col['COLUMN_NAME']),
        'phpType'    => $phpT,
        'kind'       => $kind,
        'nullable'   => $nullable,
        'comment'    => $comment,
        'maxLength'  => $col['CHARACTER_MAXIMUM_LENGTH']??null,
        'precision'  => $col['NUMERIC_PRECISION']??null,
        'scale'      => $col['NUMERIC_SCALE']??null,
        'enumValues' => parseEnumValues($columnType)
    ];
}

// 10) Générer ENTITÉ
$propsBlock       = implode("\n", array_map(fn($f) => "    private {$f['phpType']} \${$f['name']};" . ($f['comment'] ? " // {$f['comment']}" : ''), $fields));
$constructorBlock = implode(",\n", array_map(fn($f) => "        {$f['phpType']} \${$f['name']}", $fields));
$getsBlock        = implode("\n", array_map(fn($f) => "    public function get{$f['method']}(): {$f['phpType']} { return \$this->{$f['name']}; }", $fields));
$setsBlock        = implode("\n", array_map(function($f) use ($primaryKeys) {
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
file_put_contents($entityPath, $entityCode);

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
file_put_contents($interfacePath, $interfaceCode);

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
    $defaultValue = match($f['kind']) {
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
file_put_contents($hydratorPath, $hydratorCode);

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
file_put_contents($repoIfacePath, $repoIfaceCode);

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
file_put_contents($repoImplPath, $repoImplCode);

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
file_put_contents($servicePath, $serviceCode);

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
    file_put_contents($ctrlPath, $ctrlCode);

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
    file_put_contents("$layoutDir/base.php", $baseContent);

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
    file_put_contents("$tplDir/index.php", $indexContent);

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
    file_put_contents("$tplDir/show.php", $showContent);

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
        if ($f['kind'] === 'int')   $type = 'number';
        if ($f['kind'] === 'float') $type = 'number" step="any';
        if ($f['kind'] === 'datetime') $type = 'datetime-local';
        if ($f['kind'] === 'time')  $type = 'time';

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
    file_put_contents("$tplDir/form.php", $formContent);
}

// Fin du script

```
* **Base de données:** `./bin/scaffolddb.sql`
```SQL

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3307
-- Généré le : dim. 02 nov. 2025 à 16:31
-- Version du serveur : 11.3.2-MariaDB
-- Version de PHP : 8.2.18

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `scaffolddb`
--

-- --------------------------------------------------------

--
-- Structure de la table `about_section`
--

DROP TABLE IF EXISTS `about_section`;
CREATE TABLE IF NOT EXISTS `about_section` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Singleton id = 1',
  `eyebrow` varchar(100) DEFAULT NULL COMMENT 'Petite ligne descriptive au dessus du titre',
  `title` varchar(255) DEFAULT NULL COMMENT 'Titre principal de la section about',
  `image_media_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Image associée (FK -> media.id)',
  PRIMARY KEY (`id`),
  KEY `fk_about_img` (`image_media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Section A propos du site';

-- --------------------------------------------------------

--
-- Structure de la table `about_tabs`
--

DROP TABLE IF EXISTS `about_tabs`;
CREATE TABLE IF NOT EXISTS `about_tabs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant de l''onglet',
  `section_id` tinyint(3) UNSIGNED NOT NULL COMMENT 'FK -> about_section.id',
  `tab_key` varchar(50) NOT NULL COMMENT 'Clé unique dans la section (ex: mission)',
  `tab_label` varchar(100) NOT NULL COMMENT 'Libellé visible de l''onglet',
  `metric_value` int(11) DEFAULT NULL COMMENT 'Valeur métrique affichée dans l''onglet (optionnel)',
  `metric_label` varchar(150) DEFAULT NULL COMMENT 'Libellé du métrique',
  `description` text DEFAULT NULL COMMENT 'Description détaillée pour l''onglet',
  `cta_label` varchar(100) DEFAULT NULL COMMENT 'Label CTA interne',
  `cta_url` varchar(512) DEFAULT NULL COMMENT 'URL du CTA',
  `order_index` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage',
  `published` tinyint(1) DEFAULT 1 COMMENT 'Publié ou non',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_about_tab` (`section_id`,`tab_key`),
  KEY `idx_abouttab_order` (`section_id`,`published`,`order_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Onglets et contenu de la section about_section';

-- --------------------------------------------------------

--
-- Structure de la table `about_tab_bullets`
--

DROP TABLE IF EXISTS `about_tab_bullets`;
CREATE TABLE IF NOT EXISTS `about_tab_bullets` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant de la puce',
  `tab_id` bigint(20) UNSIGNED NOT NULL COMMENT 'FK -> about_tabs.id',
  `text` varchar(255) NOT NULL COMMENT 'Texte de la puce',
  `icon_class` varchar(100) DEFAULT 'far fa-check-square' COMMENT 'Classe icône par défaut',
  `order_index` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage',
  PRIMARY KEY (`id`),
  KEY `idx_aboutbullet_order` (`tab_id`,`order_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Puces listées sous un onglet about_tab';

-- --------------------------------------------------------

--
-- Structure de la table `authors`
--

DROP TABLE IF EXISTS `authors`;
CREATE TABLE IF NOT EXISTS `authors` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant de l''auteur',
  `name` varchar(150) NOT NULL COMMENT 'Nom affiché de l''auteur',
  `avatar_media_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Photo de profil (FK -> media.id)',
  PRIMARY KEY (`id`),
  KEY `idx_authors_name` (`name`),
  KEY `fk_authors_avatar_media` (`avatar_media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Auteurs des posts/articles';

-- --------------------------------------------------------

--
-- Structure de la table `company_profile`
--

DROP TABLE IF EXISTS `company_profile`;
CREATE TABLE IF NOT EXISTS `company_profile` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Singleton id = 1',
  `company_name` varchar(255) DEFAULT NULL COMMENT 'Nom complet de l''entreprise',
  `address_line` varchar(255) DEFAULT NULL COMMENT 'Adresse physique principale',
  `email` varchar(255) DEFAULT NULL COMMENT 'Courriel de contact principal',
  `phone` varchar(50) DEFAULT NULL COMMENT 'Numéro de téléphone principal',
  `office_hours` varchar(255) DEFAULT NULL COMMENT 'Horaires d''ouverture',
  `logo_media_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'FK vers media.id pour le logo',
  `created_at` datetime DEFAULT current_timestamp() COMMENT 'Date de création du profil',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Date de dernière modification',
  PRIMARY KEY (`id`),
  KEY `fk_company_logo` (`logo_media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Informations d''entreprise affichées globalement';

--
-- Déchargement des données de la table `company_profile`
--

INSERT INTO `company_profile` (`id`, `company_name`, `address_line`, `email`, `phone`, `office_hours`, `logo_media_id`, `created_at`, `updated_at`) VALUES
(1, 'CongoleseYouth sarl', '25, Cyws, Gombe, Kinshasa', 'info@congoleseyouth.cd', '+243814864186', '08:00 - 18h00', 1, '2025-08-28 23:40:16', '2025-08-30 23:05:15');

-- --------------------------------------------------------

--
-- Structure de la table `contact_messages`
--

DROP TABLE IF EXISTS `contact_messages`;
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant du message',
  `name` varchar(150) NOT NULL COMMENT 'Nom de l''expéditeur',
  `email` varchar(255) NOT NULL COMMENT 'Email de l''expéditeur',
  `subject` varchar(255) DEFAULT NULL COMMENT 'Sujet du message',
  `message` text NOT NULL COMMENT 'Contenu du message',
  `created_at` datetime DEFAULT current_timestamp() COMMENT 'Date de réception',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Messages soumis par les visiteurs';

-- --------------------------------------------------------

--
-- Structure de la table `contact_methods`
--

DROP TABLE IF EXISTS `contact_methods`;
CREATE TABLE IF NOT EXISTS `contact_methods` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant de méthode',
  `section_id` tinyint(3) UNSIGNED NOT NULL COMMENT 'FK -> contact_section.id',
  `label` varchar(100) NOT NULL COMMENT 'Libellé (ex: Téléphone)',
  `value` varchar(255) NOT NULL COMMENT 'Valeur (ex: +243... ou email)',
  `icon_class` varchar(100) DEFAULT NULL COMMENT 'Classe icône CSS',
  `order_index` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage',
  PRIMARY KEY (`id`),
  KEY `idx_contactmethod_order` (`section_id`,`order_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Méthodes de contact affichées dans la section contact';

-- --------------------------------------------------------

--
-- Structure de la table `contact_section`
--

DROP TABLE IF EXISTS `contact_section`;
CREATE TABLE IF NOT EXISTS `contact_section` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Singleton id = 1',
  `eyebrow` varchar(100) DEFAULT NULL COMMENT 'Petite ligne de présentation',
  `title` varchar(255) DEFAULT NULL COMMENT 'Titre principal de la section contact',
  `subtitle` varchar(255) DEFAULT NULL COMMENT 'Sous-titre pour informations additionnelles',
  `bg_media_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Image de fond (FK -> media.id)',
  PRIMARY KEY (`id`),
  KEY `fk_contact_bg` (`bg_media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contenu et configuration de la zone contact';

-- --------------------------------------------------------

--
-- Structure de la table `counters`
--

DROP TABLE IF EXISTS `counters`;
CREATE TABLE IF NOT EXISTS `counters` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant du compteur',
  `label` varchar(255) NOT NULL COMMENT 'Libellé du compteur (ex: Projets finis)',
  `value` int(11) NOT NULL COMMENT 'Valeur numérique du compteur',
  `suffix` varchar(10) DEFAULT NULL COMMENT 'Suffixe facultatif (ex: +, K)',
  `section_id` tinyint(3) UNSIGNED NOT NULL COMMENT 'FK -> counter_section.id',
  `icon_class` varchar(100) DEFAULT NULL COMMENT 'Classe d''icône CSS',
  `order_index` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage',
  `published` tinyint(1) DEFAULT 1 COMMENT 'Publié ou non',
  PRIMARY KEY (`id`),
  KEY `idx_counters_published_order` (`published`,`order_index`),
  KEY `fk_counter_section` (`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Comptoirs statistiques présentés dans counter_section';

-- --------------------------------------------------------

--
-- Structure de la table `counter_section`
--

DROP TABLE IF EXISTS `counter_section`;
CREATE TABLE IF NOT EXISTS `counter_section` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Singleton id = 1',
  `bg_media_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Image de fond optionnelle (FK -> media.id)',
  PRIMARY KEY (`id`),
  KEY `fk_counter_bg` (`bg_media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configuration générale pour la section counters';

-- --------------------------------------------------------

--
-- Structure de la table `feature_items`
--

DROP TABLE IF EXISTS `feature_items`;
CREATE TABLE IF NOT EXISTS `feature_items` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant de l''item',
  `section_id` tinyint(3) UNSIGNED NOT NULL COMMENT 'FK -> feature_section.id',
  `title` varchar(150) NOT NULL COMMENT 'Titre de l''élément',
  `description` text DEFAULT NULL COMMENT 'Description détaillée',
  `icon_media_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Icône sous forme de média (FK -> media.id)',
  `is_active` tinyint(1) DEFAULT 0 COMMENT 'Met en avant l''item si =1',
  `order_index` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage',
  `published` tinyint(1) DEFAULT 1 COMMENT 'Publié ou non',
  PRIMARY KEY (`id`),
  KEY `idx_featureitem_order` (`section_id`,`published`,`order_index`),
  KEY `fk_featureitem_icon` (`icon_media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Items affichés dans la section feature_section';

-- --------------------------------------------------------

--
-- Structure de la table `feature_section`
--

DROP TABLE IF EXISTS `feature_section`;
CREATE TABLE IF NOT EXISTS `feature_section` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Singleton id = 1',
  `eyebrow` varchar(100) DEFAULT NULL COMMENT 'Petite ligne descriptive',
  `title` varchar(255) DEFAULT NULL COMMENT 'Titre de la section features',
  `cta_label` varchar(100) DEFAULT NULL COMMENT 'Label du bouton d''appel à l''action',
  `cta_url` varchar(512) DEFAULT NULL COMMENT 'URL du CTA',
  `bg_media_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Image de fond (FK -> media.id)',
  PRIMARY KEY (`id`),
  KEY `fk_feature_bg` (`bg_media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configuration de la section features (zone services/USP)';

-- --------------------------------------------------------

--
-- Structure de la table `footer_columns`
--

DROP TABLE IF EXISTS `footer_columns`;
CREATE TABLE IF NOT EXISTS `footer_columns` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant de colonne de footer',
  `title` varchar(100) DEFAULT NULL COMMENT 'Titre de la colonne (ex: Ressources)',
  `order_index` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Colonnes structurelles du pied de page';

-- --------------------------------------------------------

--
-- Structure de la table `footer_links`
--

DROP TABLE IF EXISTS `footer_links`;
CREATE TABLE IF NOT EXISTS `footer_links` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant du lien',
  `column_id` bigint(20) UNSIGNED NOT NULL COMMENT 'FK -> footer_columns.id',
  `label` varchar(150) NOT NULL COMMENT 'Texte du lien',
  `url` varchar(512) NOT NULL COMMENT 'URL du lien',
  `order_index` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage dans la colonne',
  PRIMARY KEY (`id`),
  KEY `idx_footerlink_order` (`column_id`,`order_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Liens sous chaque colonne du footer';

-- --------------------------------------------------------

--
-- Structure de la table `hero_slides`
--

DROP TABLE IF EXISTS `hero_slides`;
CREATE TABLE IF NOT EXISTS `hero_slides` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant du slide',
  `eyebrow` varchar(255) DEFAULT NULL COMMENT 'Petite ligne au dessus du titre',
  `title` varchar(255) NOT NULL COMMENT 'Titre du slide',
  `cta_label` varchar(100) DEFAULT NULL COMMENT 'Texte du bouton CTA',
  `cta_url` varchar(512) DEFAULT NULL COMMENT 'URL du CTA',
  `background_media_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Image d''arrière plan (FK -> media.id)',
  `order_index` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage',
  `published` tinyint(1) DEFAULT 1 COMMENT 'Publié ou non',
  PRIMARY KEY (`id`),
  KEY `idx_hero_published` (`published`,`order_index`),
  KEY `fk_hero_bg` (`background_media_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Slides de la section hero';

--
-- Déchargement des données de la table `hero_slides`
--

INSERT INTO `hero_slides` (`id`, `eyebrow`, `title`, `cta_label`, `cta_url`, `background_media_id`, `order_index`, `published`) VALUES
(1, 'Empower your business', 'Déployez plus vite avec une plateforme fiable', 'Commencer', '/contact', 1, 0, 1),
(2, 'Bienvenue sur CongoleseYouth', 'Votre partenaire innovant en technologique numérique et digital.', 'S\'engager avec nous', '/contacts/contact.php', 2, 1, 1),
(3, 'Work without borders', 'Collaborez efficacement, où que soient vos équipes', 'En savoir plus', '/about', 3, 2, 1),
(4, 'Scale with confidence', 'Montez en échelle sans complexité ni interruptions', 'Découvrir', '/platform', 4, 3, 1),
(5, 'Go green', 'Accélérez votre transition vers une croissance durable', 'Études de cas', '/case-studies/green', 5, 4, 1);

-- --------------------------------------------------------

--
-- Structure de la table `media`
--

DROP TABLE IF EXISTS `media`;
CREATE TABLE IF NOT EXISTS `media` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant unique du média',
  `path` varchar(512) NOT NULL COMMENT 'Chemin relatif vers le fichier média',
  `title` varchar(255) DEFAULT NULL COMMENT 'Titre du média pour l''administration',
  `alt_text` varchar(255) DEFAULT NULL COMMENT 'Texte alternatif pour accessibilité/SEO',
  `mime_type` varchar(100) DEFAULT NULL COMMENT 'Type MIME du fichier (image/png, image/jpeg, etc.)',
  `width` int(11) DEFAULT NULL COMMENT 'Largeur en pixels, si fournie',
  `height` int(11) DEFAULT NULL COMMENT 'Hauteur en pixels, si fournie',
  `created_at` datetime DEFAULT current_timestamp() COMMENT 'Date de création du média',
  `media_type` varchar(50) NOT NULL COMMENT 'Catégorie ou usage du média (logo, slide, general, etc.)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_media_path` (`path`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bibliothèque de médias partagée pour les sections et items';

--
-- Déchargement des données de la table `media`
--

INSERT INTO `media` (`id`, `path`, `title`, `alt_text`, `mime_type`, `width`, `height`, `created_at`, `media_type`) VALUES
(1, 'assets/img/congoleseyouth_logo.png', 'Cyws Logo', 'Logo-CYWS', 'image/jpeg', 640, 640, '2025-08-28 23:55:54', 'logo'),
(2, 'assets/img/slides/welcome_slide.png', 'CongoleseYouth présentation', 'Congolese Youth iDigital.', 'image/png', 1920, 1280, '2025-08-29 10:00:00', 'slide'),
(3, 'assets/img/slides/welcome_slide_2.png', 'Équipe en collaboration', 'Équipe multiculturelle collaborant autour d’une table avec laptops', 'image/png', 1920, 1080, '2025-08-29 10:05:00', 'slide'),
(4, 'assets/img/slides/welcome_slide_3.png', 'Tableau de bord analytique', 'Grand écran affichant des graphiques et KPIs en temps réel', 'image/png', 2400, 1350, '2025-08-29 10:10:00', 'slide'),
(5, 'assets/img/slides/welcome_slide_4.png', 'Infrastructure cloud', 'Allée de serveurs dans un datacenter avec éclairage bleu', 'image/png', 1920, 1080, '2025-08-29 10:15:00', 'general'),
(6, 'assets/img/hero/sustainability.webp', 'Innovation durable', 'Panneaux solaires et éoliennes sous un ciel dégagé', 'image/webp', 2048, 1152, '2025-08-29 10:20:00', 'spécifique');

-- --------------------------------------------------------

--
-- Structure de la table `menus`
--

DROP TABLE IF EXISTS `menus`;
CREATE TABLE IF NOT EXISTS `menus` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant du menu',
  `key` varchar(50) NOT NULL COMMENT 'Identifiant logique du menu (ex: main)',
  `title` varchar(100) DEFAULT NULL COMMENT 'Titre administratif du menu',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_menus_key` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Menus du site (ensemble d''items)';

--
-- Déchargement des données de la table `menus`
--

INSERT INTO `menus` (`id`, `key`, `title`) VALUES
(1, 'main', 'Bienvenue sur la page d\'accueil');

-- --------------------------------------------------------

--
-- Structure de la table `menu_items`
--

DROP TABLE IF EXISTS `menu_items`;
CREATE TABLE IF NOT EXISTS `menu_items` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant de l''item',
  `menu_id` bigint(20) UNSIGNED NOT NULL COMMENT 'FK -> menus.id',
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Self-FK vers menu_items.id pour structure arborescente',
  `label` varchar(255) NOT NULL COMMENT 'Label affiché',
  `url` varchar(512) DEFAULT NULL COMMENT 'URL du lien',
  `page_slug` varchar(255) DEFAULT NULL COMMENT 'Slug interne de page (optionnel)',
  `icon_class` varchar(100) DEFAULT NULL COMMENT 'Classe d''icône CSS',
  `target` varchar(20) DEFAULT NULL COMMENT 'Cible du lien (_self, _blank)',
  `order_index` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage',
  `enabled` tinyint(1) DEFAULT 1 COMMENT 'Activé ou non',
  PRIMARY KEY (`id`),
  KEY `idx_menuitems_menu_order` (`menu_id`,`order_index`),
  KEY `fk_menuitems_parent` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Items navigations, appartenant à un menu';

--
-- Déchargement des données de la table `menu_items`
--

INSERT INTO `menu_items` (`id`, `menu_id`, `parent_id`, `label`, `url`, `page_slug`, `icon_class`, `target`, `order_index`, `enabled`) VALUES
(1, 1, NULL, 'accueil', './', 'home', NULL, '_self', 0, 1),
(2, 1, NULL, 'services', './views/pages/services.php', 'services', NULL, '_self', 0, 1),
(3, 1, NULL, 'projets', './views/pages/projects.php', 'projets', NULL, '_self', 0, 1),
(4, 1, NULL, 'contactez-nous', './views/pages/contact.php', 'contacts', NULL, '_self', 0, 1);

-- --------------------------------------------------------

--
-- Structure de la table `newsletter_subscribers`
--

DROP TABLE IF EXISTS `newsletter_subscribers`;
CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment',
  `email` varchar(255) NOT NULL COMMENT 'Email de l''abonné',
  `status` enum('active','unsubscribed','bounced') NOT NULL DEFAULT 'active' COMMENT 'Statut de l''abonnement',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Date d''inscription',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_newsletter_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Liste des abonnés à la newsletter';

-- --------------------------------------------------------

--
-- Structure de la table `posts`
--

DROP TABLE IF EXISTS `posts`;
CREATE TABLE IF NOT EXISTS `posts` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant du post',
  `title` varchar(255) NOT NULL COMMENT 'Titre du post',
  `slug` varchar(200) NOT NULL COMMENT 'Slug unique pour URL',
  `excerpt` text DEFAULT NULL COMMENT 'Résumé court',
  `body` mediumtext DEFAULT NULL COMMENT 'Contenu HTML/Markdown du post',
  `featured_media_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Image à la une (FK -> media.id)',
  `author_id` bigint(20) UNSIGNED NOT NULL COMMENT 'Auteur (FK -> authors.id)',
  `published_at` datetime DEFAULT NULL COMMENT 'Date de publication',
  `status` enum('draft','published','scheduled') NOT NULL DEFAULT 'draft' COMMENT 'Statut editorial',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_posts_slug` (`slug`),
  KEY `idx_posts_status_published` (`status`,`published_at`),
  KEY `idx_posts_author` (`author_id`),
  KEY `fk_posts_featured_media` (`featured_media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Articles et actualités du site (remplace news_articles)';

-- --------------------------------------------------------

--
-- Structure de la table `post_categories`
--

DROP TABLE IF EXISTS `post_categories`;
CREATE TABLE IF NOT EXISTS `post_categories` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant de catégorie',
  `name` varchar(150) NOT NULL COMMENT 'Nom visible de la catégorie',
  `slug` varchar(150) NOT NULL COMMENT 'Slug unique de la catégorie',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_post_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Taxonomie catégorie pour posts';

-- --------------------------------------------------------

--
-- Structure de la table `post_category_post`
--

DROP TABLE IF EXISTS `post_category_post`;
CREATE TABLE IF NOT EXISTS `post_category_post` (
  `post_id` bigint(20) UNSIGNED NOT NULL COMMENT 'FK -> posts.id',
  `category_id` bigint(20) UNSIGNED NOT NULL COMMENT 'FK -> post_categories.id',
  PRIMARY KEY (`post_id`,`category_id`),
  KEY `idx_post_category_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pivot posts <-> categories';

-- --------------------------------------------------------

--
-- Structure de la table `post_tags`
--

DROP TABLE IF EXISTS `post_tags`;
CREATE TABLE IF NOT EXISTS `post_tags` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant du tag',
  `name` varchar(150) NOT NULL COMMENT 'Nom du tag',
  `slug` varchar(150) NOT NULL COMMENT 'Slug unique du tag',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_post_tags_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Taxonomie tag pour posts';

-- --------------------------------------------------------

--
-- Structure de la table `post_tag_post`
--

DROP TABLE IF EXISTS `post_tag_post`;
CREATE TABLE IF NOT EXISTS `post_tag_post` (
  `post_id` bigint(20) UNSIGNED NOT NULL COMMENT 'FK -> posts.id',
  `tag_id` bigint(20) UNSIGNED NOT NULL COMMENT 'FK -> post_tags.id',
  PRIMARY KEY (`post_id`,`tag_id`),
  KEY `idx_post_tag_tag` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pivot posts <-> tags';

-- --------------------------------------------------------

--
-- Structure de la table `services`
--

DROP TABLE IF EXISTS `services`;
CREATE TABLE IF NOT EXISTS `services` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant du service',
  `name` varchar(150) NOT NULL COMMENT 'Nom du service',
  `slug` varchar(150) DEFAULT NULL COMMENT 'Slug optionnel pour page service',
  `icon_class` varchar(100) DEFAULT NULL COMMENT 'Classe d''icône CSS',
  `excerpt` text DEFAULT NULL COMMENT 'Texte court de présentation',
  `body` mediumtext DEFAULT NULL COMMENT 'Description complète',
  `details_url` varchar(512) DEFAULT NULL COMMENT 'URL externe ou interne de détails',
  `number_badge` varchar(10) DEFAULT NULL COMMENT 'Badge numérique court (ex: 01)',
  `order_index` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage',
  `published` tinyint(1) DEFAULT 1 COMMENT 'Visibilité',
  PRIMARY KEY (`id`),
  KEY `idx_services_order` (`published`,`order_index`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catalogue des services fournis';

--
-- Déchargement des données de la table `services`
--

INSERT INTO `services` (`id`, `name`, `slug`, `icon_class`, `excerpt`, `body`, `details_url`, `number_badge`, `order_index`, `published`) VALUES
(1, 'Modélisation Esthétique', NULL, 'fas fa-mobile-alt', 'Dans le bon', NULL, NULL, NULL, 0, 1),
(2, 'Formation en Informatique Générale', NULL, 'fas fa-info-alt', 'Une formation professionnelle de qualité adaptée au marché de l\'emploi.', NULL, NULL, NULL, 1, 1),
(3, 'Support Informatique', 'support-informatique', 'fas fa-headset', 'Assistance technique rapide et efficace pour vos besoins informatiques quotidiens.', 'Notre service de support informatique vous accompagne dans la résolution\r\nde vos problèmes techniques, la maintenance de vos systèmes et la\r\nformation de vos équipes. Nous intervenons sur Windows, Linux et\r\nenvironnements réseaux pour garantir la continuité de vos activités.', 'http://localhost:8000/services/support-informatique', '01', 1, 1),
(4, 'Maintenance Réseau', 'maintenance-reseau', 'fas fa-network-wired', 'Supervision, dépannage et optimisation de vos infrastructures réseau.', '', 'http://localhost:8000//services/maintenance-reseau', '', 0, 1),
(5, 'Sécurité Systèmes', 'securite-systemes', 'fas fa-shield-alt', 'Protection avancée contre les menaces et conformité aux normes de sécurité.', '', 'http://localhost:8000/services/securite-systemes', '', 0, 1),
(6, 'Gestion des Bases de Données', 'gestion-bases-donnees', 'fas fa-database', '', '', 'http://localhost:8000//services/gestion-bases-donnees', '04', 0, 1),
(7, 'Virtualisation & Cloud', 'virtualisation-cloud', 'fas fa-cloud', '', '', 'http://localhost:8000/services/virtualisation-cloud', '05', 0, 1),
(8, 'Modélisation Esthétique', 'modelisation-esthetique', 'fas fa-pencil-ruler', 'Dans le bon', NULL, '#', '01', 0, 1),
(9, 'Formation en Informatique', 'formation-informatique', 'fas fa-info-circle', 'Une formation professionnelle de qualité adaptée au marché de l\'emploi.', NULL, '#', '02', 1, 1),
(10, 'Développement Web', 'developpement-web', 'fas fa-code', 'Création de sites web modernes et performants pour une présence en ligne optimale.', NULL, '#', '03', 2, 1),
(11, 'Marketing Digital', 'marketing-digital', 'fas fa-bullhorn', 'Stratégies de marketing numérique pour accroître votre visibilité et votre engagement.', '', '#', '04', 3, 1),
(12, 'Banque Digitale Sécurisée', 'anque-igitale-ecuris-ee', 'fas fa-university', 'Plateforme bancaire en ligne fiable, rapide et sécurisée, accessible 24/7.', 'Gérez vos comptes et transactions en toute sécurité grâce à notre plateforme bancaire digitale.', 'http://localhost:8000/services/anque-igitale-ecuris-ee', '12', 0, 1);

-- --------------------------------------------------------

--
-- Structure de la table `service_section`
--

DROP TABLE IF EXISTS `service_section`;
CREATE TABLE IF NOT EXISTS `service_section` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Singleton id = 1',
  `eyebrow` varchar(100) DEFAULT NULL COMMENT 'Petite ligne descriptive',
  `title` varchar(255) DEFAULT NULL COMMENT 'Titre principal de la section services',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configuration générale pour la section services';

-- --------------------------------------------------------

--
-- Structure de la table `skills`
--

DROP TABLE IF EXISTS `skills`;
CREATE TABLE IF NOT EXISTS `skills` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment',
  `name` varchar(150) NOT NULL COMMENT 'Nom de la compétence',
  `percent` tinyint(3) UNSIGNED NOT NULL COMMENT 'Pourcentage de compétence (0-100)',
  `order_index` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage',
  `published` tinyint(1) DEFAULT 1 COMMENT 'Publié ou non',
  PRIMARY KEY (`id`),
  KEY `idx_skills_published_order` (`published`,`order_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Compétences affichées (barres, etc.)';

-- --------------------------------------------------------

--
-- Structure de la table `social_links`
--

DROP TABLE IF EXISTS `social_links`;
CREATE TABLE IF NOT EXISTS `social_links` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant du lien social',
  `platform` varchar(50) NOT NULL COMMENT 'Nom de la plateforme (facebook, twitter, etc.)',
  `url` varchar(512) NOT NULL COMMENT 'URL ou identifiant (selon usage)',
  `icon_class` varchar(100) DEFAULT NULL COMMENT 'Classe icône',
  `order_index` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage',
  `enabled` tinyint(1) DEFAULT 1 COMMENT 'Publié ou non',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_platform` (`platform`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Liens et identifiants des profils sociaux';

--
-- Déchargement des données de la table `social_links`
--

INSERT INTO `social_links` (`id`, `platform`, `url`, `icon_class`, `order_index`, `enabled`) VALUES
(3, 'facebook', 'congoleseyouth_sarl', 'fab fa-facebook-f', 1, 1),
(4, 'twitter', 'congolese_youth', 'fab fa-twitter', 2, 1),
(5, 'whatsapp', 'congoleseyouth', 'fab fa-whatsapp', 3, 1);

-- --------------------------------------------------------

--
-- Structure de la table `testimonials`
--

DROP TABLE IF EXISTS `testimonials`;
CREATE TABLE IF NOT EXISTS `testimonials` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant du témoignage',
  `author_name` varchar(150) NOT NULL COMMENT 'Nom de la personne',
  `author_role` varchar(150) DEFAULT NULL COMMENT 'Rôle ou titre de la personne',
  `content` text NOT NULL COMMENT 'Texte du témoignage',
  `photo_media_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Photo (FK -> media.id)',
  `order_index` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage',
  `published` tinyint(1) DEFAULT 1 COMMENT 'Publié ou non',
  PRIMARY KEY (`id`),
  KEY `idx_testimonial_order` (`published`,`order_index`),
  KEY `fk_testimonial_photo` (`photo_media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Témoignages clients';

-- --------------------------------------------------------

--
-- Structure de la table `testimonial_section`
--

DROP TABLE IF EXISTS `testimonial_section`;
CREATE TABLE IF NOT EXISTS `testimonial_section` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Singleton id = 1',
  `bg_media_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Image de fond (FK -> media.id)',
  PRIMARY KEY (`id`),
  KEY `fk_testimonial_section_bg_media` (`bg_media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Paramètres globaux pour la section témoignages';

-- --------------------------------------------------------

--
-- Structure de la table `trust_bullets`
--

DROP TABLE IF EXISTS `trust_bullets`;
CREATE TABLE IF NOT EXISTS `trust_bullets` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto-increment, identifiant de la puce',
  `section_id` tinyint(3) UNSIGNED NOT NULL COMMENT 'FK -> trust_section.id',
  `text` varchar(255) NOT NULL COMMENT 'Texte de la puce',
  `icon_class` varchar(100) DEFAULT 'fas fa-check-circle' COMMENT 'Classe icône',
  `order_index` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage',
  PRIMARY KEY (`id`),
  KEY `idx_trustbullet_order` (`section_id`,`order_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Points de confiance affichés dans trust_section';

-- --------------------------------------------------------

--
-- Structure de la table `trust_section`
--

DROP TABLE IF EXISTS `trust_section`;
CREATE TABLE IF NOT EXISTS `trust_section` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Singleton id = 1',
  `eyebrow` varchar(100) DEFAULT NULL COMMENT 'Petite ligne descriptive',
  `title` varchar(255) DEFAULT NULL COMMENT 'Titre de la section trust',
  `body` text DEFAULT NULL COMMENT 'Texte descriptif ou mission',
  `image_media_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Image associée (FK -> media.id)',
  `video_url` varchar(512) DEFAULT NULL COMMENT 'URL vidéo optionnelle',
  `cta_label` varchar(100) DEFAULT NULL COMMENT 'Label CTA',
  `cta_url` varchar(512) DEFAULT NULL COMMENT 'URL du CTA',
  PRIMARY KEY (`id`),
  KEY `fk_trust_img` (`image_media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Section confiance / why trust us';

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `about_section`
--
ALTER TABLE `about_section`
  ADD CONSTRAINT `fk_about_img` FOREIGN KEY (`image_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `about_tabs`
--
ALTER TABLE `about_tabs`
  ADD CONSTRAINT `fk_abouttab_section` FOREIGN KEY (`section_id`) REFERENCES `about_section` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `about_tab_bullets`
--
ALTER TABLE `about_tab_bullets`
  ADD CONSTRAINT `fk_aboutbullet_tab` FOREIGN KEY (`tab_id`) REFERENCES `about_tabs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `authors`
--
ALTER TABLE `authors`
  ADD CONSTRAINT `fk_authors_avatar_media` FOREIGN KEY (`avatar_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `company_profile`
--
ALTER TABLE `company_profile`
  ADD CONSTRAINT `fk_company_logo` FOREIGN KEY (`logo_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `contact_methods`
--
ALTER TABLE `contact_methods`
  ADD CONSTRAINT `fk_contactmethod_section` FOREIGN KEY (`section_id`) REFERENCES `contact_section` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `contact_section`
--
ALTER TABLE `contact_section`
  ADD CONSTRAINT `fk_contact_bg` FOREIGN KEY (`bg_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `counters`
--
ALTER TABLE `counters`
  ADD CONSTRAINT `fk_counter_section` FOREIGN KEY (`section_id`) REFERENCES `counter_section` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `counter_section`
--
ALTER TABLE `counter_section`
  ADD CONSTRAINT `fk_counter_bg` FOREIGN KEY (`bg_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `feature_items`
--
ALTER TABLE `feature_items`
  ADD CONSTRAINT `fk_featureitem_icon` FOREIGN KEY (`icon_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_featureitem_section` FOREIGN KEY (`section_id`) REFERENCES `feature_section` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `feature_section`
--
ALTER TABLE `feature_section`
  ADD CONSTRAINT `fk_feature_bg` FOREIGN KEY (`bg_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `footer_links`
--
ALTER TABLE `footer_links`
  ADD CONSTRAINT `fk_footerlink_column` FOREIGN KEY (`column_id`) REFERENCES `footer_columns` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `hero_slides`
--
ALTER TABLE `hero_slides`
  ADD CONSTRAINT `fk_hero_bg` FOREIGN KEY (`background_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `menu_items`
--
ALTER TABLE `menu_items`
  ADD CONSTRAINT `fk_menuitems_menu` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_menuitems_parent` FOREIGN KEY (`parent_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `fk_posts_author` FOREIGN KEY (`author_id`) REFERENCES `authors` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_posts_featured_media` FOREIGN KEY (`featured_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `post_category_post`
--
ALTER TABLE `post_category_post`
  ADD CONSTRAINT `fk_pc_category` FOREIGN KEY (`category_id`) REFERENCES `post_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pc_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `post_tag_post`
--
ALTER TABLE `post_tag_post`
  ADD CONSTRAINT `fk_pt_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pt_tag` FOREIGN KEY (`tag_id`) REFERENCES `post_tags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `testimonials`
--
ALTER TABLE `testimonials`
  ADD CONSTRAINT `fk_testimonial_photo` FOREIGN KEY (`photo_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `testimonial_section`
--
ALTER TABLE `testimonial_section`
  ADD CONSTRAINT `fk_testimonial_section_bg_media` FOREIGN KEY (`bg_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `trust_bullets`
--
ALTER TABLE `trust_bullets`
  ADD CONSTRAINT `fk_trustbullet_section` FOREIGN KEY (`section_id`) REFERENCES `trust_section` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `trust_section`
--
ALTER TABLE `trust_section`
  ADD CONSTRAINT `fk_trust_img` FOREIGN KEY (`image_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

```

* **Connexion BDD:** `App\\Config\\Database\\Config`
* **Plan directeur:** `./Scaffold-Maxima-Project-Executive-Plan.md`
qui est ce même fichier en cours de lecture.
---

### 1. Architecture cible (principes et composants)

- **Principes**
  - Séparation nette en couches : Presentation (controllers, vues), Application (services, use cases), Domain (entités, règles métier), Infrastructure (BD, stockage, intégrations).
  - Inverser les dépendances (DI) pour tester et remplacer les implémentations.
  - Petite surface d’API stable pour le dashboard (REST/JSON) + pages server-rendered pour administration si besoin.
  - Designs idempotents, immuables pour entités critiques, validations côté serveur et client.

- **Composants**
  - Backend PHP (ton code restructuré, PSR-12), avec conteneur d’injection (simple ou PSR-11).
  - API REST JSON (controllers distincts ou adaptateurs) et/ ou GraphQL selon besoins.
  - Frontend Dashboard : SPA en React/Vue/ Svelte (préconisation : React + TypeScript), ou admin minimal en server-side rendered + progressive enhancement.
  - Base de données : MySQL/MariaDB (schéma versionné via migrations).
  - Auth & ACL : JWT / session sécurisée + rôle/permission (RBAC).
  - Observabilité : logs structurés, métriques (Prometheus), traces (OpenTelemetry), alerting.
  - CI/CD : lint, tests, build, container image, déploiement automatisé.
  - Infrastructure : conteneurisation (Docker), orchestrateur (Kubernetes ou services managés), stockage des secrets.

---

### 2. Structure du dépôt et conventions

- Racine
  - /bin — outils CLI (ton scaffold)
  - /config — configuration (env examples, services)
  - /src
    - /Application (controllers, DTOs, HTTP adapters)
    - /Domain (entités, repository interfaces, services métier)
    - /Infrastructure (persistence, repos impl, hydrators, mail, cache)
    - /UI (vues server-rendered)
  - /templates — templates Twig/Plate/Blade ou fichiers PHP proprement isolés
  - /public — front controller (index.php), assets
  - /tests — unit & integration
  - /migrations — SQL / migration tool (Phinx/Doctrine Migrations)
  - /docker — Dockerfiles, compose
  - /.github/workflows — CI pipeline
  - /docs — architecture, guides de style, procédures opérationnelles

Conventions
- PSR-12, namespace conforme au chemin, strict_types partout.
- Commit messages conventionnels (Conventional Commits).
- Versioning sémantique (SemVer).

---

### 3. Qualité logicielle : tests, lint, sécurité

- Tests
  - Unitaires (PHPUnit) pour Domain et services.
  - Tests d’intégration pour repos (base de données via fixtures/containers).
  - Tests end‑to‑end (Cypress/Playwright) pour le dashboard.
  - Couverture minimale cible : 70% → augmenter selon criticité.
- Linting / Static analysis
  - PHPStan (niveau 7+), Psalm, phpcs (PSR-12).
  - Prettier/ESLint + TypeScript strict pour frontend.
- Sécurité
  - Scanner dépendances (Dependabot / Snyk).
  - Analyse statique SAST (SonarQube/CodeQL).
  - Protection contre XSS/CSRF/SQLi : prepared statements, escaping, token CSRF sur forms.
  - Gestion des secrets : Vault/Azure KeyVault/Secrets Manager.
- Politique de mot de passe/2FA pour comptes admin.
- Revue de code obligatoire (Pull Request + approbations).

---

### 4. Observabilité et exploitation

- Logs
  - Logs structurés (JSON) envoyés à une solution centralisée (ELK/EFK, Datadog).
- Metrics & Tracing
  - Exposer métriques Prometheus (latence, erreurs, throughput).
  - Instrumentation OpenTelemetry (traces distribuées).
- Monitoring & Alerting
  - Dashboards Grafana ; alertes sur latence, erreurs 5xx, saturation DB, échecs cron.
- Health & Readiness
  - Endpoints /health /ready avec checks DB, cache, queue.
- Backups & DR
  - Sauvegardes régulières DB, tests de restauration, plan de reprise.
- Runbooks
  - Procédures pour incidents fréquents (rebuild cache, rotate keys, rollback).

---

### 5. Déploiement, infra & CI/CD

- Développement local
  - Docker Compose pour stack (PHP-FPM/Nginx, MySQL, Redis).
- CI pipeline (GitHub Actions / GitLab CI)
  - On push/PR : linters, static analysis, unit tests, build frontend, build docker image, scan vuln.
  - On merge to main: build image, push registry, déploiement staging.
- CD / Déploiement
  - Staging + Production ; approbations manuelles pour prod.
  - Canary / blue‑green si possible.
  - K8s (Helm) ou plateforme managée (App Service, Cloud Run, AKS/GKE/EKS).
- Secrets & config
  - 12‑factor config via environment variables + secret manager.
- Rollback
  - Tagging des images, scripts de rollback automatisés.

---

### 6. Roadmap pratique et livrables (phases & durée indicative)

- Phase A — Fondation (1–2 semaines)
  - Restructuration repo selon structure proposée.
  - Intégrer PSR config, linters, CI baseline (lint + php -l + phpstan lvl 5).
  - Docker Compose local.
  - Migration simple + outil de migration.
  - Livrable : repository initial, pipeline CI minimal.

- Phase B — Stabilisation & tests (2–3 semaines)
  - Écrire tests unitaires pour Domain + Hydrator + Repository.
  - Intégrer PHPStan/ Psalm niveau plus strict.
  - Ajouter scan vulnérabilités automatiques.
  - Livrable : couverture minimale, ci green.

- Phase C — API & Dashboard (3–6 semaines)
  - Définir endpoints REST (OpenAPI spec).
  - Choisir frontend (React+TS) ; scaffolder pages CRUD consommant l’API.
  - Auth basique & RBAC pour admin.
  - Livrable : dashboard CRUD fonctionnel avec authentification.

- Phase D — Observabilité & Production (2–4 semaines)
  - Metrics, logs centralisés, traces.
  - Déploiement staging, tests de charge légers, backup.
  - Livrable : pipeline CD, monitoring, docs runbook.

- Phase E — Raffinements et sécurité (continu)
  - Hardening, audits, optimisation, UX polish.

---

### 7. Artefacts à produire maintenant (action immédiate — 1ère itération)

- README projet avec architecture et quickstart Docker.
- Template .env.example et script d’installation local.
- Configuration CI minimal (lint + tests).
- Migration initiale pour table "services" (exemple).
- Endpoint REST basique pour services (index, show, create, update, delete) + Postman/Swagger.
- Prototype minimal de dashboard (one page list + form) en React/TS ou server-rendered.

---

### 8. Checklist de validation avant production

- [ ] Tests unitaires et d’intégration verts.
- [ ] Scans de vulnérabilités corrigés ou évalués.
- [ ] Metrics + logs visibles dans dashboards.
- [ ] Backups automatiques et vérifiés.
- [ ] Processus de déploiement documenté et automatisé.
- [ ] Politique d’accès et comptes admin configurés (2FA).
- [ ] Runbooks pour incidents courants.

---

### Prochaine étape concrète (choix à exécuter)

Dis lequel tu veux que je fasse en premier — je l’exécute et te fournis les livrables exacts :
1. Générer la structure de repo (+ Docker Compose + README + .env.example) prête à cloner.  
2. Ecrire pipeline CI (GitHub Actions) minimal + configuration PHPStan + phpcs.  
3. Créer endpoint REST OpenAPI + contrôleur d’exemple pour la table services + migration SQL.  
4. Scaffolder un prototype de dashboard en React/TypeScript consommant l’API (liste + formulaire).  

Choisis 1, 2, 3 ou 4 et je m’occupe de préparer tous les fichiers nécessaires prêts à coller.