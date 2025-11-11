# Guide d'utilisation — Journal de Progression

Ce guide explique comment utiliser `bin/log_progress.php` pour enregistrer systématiquement le début et la fin d'une étape de travail.

Pourquoi ?
- Assurer traçabilité et responsabilité pour chaque étape
- Faciliter les revues post-mortem et les rapports d'avancement
- Permettre à l'équipe (ou CI) d'automatiser la création d'entrées

Prérequis
- PHP CLI disponible
- Fichier `docs/PROGRESS_JOURNAL.md` présent

Utilisation basique

1. Enregistrer le démarrage d'une tâche :

```powershell
php bin/log_progress.php start --id=A.05 --phase="Phase B" --title="Écrire tests unitaires" --owner="alice" --desc="Création des tests pour les entités"
```

2. Enregistrer la fin d'une tâche :

```powershell
php bin/log_progress.php finish --id=A.05 --owner="alice" --notes="All tests green" --artifacts="tests/Domain" --commands="vendor\\bin\\phpunit.bat --colors=never"
```

Options utiles

- `--format=json` ou `--format=jsonl` : écrit aussi une ligne JSON (JSONL) dans `docs/progress_entries.jsonl` (facilement ingérable par CI).
- `--no-md` : lorsque vous utilisez `--format=json`, ajoutez `--no-md` si vous ne voulez pas écrire simultanément la note Markdown dans `docs/PROGRESS_JOURNAL.md`.
- `--out-json=PATH` et `--out-journal=PATH` : rediriger les sorties vers des chemins personnalisés (utile pour tests/CI).
- `--rotate-size=BYTES` : taille seuil pour archiver/rotater le fichier JSONL (par défaut 5MB).

Exemple (CI) : n'écrire que JSONL et éviter le MD

```powershell
php bin/log_progress.php start --id=A.10 --phase="CI" --title="Run Tests" --owner="ci" --desc="Pipeline tests" --format=jsonl --no-md --out-json="/tmp/ci_progress.jsonl"
```

Exemple d'entrée JSONL produite (une ligne par entrée) :

```json
{"id":"A.10","phase":"CI","title":"Run Tests","status":"in-progress","timestamp":"2025-11-03T15:04:00+01:00","owner":"ci","description":"Pipeline tests","artifacts":[],"commands":[],"notes":""}
```

Bonnes pratiques
- Exécuter `start` juste avant de lancer une modification significative (feature, migration, refactor)
- Exécuter `finish` quand la tâche est réellement terminée et vérifiée
- Inclure les commandes exactes utilisées pour faciliter la reproduction
- Ajouter les artefacts produits (noms de fichiers, migrations appliquées)

Intégration CI / hooks git (suggestion)
- Option 1 (CI) : le pipeline peut appeler `bin/log_progress.php finish` automatiquement après une étape (ex: tests passés)
- Option 2 (git hook) : installer un hook `post-merge` ou `post-commit` qui rappelle de journaliser ou exécute automatiquement pour certaines branches (ex: `main`, `staging`).

Limites et extensions futures
- Aujourd'hui le script est minimal et ajoute des blocs Markdown à `docs/PROGRESS_JOURNAL.md`.
- Améliorations possibles :
  - Stocker les entrées au format JSON/YAML et générer la vue Markdown
  - Interface web pour visualiser et filtrer les étapes
  - Intégration avec un ticketing (GitHub Issues) pour lier les IDs

Si vous voulez, je peux :
- Ajouter une option `--format=json` pour produire une copie JSON de chaque entrée
- Ajouter un petit script Node/PHP pour afficher un tableau récapitulatif triable
