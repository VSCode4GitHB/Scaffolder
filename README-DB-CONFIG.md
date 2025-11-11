# Database configuration (class) — Usage & developer notes

This file documents the new DB configuration approach and basic developer flows.

## Purpose
The project now uses an object-oriented database configuration class instead of a plain `config/database.php` file. The class is `App\Config\Database\Config` located at `config/Database/Config.php`.

This README explains how to use it, how to run local checks (PHPStan/PHPCS/PHPUnit) and how to run the test bootstrap.

---

## How to use `Config\Database\Config`

The class exposes a ready PDO connection on instantiation and a diagnostics array:

- `new \App\Config\Database\Config()` — constructs the config, validates environment, and opens a PDO connection.
- `$config->connection` — the `PDO` instance.
- `$config->getDiagnostics()` — returns an array of diagnostics collected during validation/connection attempts.

Example (simple usage):

```php
use App\Config\Database\Config;

require_once __DIR__ . '/vendor/autoload.php';

$config = new Config();
$pdo = $config->connection; // PDO ready to use
$diag = $config->getDiagnostics();

$stm = $pdo->query('SELECT 1');
```

Notes:
- The class reads database settings from environment variables (via `vlucas/phpdotenv` loaded by `config/bootstrap.php`).
- Use `.env` for local dev and `.env.testing` for PHPUnit (already present in the project).

Relevant env variables (examples):
- `DB_DRIVER` (mysql|pgsql|mariadb|sqlite)
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASSWORD`
- `DB_CHARSET`

Do NOT commit secrets into `.env` — the repo's `.gitignore` already excludes `.env`.

---

## Running checks locally (PowerShell / pwsh)

Run the following from project root (Windows/pwsh):

```powershell
# Static analysis (PHPStan)
vendor/bin/phpstan analyse --memory-limit=256M

# Coding standard checks (PHP_CodeSniffer)
vendor/bin/phpcs

# Auto-fix trivial code style issues
vendor/bin/phpcbf

# Unit tests
vendor/bin/phpunit
```

If composer dependencies are not installed yet:

```powershell
composer install --prefer-dist --no-interaction
```

---

## Running tests with the testing environment

A `.env.testing` file exists and configures tests to use SQLite in-memory by default. PHPUnit bootstraps `config/bootstrap.php` which loads `.env.testing` when running tests.

You can run tests and see diagnostics with:

```powershell
# Run phpunit
vendor/bin/phpunit --testdox
```

If you need integration tests against MySQL/MariaDB, update `.env.testing` or set `DB_DRIVER`/`DB_HOST`/... in your CI runner and ensure migrations are run prior to tests.

---

## Next recommended dev tasks

- Add migration tooling and at least one sample migration (Phinx or Doctrine Migrations). This will let integration tests run reliably against real schema.
- Add integration tests covering repository implementations (use SQLite or containerized DB in CI).
- Add a short `CONTRIBUTING.md` describing commit convention and how to run local checks.

---

If you want, I can add a `README.md` at the repo root with this content and also create a short `CONTRIBUTING.md`. Which one do you want me to add next? (I can add both.)