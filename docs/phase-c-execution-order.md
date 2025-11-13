# Phase C — Ordre d'Exécution Logique et Optimisé

**Date :** 12 novembre 2025  
**Branche :** Phase-C  
**Statut :** Plan d'exécution finalisé

---

## 📋 Vue d'ensemble : Tâches de Phase C

### Objectif global
Transformer l'application CLI en plateforme interactive avec REST API fonctionnelle et dashboard CRUD avec authentification.

### Livrables attendus
✅ REST API complète (CRUD pour ressources principales)  
✅ Dashboard React+TS avec authentification RBAC  
✅ Spécification OpenAPI/Swagger  
✅ Pipeline CI/CD mis à jour pour frontend  
✅ Documentation API + runbooks  

---

## 🎯 Ordre d'exécution : 5 phases stratégiques

### **PHASE C.0 — Fondation API (2–3 jours)**
*Préparer l'infrastructure backend pour l'API REST*

| # | Tâche | Dépendances | Fichiers clés | Priorité |
|---|-------|------------|---------------|----------|
| C.0.1 | Créer structure `src/Application/Controller/Api/` | Rien | `/src/Application/Controller/Api/BaseApiController.php` | 🔴 CRITIQUE |
| C.0.2 | Implémenter `BaseApiController` (réponses standardisées) | C.0.1 | `/src/Application/Controller/Api/BaseApiController.php` | 🔴 CRITIQUE |
| C.0.3 | Créer DTOs pour sérialisation (ProjectDTO, TemplateDTO, ComponentDTO) | C.0.2 | `/src/Application/DTO/` | 🔴 CRITIQUE |
| C.0.4 | Implémenter Middleware d'auth + RBAC basique | C.0.2 | `/src/Application/Middleware/AuthMiddleware.php`, RbacMiddleware.php | 🔴 CRITIQUE |
| C.0.5 | Router API (routes pour /api/v1/projects, templates, components) | C.0.4 | `config/routes.php` ou `/src/Application/Router/ApiRouter.php` | 🟠 HAUTE |
| C.0.6 | Implémenter handlers d'erreurs API (400, 401, 403, 404, 500) | C.0.2 | `/src/Application/Exception/ApiException.php`, handlers | 🟠 HAUTE |

**Livrables C.0 :**
- Structure de base pour tous les contrôleurs API
- Middleware d'authentification fonctionnel
- Format de réponse standardisé (JSON avec metadata)
- Gestion d'erreurs centralisée

**Tests :** Unit tests pour BaseApiController, DTOs, Middleware

---

### **PHASE C.1 — API CRUD Complète (4–5 jours)**
*Implémenter endpoints REST pour les 3 ressources principales*

| # | Tâche | Dépendances | Fichiers clés | Priorité |
|---|-------|------------|---------------|----------|
| C.1.1 | `GET /api/v1/projects` (list + pagination) | C.0 | `ProjectController.php::list()` | 🔴 CRITIQUE |
| C.1.2 | `GET /api/v1/projects/{id}` (detail) | C.1.1 | `ProjectController.php::show()` | 🔴 CRITIQUE |
| C.1.3 | `POST /api/v1/projects` (create) | C.1.2 | `ProjectController.php::create()` | 🔴 CRITIQUE |
| C.1.4 | `PUT /api/v1/projects/{id}` (update) | C.1.3 | `ProjectController.php::update()` | 🔴 CRITIQUE |
| C.1.5 | `DELETE /api/v1/projects/{id}` (delete) | C.1.4 | `ProjectController.php::delete()` | 🟠 HAUTE |
| C.1.6 | Templates CRUD (`/api/v1/templates/*`) | C.1.5 | `TemplateController.php` (repeat C.1.1–C.1.5) | 🟠 HAUTE |
| C.1.7 | Components CRUD (`/api/v1/components/*`) | C.1.6 | `ComponentController.php` (repeat C.1.1–C.1.5) | 🟠 HAUTE |
| C.1.8 | Validations d'entrée + sanitization | C.1.7 | `/src/Application/Validator/` | 🟠 HAUTE |
| C.1.9 | Tests unitaires pour tous les contrôleurs | C.1.8 | `/tests/Unit/Application/Controller/Api/*` | 🟠 HAUTE |
| C.1.10 | Tests d'intégration API (avec DB réelle) | C.1.9 | `/tests/Integration/Api/*` | 🟠 HAUTE |

