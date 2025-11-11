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
