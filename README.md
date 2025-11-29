# Project Scaffold (PHP CRUD + Admin)

But : fournir une base solide pour transformer le script de scaffolding en une application modulaire, testable et déployable.

Prérequis
- Docker & Docker Compose
- PHP 8.1+ si exécution hors conteneur
- Composer (optionnel local)
- Node.js + npm/yarn (si frontend)

Démarrage local (Docker)
1. Copier `.env.example` en `.env` et adapter les valeurs si besoin.
2. Lancer l'environnement :
   ```bash
   docker-compose up -d --build


Environment et mise en route (rapide)

1. Copier l'exemple d'environnement :

```powershell
copy .env.example .env
```

2. Installer les dépendances PHP :

```powershell
composer install
```

3. Lancer la stack Docker (optionnel) :

```powershell
docker-compose up -d --build
```

4. Exécuter les migrations de test :

```powershell
vendor\bin\phinx migrate -c phinx.php -e test
```

5. Lancer les tests :

```powershell
vendor\bin\phpunit --colors=never
```

Tests avec couverture (local)

Pour générer le rapport de couverture et vérifier le seuil (≥ 70%), activez Xdebug coverage puis lancez le script Composer.

- PowerShell (Windows):

```powershell
$env:XDEBUG_MODE = 'coverage'; composer test:coverage
```

- Bash (Linux/macOS):

```bash
XDEBUG_MODE=coverage composer test:coverage
```

Le rapport Clover est écrit dans `coverage.xml` et validé par `scripts/check-coverage.php` (seuil 70%).

Regénérer la documentation minimale (timestamp) :

```powershell
composer run docs:update
```

Dernière mise à jour : 2025-11-28T23:06:00+00:00
