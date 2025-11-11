 # Journal de Progression du Projet Scaffolder

Dernière mise à jour : 2025-11-03

Ce journal contient des entrées horodatées et structurées pour chaque étape du projet.
Il est conçu pour être mis à jour automatique via le script `bin/log_progress.php` (voir `docs/PROGRESS_GUIDE.md`) ou manuellement.

---

## Format d'une entrée

Chaque entrée doit contenir les champs suivants afin d'assurer traçabilité et reproductibilité :

- ID (unique pour l'étape, ex: A.1, B.2)
- Phase (Phase A / Phase B / Phase C)
- Titre
- Statut : not-started | in-progress | completed
- Démarré le (horodatage ISO 8601)
- Terminé le (horodatage ISO 8601, si applicable)
- Responsable (owner)
- Description (ce qui est fait)
- Artéfacts (fichiers créés/modifiés, migrations exécutées, tests lancés)
- Commandes exécutées (shell / composer / phinx / phpunit)
- Vérification (comment la réussite a été confirmée)
- Notes / Liens

Format (exemple en Markdown) :

---

### ID: A.01 — Configuration Initiale
Phase: Phase A — Fondation
Statut: completed
Démarré: 2025-11-03T09:00:00+01:00
Terminé: 2025-11-03T10:00:00+01:00
Responsable: system

Description:

Mise en place du squelette PSR-4, configuration Composer et dépendances dev.

Artéfacts:

- `composer.json` (autoload PSR-4)
- `phpunit.xml.dist`, `phpstan.neon`, `phpcs.xml`

Commandes:

```powershell
composer install
vendor\bin\phpunit.bat --colors=never
```

Vérification:

- `phpunit` retourne OK (3 tests)

Notes:

- Baseline prête pour Phase B

---

## Entrées (chronologique)

<!-- Les nouvelles entrées doivent être ajoutées au-dessus de cette ligne -->

### ID: A.04 — Correction des dépréciations
Phase: Phase A — Fondation
Statut: completed
Démarré: 2025-11-03T13:45:00+01:00
Terminé: 2025-11-03T14:30:00+01:00
Responsable: system

Description:

Résolution des avertissements de dépréciation remontés par PHPUnit et PHPStan. Mise à jour de dépendances et corrections de configurations.

Artéfacts:

- `composer.json` (Phinx mis à `^0.13`)
- `phpstan.neon` (remplacement `autoload_files` -> `bootstrapFiles`)
- `phpunit.xml.dist` (utilisation du schéma XSD et params d'affichage)

Commandes:

```powershell
composer update robmorgan/phinx --with-dependencies
vendor\bin\phpunit.bat --colors=never
```

Vérification:

- `phpunit` : OK (3 tests, 9 assertions), plus d'avertissements de dépréciation

Notes:

- La correction permet de passer proprement à la stabilisation (Phase B)

---

## Comment enregistrer une étape

1. Avant de lancer un travail important : exécutez `php bin/log_progress.php start --id ID --phase "Phase X" --title "Titre" --owner "VotreNom" --desc "Brève description"`.
2. À la fin, exécutez `php bin/log_progress.php finish --id ID --notes "résumé/verifications" --artifacts "list" --commands "..."`.

Voir `docs/PROGRESS_GUIDE.md` pour les détails et exemples.

---