**Livrables C.1 :**
- 5 endpoints par ressource × 3 ressources = 15 endpoints fonctionnels
- Validation + gestion d'erreurs pour chaque endpoint
- Pagination et filtrage optionnels
- Tests couvrant 80%+ des chemins heureux et d'erreur

**Tests :** 
- Unit tests pour contrôleurs (mock repositories)
- Integration tests avec database réelle (SQLite test)
- Postman/curl scripts pour validation manuelle

---

### **PHASE C.2 — Authentification & Autorisation (2–3 jours)**
*Implémenter JWT ou session + RBAC pour accès API*

| # | Tâche | Dépendances | Fichiers clés | Priorité |
|---|-------|------------|---------------|----------|
| C.2.1 | Migration pour table `users` + table `roles` | Rien | `/migrations/20251112_create_users_roles_tables.php` | 🔴 CRITIQUE |
| C.2.2 | Modèle User + UserRepository | C.2.1 | `/src/Domain/Entity/User.php`, `/src/Infrastructure/Repository/UserRepository.php` | 🔴 CRITIQUE |
| C.2.3 | Implémenter JWT (JsonWebToken) ou session-based auth | C.2.2 | `/src/Application/Auth/JwtService.php` ou `SessionService.php` | 🔴 CRITIQUE |
| C.2.4 | Endpoint `POST /api/v1/auth/login` | C.2.3 | `/src/Application/Controller/Api/AuthController.php` | 🟠 HAUTE |
| C.2.5 | Endpoint `POST /api/v1/auth/logout` | C.2.4 | `AuthController.php::logout()` | 🟠 HAUTE |
| C.2.6 | Endpoint `POST /api/v1/auth/refresh` (JWT refresh) | C.2.5 | `AuthController.php::refresh()` (JWT only) | 🟠 HAUTE |
| C.2.7 | Implémenter RBAC (admin, editor, viewer roles) | C.2.6 | `RbacMiddleware.php`, `/src/Application/Auth/RbacService.php` | 🔴 CRITIQUE |
| C.2.8 | Décorateurs/attributs pour protéger endpoints par rôle | C.2.7 | `@Requires('admin')` ou similaire | 🟠 HAUTE |
| C.2.9 | Tests d'authentification (login/logout/refresh) | C.2.8 | `/tests/Unit/Application/Auth/*`, `/tests/Integration/Api/AuthTest.php` | 🟠 HAUTE |
| C.2.10 | Tests de RBAC (unauthorized access denials) | C.2.9 | `/tests/Integration/Api/RbacTest.php` | 🟠 HAUTE |

**Livrables C.2 :**
- Table `users` avec email, password_hash, roles_json ou FK
- Table `roles` avec permissions (admin, editor, viewer)
- JWT ou session tokens valides et testés
- Endpoints auth (login/logout/refresh) fonctionnels
- Middleware RBAC bloquant accès non-autorisé
- Documentation des rôles et permissions

**Tests :**
- Unit tests pour JwtService, RbacService
- Integration tests pour endpoints auth
- Tests de refus d'accès pour chaque rôle

---

### **PHASE C.3 — Dashboard Frontend (5–7 jours)**
*Construire interface React+TS consommant l'API*

