# Project Scaffold (PHP CRUD + Admin)

But : fournir une base solide pour transformer le script de scaffolding en une application modulaire, testable et déployable.

Prérequis
- Docker & Docker Compose
- PHP 8.1+ si exécution hors conteneur
- Résolution Composer figée sur plateforme PHP 8.1 pour garantir la reproductibilité (voir plus bas). Même si votre machine est en PHP 8.2/8.3, Composer simulera 8.1 lors de la résolution des dépendances.
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

Compatibilité PHP / Composer

- Baseline PHP du projet: 8.1 (cf. `composer.json` et ce README).
- Le fichier `composer.json` contient `config.platform.php = 8.1.0` afin que la résolution des dépendances reste compatible PHP 8.1 en CI et en local.
- Après modification de dépendances, exécutez :

```powershell
composer update --with-all-dependencies
```

Puis validez le nouveau `composer.lock`.

CI GitHub Actions

- Si votre workflow CI utilise PHP 8.1, `composer install` fonctionnera avec le `composer.lock` généré pour 8.1.
- Si vous passez la CI en PHP 8.2/8.3, deux choix :
  - conserver `config.platform.php=8.1` (les paquets compatibles 8.1 fonctionnent aussi en 8.2/8.3) ;
  - ou supprimer/monter la plateforme (et régénérer le lock) pour cibler 8.2/8.3.

Docker (version de PHP dans le container)

- L'image Docker utilise par défaut PHP 8.2, tout en supportant l'exécution du projet 8.1+. La version de PHP peut être surchargée au build grâce à l'argument `PHP_VERSION` (propagé depuis `docker-compose.yml`).
- Exemples :

```powershell
# Windows PowerShell — forcer une image PHP 8.1
$env:PHP_VERSION = '8.1'; docker-compose build --no-cache php
docker-compose up -d

# Revenir à 8.2 (par défaut)
Remove-Item Env:PHP_VERSION
docker-compose build --no-cache php
```

```bash
# Bash — forcer une image PHP 8.1
PHP_VERSION=8.1 docker-compose build --no-cache php
docker-compose up -d
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

Dernière mise à jour : 2025-11-29T08:08:00+00:00
