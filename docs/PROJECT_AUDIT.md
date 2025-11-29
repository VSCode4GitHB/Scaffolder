# Audit rapide — conformité au plan directeur

Date: 2025-11-03
Auteur: scan automatique + analyse manuelle

## But
Résumé du mapping entre le plan directeur (`Scaffold-Maxima-Project-Executive-Plan.md`) et l'état actuel du dépôt. Recommandations prioritaires et actions immédiates appliquées.

---

## 1) Synthèse rapide
- Le projet contient déjà :
  - `bin/scaffold_v2.php` (script principal de génération) — implémentation riche et conforme aux idées du plan.
  - `config/Database/Config.php` (classe OO de configuration PDO)
  - `docker/` + `docker-compose.yml` (présence d'infrastructure container)
  - `composer.json` avec dev deps (phpunit, phpstan, phpcs)
  - `public/index.php`, `migrations/`, `templates/`, `tests/` (présents mais vides pour la plupart)
- Manquant / incomplet :
  - `src/` structure (vide) : pas encore d'arborescence PSR-4 (Application, Domain, Infrastructure, UI).
  - CI/CD (aucun `.github/workflows` détecté).
  - Configuration linters/analyses (fichiers de config phpstan/phpcs non présents).
  - Tests unitaires / fixtures / migrations gérées (dossier `migrations/` vide).
  - Documentation opérationnelle (docs/ est ajouté par cet audit).

## 2) Cartographie (exigences -> artefacts existants)
- Séparation couches (préconisée)
  - Artefact existant : `bin/scaffold_v2.php` prépare code dans `src/...` mais `src/` n'existe pas encore. Le script est prêt à générer une architecture conforme.
- Conteneurisation & développement local
  - Artefact existant : `docker/`, `docker-compose.yml`, README indique usage Docker.
- Tests & QC
  - Artefact existant : `composer.json` contient `phpunit`, `phpstan`, `php_codesniffer` en dev.
  - Manque : configuration et tests effectifs.
- Migrations & DB
  - Artefact existant : `migrations/` (vide) et `bin/scaffolddb.sql` (dump SQL) disponible.
- Observabilité / CI / Secrets
  - Non présents : workflows CI, configs de monitoring, stockage de secrets.

## 3) Écarts critiques et priorité recommandations
Priorité Critique (à adresser rapidement)
- Créer la structure PSR-4 minimale `src/{Application,Domain,Infrastructure,UI}` pour que le scaffold puisse écrire les fichiers et pour respecter la convention du plan.
- Ajouter configuration minimale de PHPStan / phpcs pour tirer parti des dépendances dev déjà déclarées.

Priorité Haute
- Ajouter un pipeline CI minimal (GitHub Actions) pour lancer `composer install --no-interaction --prefer-dist`, `php -l`, `vendor/bin/phpstan analyse`, `vendor/bin/phpunit --colors=never --log-junit`.
- Ajouter `phpunit.xml.dist` minimal et un exemple de test unitaire.

Priorité Moyenne
- Mettre en place un dossier `migrations/` géré (Phinx/Doctrine Migrations) et documenter la procédure.
- Ajouter le readme technique / runbook minimal.

Priorité Faible
- Dashboard frontend (phase C), OpenTelemetry, Prometheus, etc. à planifier après stabilisation.

## 4) Actions immédiates (low-risk) proposées et appliquées
Appliquées maintenant
- Ajout de ce fichier `docs/PROJECT_AUDIT.md` (synthèse et plan d'actions).

Proposées (prêtes à être appliquées si vous validez)
1. Créer l'arborescence `src/Application`, `src/Domain`, `src/Infrastructure`, `src/UI` et un `src/bootstrap.php` minimal.
2. Ajouter `phpunit.xml.dist` minimal et un test d'exemple `tests/ExampleTest.php`.
3. Ajouter `phpstan.neon` minimal et `phpcs.xml` (PSR-12) config.
4. Ajouter un workflow GitHub Actions basique `.github/workflows/ci.yml`.
5. Ajouter script `composer` pour lint/test (`composer test` => phpstan + phpunit + phpcs).

## 5) Risques / remarques
- Le script de scaffolding peut écrire beaucoup de fichiers; créez une branche dédiée pour expérimenter la génération et vérifiez l'option `--force` avant d'écraser.
- Les identifiants DB doivent être fournis via variables d'environnement (`.env`) et chargés par `vlucas/phpdotenv` (voir `config/Database/Config.php`).

## 6) Prochaines étapes recommandées (livrables immédiats)
- Phase A (fondation, 1–2 jours): créer `src/` skeleton, config QC (phpstan/phpcs/phpunit), CI baseline.
- Phase B (stabilisation, 2–3 jours): écrire tests Domain + Hydrator + Repository, activer phpstan stricte, exécuter CI vert.

---

Fichier généré automatiquement par l'analyse. Pour que j'applique les modifications proposées (création des dossiers skeleton + fichiers de config CI/tests), dites simplement "Appliquer les changements de phase A" et je les implémenterai en modifiant le dépôt et en exécutant des validations rapides.