| # | Tâche | Dépendances | Fichiers clés | Priorité |
|---|-------|------------|---------------|----------|
| C.3.1 | Scaffolder projet React+TypeScript (Vite ou CRA) | C.1 (API fonctionnelle) | `/frontend/package.json`, vite.config.ts ou tsconfig.json | 🔴 CRITIQUE |
| C.3.2 | Configurer ESLint + Prettier + TypeScript strict | C.3.1 | `/frontend/.eslintrc.json`, `prettier.config.js` | 🟠 HAUTE |
| C.3.3 | Client API HTTP (axios/fetch wrapper) | C.3.1 | `/frontend/src/lib/api.ts`, `/frontend/src/lib/client.ts` | 🔴 CRITIQUE |
| C.3.4 | Page Login (form + JWT/session storage) | C.3.3 + C.2 | `/frontend/src/pages/LoginPage.tsx` | 🔴 CRITIQUE |
| C.3.5 | Layout/Navigation (header, sidebar, logout button) | C.3.4 | `/frontend/src/layouts/MainLayout.tsx` | 🟠 HAUTE |
| C.3.6 | Page Projects (list + pagination) | C.3.5 + C.1.1 | `/frontend/src/pages/ProjectsPage.tsx` | 🔴 CRITIQUE |
| C.3.7 | Page Project Detail + Edit Form | C.3.6 + C.1.2 + C.1.4 | `/frontend/src/pages/ProjectDetailPage.tsx`, `ProjectFormModal.tsx` | 🔴 CRITIQUE |
| C.3.8 | Page Create Project | C.3.7 + C.1.3 | `/frontend/src/pages/ProjectCreatePage.tsx` ou modal réutilisable | 🔴 CRITIQUE |
| C.3.9 | Delete confirmation modal + action | C.3.8 + C.1.5 | `/frontend/src/components/DeleteConfirmModal.tsx` | 🟠 HAUTE |
| C.3.10 | Templates list/CRUD pages (repeat C.3.6–C.3.9) | C.3.9 + C.1.6 | `/frontend/src/pages/TemplatesPage.tsx`, TemplateForms | 🟠 HAUTE |
| C.3.11 | Components list/CRUD pages (repeat C.3.6–C.3.9) | C.3.10 + C.1.7 | `/frontend/src/pages/ComponentsPage.tsx`, ComponentForms | 🟠 HAUTE |
| C.3.12 | State management (Zustand/Redux Lite ou React Context) | C.3.4 | `/frontend/src/store/authStore.ts` ou `contexts/AuthContext.tsx` | 🟠 HAUTE |
| C.3.13 | Route protection (PrivateRoute, role-based redirects) | C.3.12 + C.2.7 | `/frontend/src/components/PrivateRoute.tsx` | 🟠 HAUTE |
| C.3.14 | Error handling + toast notifications | C.3.13 | `/frontend/src/components/Toast.tsx`, error boundary | 🟠 HAUTE |
| C.3.15 | Tests React (Jest + React Testing Library) | C.3.14 | `/frontend/src/__tests__/*` | 🟠 HAUTE |

**Livrables C.3 :**
- Interface Login fonctionnelle avec token management
- Pages CRUD pour Projects, Templates, Components
- Navigation + layout cohérent
- Validation côté client
- Gestion d'erreurs et feedback utilisateur
- Couverture minimale de tests (>50%)

**Tests :**
- Unit tests pour hooks, services API
- Component tests pour pages principales
- E2E optionnel (Cypress/Playwright) pour flux critiques

---

### **PHASE C.4 — Intégration, Documentation & CI/CD (3–4 jours)**
*Finaliser, tester end-to-end et documenter*

| # | Tâche | Dépendances | Fichiers clés | Priorité |
|---|-------|------------|---------------|----------|
| C.4.1 | Mise à jour CI/CD pour build frontend (npm build) | C.3.1 | `.github/workflows/ci.yml` ajout de `npm install && npm run build` | 🔴 CRITIQUE |
| C.4.2 | Spécification OpenAPI/Swagger pour API | C.1.9 | `/docs/openapi.yaml` ou `swagger.json` | 🟠 HAUTE |
| C.4.3 | Documentation API (endpoints, auth, errors) | C.4.2 | `/docs/api-documentation.md` | 🟠 HAUTE |
| C.4.4 | Swagger UI endpoint (`/api/docs` ou `/swagger-ui.html`) | C.4.2 | Middleware Swagger + `/public/swagger-ui/` | 🟠 HAUTE |
| C.4.5 | Documentation setup local (Docker + frontend serving) | C.3.1 | `docker-compose.yml` ajout `frontend: ...` service | 🟠 HAUTE |
| C.4.6 | Tests end-to-end (backend + frontend intégrés) | C.3.15 + C.1.9 | `/tests/E2E/*` (Postman CLI ou Playwright) | 🟠 HAUTE |
| C.4.7 | Runbooks + troubleshooting guide | C.4.4 | `/docs/runbooks/common-issues.md` | 🟡 MOYENNE |
| C.4.8 | README Phase C + QUICK START | C.4.7 | `README.md` section Phase C | 🟠 HAUTE |
| C.4.9 | Audit de sécurité API (OWASP Top 10) | C.4.6 | Checklist + fixes | 🔴 CRITIQUE |
| C.4.10 | Déploiement staging et validations | C.4.9 | Déploiement docker-compose, tests manuels | 🟡 MOYENNE |

**Livrables C.4 :**
- Pipeline CI/CD vert (tests + build backend + build frontend)
- Spécification OpenAPI complète
- Documentation d'utilisation de l'API
- Guide de configuration locale
- Runbooks pour incidents courants
- Checklist de sécurité validée

**Tests :**
- CI/CD green (tous les tests passing)
- E2E happy paths (login → CRUD → logout)
- Tests de charge légers (artillery ou k6)

---

## 📊 Résumé : Timeline Consolidée

```
PHASE C.0 (Fondation API)          : 2–3 jours   | 6 tâches
    ↓
PHASE C.1 (API CRUD)               : 4–5 jours   | 10 tâches
    ↓
PHASE C.2 (Auth & RBAC)            : 2–3 jours   | 10 tâches
    ↓
PHASE C.3 (Frontend React+TS)      : 5–7 jours   | 15 tâches
    ↓
PHASE C.4 (Intégration & Docs)     : 3–4 jours   | 10 tâches

─────────────────────────────────────────────────
TOTAL PHASE C                      : ~18–25 jours  | 51 tâches

(dans un processus itératif avec code reviews + tests parallélisés,
estimé 3–4 semaines calendrier)
```

---

## 🔄 Dépendances Critiques

### Chemin critique (sans lequel rien ne fonctionne)

```
C.0.1 → C.0.2 → C.0.4 → C.0.5
   ↓       ↓       ↓       ↓
C.1.1 → C.1.3 → C.1.4 → C.1.8 → C.1.9
                            ↓
C.2.1 → C.2.2 → C.2.3 → C.2.4 → C.2.7 → C.2.9
                            ↓       ↓
C.3.1 → C.3.3 → C.3.4 → C.3.6 → C.3.7 → C.3.8 → C.3.15
                    ↓
                 C.4.6 (E2E tests)
                    ↓
                 C.4.1 (CI/CD)
                    ↓
                 C.4.9 (Sécurité)
```

### Tâches parallélisables

- **C.1.1–C.1.5** (Projects endpoints) peuvent être parallélisées
- **C.1.6–C.1.7** (Templates & Components) peuvent être parallélisées après C.1.5
- **C.3.6–C.3.11** (Pages CRUD) peuvent être partiellement parallélisées
- **C.3.15** (Tests) peut commencer dès C.3.4 (Login page)
- **Documentation** (C.4.2–C.4.3) peut démarrer après C.1.9

---

## ✅ Critères de Succès par Phase

### C.0 (Fondation)
- [ ] Structure `/src/Application/Controller/Api/` créée
- [ ] BaseApiController implémenté avec réponses JSON standardisées
- [ ] Middleware d'authentification fonctionnel
- [ ] Gestion d'erreurs API couvrant 4xx et 5xx
- [ ] Tests unitaires > 80% de couverture

### C.1 (API CRUD)
- [ ] 15 endpoints implémentés et testés (Projects, Templates, Components CRUD)
- [ ] Pagination + filtrage optionnels
- [ ] Validation d'entrée rigoureuse
- [ ] Tests > 80% de couverture
- [ ] Tests d'intégration avec DB réelle réussis
- [ ] Postman/curl scripts documentés

### C.2 (Auth & RBAC)
- [ ] Migrations users/roles créées et appliquées
- [ ] JWT ou session tokens valides
- [ ] Endpoints auth (login/logout/refresh) fonctionnels
- [ ] Middleware RBAC bloquant accès non-autorisé
- [ ] Tests d'authentification > 80% de couverture
- [ ] Tests de RBAC validant refus d'accès

### C.3 (Frontend)
- [ ] Projet React+TS scaffoldé avec Vite
- [ ] Login page fonctionnelle
- [ ] Pages CRUD pour 3 ressources
- [ ] Token management (JWT/session storage)
- [ ] Navigation + layout cohérents
- [ ] Gestion d'erreurs + notifications
- [ ] Tests React > 50% de couverture
- [ ] Build et bundle sans erreurs

### C.4 (Intégration)
- [ ] CI/CD vert (tests + build backend/frontend)
- [ ] OpenAPI spec complète
- [ ] Documentation API + setup local
- [ ] E2E tests happy path réussis
- [ ] Audit sécurité OWASP Top 10 complété
- [ ] Deployable sur staging

---

## 🚨 Risques Identifiés et Mitigations

| Risque | Probabilité | Impact | Mitigation |
|--------|------------|--------|-----------|
| **Auth token expiration mal gérée** | Haute | Haute | Tester refresh token flow, ajouter retry logic frontend |
| **CORS bloguant requêtes API** | Moyenne | Haute | Configurer CORS middleware en C.0.4, tester cross-origin |
| **Frontend state management complexe** | Moyenne | Moyenne | Utiliser Context ou Zustand simple, éviter Redux pour cette taille |
| **Migrations DB en conflit** | Basse | Haute | Utiliser timestamps uniques, tester migrations plusieurs fois |
| **Performance API sous charge** | Basse | Moyenne | Ajouter pagination, indexer DB, prévoir Phase D monitoring |
| **Sécurité SQL injection** | Basse | Critique | Utiliser prepared statements partout, tester avec SQLMap |
| **Perte de tokens en cas de refresh échoué** | Basse | Moyenne | Implémenter retry exponential, localStorage + sessionStorage backup |
| **Tests flaky (intermittents)** | Moyenne | Moyenne | Fixture de test isolées, mock external deps, timeout élevés |

---

## 🎬 Points de Démarrage

### Avant de commencer

**Prérequis Phase C :**
- [ ] Branche `Phase-C` créée et synchronisée
- [ ] Phase B 100% complétée (tests ✅, linters ✅)
- [ ] Database migrations de Phase A confirmées fonctionnelles
- [ ] Docker Compose local fonctionnant

**Setup initial :**
```bash
# Créer branche Phase-C depuis main
git checkout -b Phase-C origin/main

# Installer/mettre à jour dépendances
composer install

# Vérifier Phase B
composer test          # Tous les tests doivent passer
vendor/bin/phpstan analyse --level 7 src/
vendor/bin/phpcs --standard=PSR12 src/

# Prêt pour Phase C
echo "✅ Phase B validée, Phase C peut commencer"
```

### Session 1 : Phase C.0 (Fondation)
```bash
# Créer la structure API
mkdir -p src/Application/Controller/Api
mkdir -p src/Application/DTO
mkdir -p src/Application/Middleware
mkdir -p src/Application/Exception

# Commencer par BaseApiController (tâche C.0.2)
touch src/Application/Controller/Api/BaseApiController.php

# Puis créer DTOs
touch src/Application/DTO/ProjectDTO.php
touch src/Application/DTO/TemplateDTO.php
touch src/Application/DTO/ComponentDTO.php

# Puis middleware d'auth
touch src/Application/Middleware/AuthMiddleware.php
touch src/Application/Middleware/RbacMiddleware.php

# Exécuter tests pour C.0
composer test
```

### Session 2 : Phase C.1 (API CRUD)
```bash
# Créer les contrôleurs API
touch src/Application/Controller/Api/ProjectController.php
touch src/Application/Controller/Api/TemplateController.php
touch src/Application/Controller/Api/ComponentController.php

# Impl endpoints CRUD, tester à chaque endpoint
composer test

# Valider avec Postman/curl
curl -X GET http://localhost:8000/api/v1/projects
```

### Session 3 : Phase C.2 (Auth & RBAC)
```bash
# Créer migration users/roles
php vendor/bin/phinx create CreateUsersRolesTables

# Impl UserRepository et Auth services
touch src/Domain/Entity/User.php
touch src/Infrastructure/Repository/UserRepository.php
touch src/Application/Auth/JwtService.php

# Créer contrôleur Auth
touch src/Application/Controller/Api/AuthController.php

# Tester auth
composer test
```

### Session 4 : Phase C.3 (Frontend)
```bash
# Scaffolder React+TS
npm create vite@latest frontend -- --template react-ts
cd frontend
npm install

# Créer client API
mkdir src/lib
touch src/lib/api.ts

# Commencer par login page
mkdir src/pages
touch src/pages/LoginPage.tsx

# Tester build
npm run build
```

### Session 5 : Phase C.4 (Intégration)
```bash
# Générer OpenAPI spec
touch docs/openapi.yaml

# Ajouter Swagger UI
npm install swagger-ui-express --save-dev

# Mettre à jour CI/CD
# Éditer .github/workflows/ci.yml

# Tests E2E
npm install -D cypress  # ou playwright

# Déployer staging local
docker-compose -f docker-compose.yml up -d
```

---

## 📝 Prochaines Actions

1. **Lire ce document** et valider l'ordre proposé
2. **Confirmer ressources disponibles** (1 dev = ~4 semaines, 2 devs = ~2 semaines)
3. **Cloner branche Phase-C** et lancer Session 1 (C.0)
4. **Follow cette roadmap étape par étape**, commitant après chaque tâche complétée
5. **Exécuter tests et CI** après chaque session pour détecter regressions tôt

---

**Prêt à démarrer Phase C ? Dis "Go C.0" et nous commençons ! 🚀**
