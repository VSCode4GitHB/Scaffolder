# Phase C — Cahier des Charges API REST

**Projet** : Scaffolder / CongoleseYouth Platform  
**Date** : 12 novembre 2025  
**Phase** : C (API & Dashboard)  
**Durée estimée** : 3–6 semaines  

---

## 1. Contexte métier

### 1.1 Objectif global

Transformer le scaffold (générateur de code) et la plateforme CongoleseYouth en une **application web modulaire avec API REST + dashboard de gestion administrateur**. 

**Ressources cibles** :
- **Pages d'administration** : gestion des contenus vitrine (services, posts, sections).
- **Authentification** : accès contrôlé au dashboard (JWT ou sessions).
- **CRUD completo** : création, lecture, mise à jour, suppression des entités principales.
- **Intégration frontend** : React + TypeScript consommant l'API.

### 1.2 Utilisateurs finaux

| Rôle | Cas d'usage | Accès |
|------|-----------|-------|
| **Admin** | Gère tous les contenus (services, posts, sections, media). | Full access à `/api/v1/admin/*` |
| **Editor** | Crée/édite posts, services (role-based access). | Lecture+écriture contenus spécifiques |
| **Viewer** | Consultation lectures seules (public si applicable). | GET seulement sur ressources publiques |
| **Public** | Accès web frontend (non-API). | N/A |

---

## 2. Ressources et modèles

### 2.1 Ressources prioritaires (MVP Phase C)

#### Ressource 1: **Services**
- **Modèle DB** : `services` (id, name, slug, icon_class, excerpt, body, details_url, number_badge, order_index, published)
- **Cas d'usage** : CRUD complet, tri par `order_index`, filtrage par `published`.
- **Priorité** : 🔴 **CRITIQUE** (visible sur homepage, gérée admin).

#### Ressource 2: **Posts (Articles)**
- **Modèle DB** : `posts` (id, title, slug, excerpt, body, featured_media_id, author_id, published_at, status)
  - Associations : authors, post_categories, post_tags.
- **Cas d'usage** : CRUD + publication workflow (draft → published).
- **Priorité** : 🟠 **HAUTE** (contenu éditorial, multicritère).

#### Ressource 3: **Media (Bibliothèque)**
- **Modèle DB** : `media` (id, path, title, alt_text, mime_type, width, height, media_type)
- **Cas d'usage** : Upload, CRUD, liaison aux ressources (services, posts).
- **Priorité** : 🟠 **HAUTE** (support images/assets).

#### Ressource 4: **Configuration sections (Singletons)**
- **Modèles DB** : 
  - `company_profile` (id=1 singleton)
  - `hero_section`, `feature_section`, `about_section`, `contact_section`, etc.
- **Cas d'usage** : CRUD configuration unique par section (eyebrow, title, bg_media_id).
- **Priorité** : 🟡 **MOYENNE** (personnalisation, pas CRUD massif).

#### Ressource 5: **Utilisateurs & Authentification**
- **Modèle** : Table `users` (à créer) — id, email, password_hash, role, created_at, updated_at.
- **Cas d'usage** : Login/logout, JWT ou session + RBAC.
- **Priorité** : 🔴 **CRITIQUE** (gate pour tout le dashboard).

### 2.2 Ressources secondaires (Phase C+)
- Testimonials, Skills, FooterColumns, SocialLinks, Menus → CRUD optionnel.
- ContactMessages → GET (read-only, analytics).
- NewsletterSubscribers → POST (subscribe), GET (admin analytics).

---

## 3. Specification OpenAPI (contours)

### 3.1 Structure d'API

```
Base URL: /api/v1
Version: 1.0
Authentication: JWT Bearer Token (or session cookies)
Response format: JSON
```

### 3.2 Endpoints CRUD standard (par ressource)

Pour chaque ressource (Services, Posts, Media, etc.) :

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/{resource}` | Public ou Auth | Lister (pagination, filtrage) |
| GET | `/api/v1/{resource}/{id}` | Public ou Auth | Détail |
| POST | `/api/v1/admin/{resource}` | Admin | Créer |
| PUT | `/api/v1/admin/{resource}/{id}` | Admin/Owner | Mettre à jour |
| DELETE | `/api/v1/admin/{resource}/{id}` | Admin | Supprimer |

### 3.3 Authentification & Autorisations

#### Endpoints d'authentification
```
POST   /api/v1/auth/login       → { email, password } → { token, user }
POST   /api/v1/auth/logout      → { token } → { success }
GET    /api/v1/auth/me          → Get current user profile
POST   /api/v1/auth/refresh     → Refresh JWT token
```

#### Middleware d'autorisations
- **RequireAuth** : Bloquer si aucun token valide.
- **RequireRole(role)** : Bloquer si user.role ≠ role (Admin, Editor, Viewer).
- **OwnerOrAdmin** : Bloquer si user.id ≠ resource.owner_id ET user.role ≠ Admin.

#### RBAC Roles
```
Admin    → Full access (tous endpoints)
Editor   → POST/PUT/DELETE sur posts, services (own ou assigned)
Viewer   → GET seulement
```

### 3.4 Réponses & Codes HTTP

#### Succès (2xx)
- **200 OK** : GET, PUT (modification réussie).
- **201 Created** : POST (création réussie).
- **204 No Content** : DELETE (suppression réussie, pas de body).

#### Client errors (4xx)
- **400 Bad Request** : Validation échouée (missing fields, invalid format).
- **401 Unauthorized** : Missing/invalid token.
- **403 Forbidden** : Insufficient permissions.
- **404 Not Found** : Resource inexistante.
- **409 Conflict** : Slug duplicata, constraint violation.

#### Server errors (5xx)
- **500 Internal Server Error** : Erreur serveur non gérée.
- **503 Service Unavailable** : DB down, external service.

#### Format d'erreur
```json
{
  "error": {
    "code": "INVALID_INPUT",
    "message": "Field 'email' is required",
    "details": {
      "field": "email",
      "rule": "required"
    }
  }
}
```

### 3.5 DTO Structures (exemples)

#### ServiceDTO (request/response)
```json
{
  "id": 1,
  "name": "Support Informatique",
  "slug": "support-informatique",
  "icon_class": "fas fa-headset",
  "excerpt": "Assistance technique...",
  "body": "Description complète...",
  "details_url": "http://...",
  "number_badge": "01",
  "order_index": 1,
  "published": true,
  "created_at": "2025-11-12T10:00:00Z",
  "updated_at": "2025-11-12T10:00:00Z"
}
```

#### PostDTO (request/response)
```json
{
  "id": 1,
  "title": "Titre du post",
  "slug": "titre-du-post",
  "excerpt": "Résumé...",
  "body": "<p>Contenu HTML...</p>",
  "featured_media_id": 2,
  "featured_media": { "id": 2, "path": "...", "alt_text": "..." },
  "author_id": 1,
  "author": { "id": 1, "name": "Jean Doe" },
  "published_at": "2025-11-12T10:00:00Z",
  "status": "published",
  "categories": [{ "id": 1, "name": "Tech" }],
  "tags": [{ "id": 1, "name": "php" }],
  "created_at": "2025-11-12T09:00:00Z",
  "updated_at": "2025-11-12T10:00:00Z"
}
```

#### UserDTO (response)
```json
{
  "id": 1,
  "email": "admin@example.com",
  "name": "Admin User",
  "role": "admin",
  "created_at": "2025-11-01T00:00:00Z"
}
```

---

## 4. Architecture backend

### 4.1 Structure de répertoires (Phase C)

```
src/
├── Application/
│   ├── Controller/
│   │   ├── Api/
│   │   │   ├── AuthController.php
│   │   │   ├── ServiceController.php
│   │   │   ├── PostController.php
│   │   │   ├── MediaController.php
│   │   │   └── ConfigController.php
│   │   └── Web/
│   │       └── DashboardController.php
│   ├── Service/
│   │   ├── AuthService.php
│   │   ├── ServiceService.php
│   │   ├── PostService.php
│   │   └── MediaService.php
│   └── Middleware/
│       ├── AuthMiddleware.php
│       ├── RoleMiddleware.php
│       └── CorsMiddleware.php
├── Domain/
│   └── Entity/
│       ├── User.php
│       ├── Service.php
│       ├── Post.php
│       └── Media.php
├── Infrastructure/
│   ├── Repository/
│   │   ├── UserRepository.php
│   │   ├── ServiceRepository.php
│   │   ├── PostRepository.php
│   │   └── MediaRepository.php
│   ├── Hydration/
│   │   └── DTOHydrator.php
│   └── Security/
│       ├── JwtTokenizer.php
│       └── PasswordHasher.php
└── UI/
    └── Api/
        ├── Request/
        │   ├── LoginRequest.php
        │   ├── ServiceRequest.php
        │   └── PostRequest.php
        └── Response/
            ├── ApiResponse.php
            └── ErrorResponse.php
```

### 4.2 Patterns & technologies

| Aspect | Choix | Justification |
|--------|-------|---------------|
| **API Framework** | Slim 4 + PSR-7 | Léger, composable, compatible PHP 8+ |
| **Authentication** | JWT (Bearer token) | Stateless, scalable, idéal pour SPAs |
| **Authorization** | RBAC (middleware) | Simple, flexibilité pour évolutions |
| **Validation** | PHP Filter + custom validators | Pas de dépendance externe, efficace |
| **Serialization** | Manual DTO hydration | Contrôle complet, pas de magic |
| **Error handling** | Exceptions + centralized handler | Cohérence, logs uniformes |
| **Logging** | Monolog (si disponible) | Standard PSR-3, intégration monitoring |

### 4.3 Middleware stack

```php
// Order matters
App::middleware(CorsMiddleware::class);
App::middleware(JsonBodyParser::class);
App::middleware(RequestLoggingMiddleware::class);
App::middleware(ErrorHandlerMiddleware::class);

// Protected routes
App::group('/api/v1/admin', AuthMiddleware::class, function(Router $r) {
    App::middleware(RoleMiddleware::class, ['admin']);
    // Protected routes
});
```

### 4.4 JWT Token structure

```json
{
  "iss": "https://app.example.com",
  "sub": "user_123",
  "email": "admin@example.com",
  "role": "admin",
  "iat": 1700000000,
  "exp": 1700086400,
  "nbf": 1700000000
}
```

**Durée** : 24h (configurable).  
**Refresh** : Rotation possible via `/api/v1/auth/refresh` (renouvelle token).

---

## 5. Migrations DB & modèles

### 5.1 Migrations à ajouter

#### Migration 1: `create_users_table`
```sql
CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) DEFAULT NULL,
  `role` ENUM('admin', 'editor', 'viewer') NOT NULL DEFAULT 'viewer',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login_at` DATETIME DEFAULT NULL,
  KEY `idx_users_email` (`email`),
  KEY `idx_users_role` (`role`)
);
```

#### Migration 2: Optionnel — `add_soft_delete_to_services`
```sql
ALTER TABLE `services` ADD COLUMN `deleted_at` DATETIME DEFAULT NULL;
ALTER TABLE `posts` ADD COLUMN `deleted_at` DATETIME DEFAULT NULL;
```
(Pour soft-delete, logs d'audit optionnels).

#### Migration 3: Optionnel — `create_audit_log_table`
```sql
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `resource_type` VARCHAR(100) NOT NULL,
  `resource_id` BIGINT UNSIGNED NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `changes` JSON DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_audit_resource` (`resource_type`, `resource_id`),
  KEY `idx_audit_user` (`user_id`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);
```

### 5.2 Modèles (Entity classes)

Chaque entité aura sa classe Domain\\Entity (ex: `User`, `Service`, `Post`, `Media`).

```php
// src/Domain/Entity/User.php
class User {
    public int $id;
    public string $email;
    public string $passwordHash;
    public ?string $name;
    public string $role; // admin, editor, viewer
    public \DateTime $createdAt;
    // ...
}
```

---

## 6. Endpoints détaillés (Phase C MVP)

### 6.1 Authentification

```yaml
POST /api/v1/auth/login
  Request:
    - email: string (required, email format)
    - password: string (required, min 6)
  Response (200):
    - token: string (JWT Bearer token)
    - user: object (UserDTO)
  Errors: 401 (credentials invalid), 400 (validation)

POST /api/v1/auth/logout
  Auth: Required (Bearer token)
  Response (204): No content
  Errors: 401 (invalid token)

GET /api/v1/auth/me
  Auth: Required
  Response (200): UserDTO
  Errors: 401

POST /api/v1/auth/refresh
  Auth: Required
  Response (200): { token, user }
  Errors: 401
```

### 6.2 Services (CRUD)

```yaml
GET /api/v1/services
  Query params:
    - page: int (default 1)
    - limit: int (default 20, max 100)
    - published: bool (optional filter)
    - sort: string (default 'order_index', options: name, order_index, created_at)
  Response (200): 
    - data: ServiceDTO[]
    - pagination: { total, page, pages, limit }
  Auth: Public

GET /api/v1/services/{id}
  Response (200): ServiceDTO
  Errors: 404 (not found)
  Auth: Public

POST /api/v1/admin/services
  Auth: Required (Admin only)
  Request:
    - name: string (required, max 150)
    - slug: string (optional, unique)
    - excerpt: string (optional)
    - body: string (optional)
    - icon_class: string (optional)
    - order_index: int (optional, default 0)
    - published: bool (default true)
  Response (201): ServiceDTO (with id, timestamps)
  Errors: 400 (validation), 401, 403

PUT /api/v1/admin/services/{id}
  Auth: Required (Admin)
  Request: (same as POST, all optional)
  Response (200): ServiceDTO
  Errors: 404, 400, 401, 403

DELETE /api/v1/admin/services/{id}
  Auth: Required (Admin)
  Response (204): No content
  Errors: 404, 401, 403
```

### 6.3 Posts (CRUD)

```yaml
GET /api/v1/posts
  Query params:
    - page: int (default 1)
    - limit: int (default 20)
    - status: string (filter: draft, published, scheduled)
    - author_id: int (optional)
    - category_id: int (optional)
    - sort: string (default 'published_at' desc)
  Response (200):
    - data: PostDTO[] (with nested author, categories, tags)
    - pagination: { total, page, pages }
  Auth: Public (published seulement) ou Admin (tous)

GET /api/v1/posts/{id}
  Response (200): PostDTO
  Auth: Public (if published) / Admin (any)

POST /api/v1/admin/posts
  Auth: Required (Admin/Editor)
  Request:
    - title: string (required, max 255)
    - slug: string (optional, auto-generate if omitted)
    - excerpt: string (optional)
    - body: string (required)
    - featured_media_id: int (optional)
    - author_id: int (default: current user)
    - status: enum (draft, published, scheduled)
    - published_at: datetime (if scheduled)
    - category_ids: int[] (optional)
    - tag_ids: int[] (optional)
  Response (201): PostDTO
  Errors: 400 (validation), 409 (slug duplicate)

PUT /api/v1/admin/posts/{id}
  Auth: Required (Admin or post owner)
  Request: (same as POST, all optional)
  Response (200): PostDTO

DELETE /api/v1/admin/posts/{id}
  Auth: Required (Admin or owner)
  Response (204)
```

### 6.4 Media (Upload & CRUD)

```yaml
GET /api/v1/media
  Query params:
    - page: int
    - limit: int
    - media_type: string (filter: logo, slide, general, etc.)
  Response (200):
    - data: MediaDTO[]
    - pagination: { total, page, pages }
  Auth: Admin or Public (depending on policy)

POST /api/v1/admin/media/upload
  Auth: Required (Admin/Editor)
  Content-Type: multipart/form-data
  Request:
    - file: file (required, image/* or application/pdf, max 5MB)
    - title: string (optional)
    - alt_text: string (optional)
    - media_type: string (optional, default 'general')
  Response (201):
    - id: int
    - path: string (relative path to uploaded file)
    - url: string (public URL)
    - mime_type: string
    - width, height: int (if image)
  Errors: 400 (invalid file), 413 (too large), 415 (unsupported media type)

DELETE /api/v1/admin/media/{id}
  Auth: Required (Admin)
  Response (204)
  Errors: 404, 422 (in-use warning)
```

### 6.5 Configuration sections

```yaml
GET /api/v1/config/company-profile
  Response (200): CompanyProfileDTO
  Auth: Public

PUT /api/v1/admin/config/company-profile
  Auth: Required (Admin only)
  Request: Partial update (company_name, email, phone, etc.)
  Response (200): CompanyProfileDTO

GET /api/v1/config/sections
  Response (200): 
    - sections: { hero, feature, about, contact, testimonial, counter, ... }
  Auth: Public

PUT /api/v1/admin/config/sections/{section_key}
  Auth: Admin
  Request: Section-specific payload
  Response (200): Section DTO
```

---

## 7. Frontend (React + TypeScript) — Contours

### 7.1 Architecture

```
frontend/
├── src/
│   ├── pages/
│   │   ├── Dashboard.tsx       // Main dashboard layout
│   │   ├── Login.tsx           // Login form
│   │   ├── Services/
│   │   │   ├── List.tsx        // Services list + pagination
│   │   │   ├── Create.tsx      // Service form (create)
│   │   │   ├── Edit.tsx        // Service form (edit)
│   │   │   └── Detail.tsx      // Service detail view
│   │   ├── Posts/
│   │   │   ├── List.tsx
│   │   │   ├── Create.tsx
│   │   │   ├── Edit.tsx
│   │   │   └── Detail.tsx
│   │   └── Media/
│   │       └── Library.tsx     // Media upload + list
│   ├── components/
│   │   ├── Layout/
│   │   │   ├── Sidebar.tsx
│   │   │   ├── Header.tsx
│   │   │   └── Layout.tsx
│   │   ├── Common/
│   │   │   ├── Button.tsx
│   │   │   ├── Form.tsx
│   │   │   ├── Modal.tsx
│   │   │   └── Loader.tsx
│   │   └── Forms/
│   │       ├── ServiceForm.tsx
│   │       └── PostForm.tsx
│   ├── hooks/
│   │   ├── useAuth.ts          // Auth context + state
│   │   ├── useApi.ts           // API fetch wrapper
│   │   └── useForm.ts          // Form state management
│   ├── api/
│   │   ├── client.ts           // Axios/fetch client + interceptors
│   │   ├── authApi.ts          // /auth endpoints
│   │   ├── servicesApi.ts      // /services endpoints
│   │   ├── postsApi.ts         // /posts endpoints
│   │   └── mediaApi.ts         // /media endpoints
│   ├── context/
│   │   ├── AuthContext.tsx
│   │   └── NotificationContext.tsx
│   ├── types/
│   │   ├── api.ts              // DTOs (ServiceDTO, PostDTO, etc.)
│   │   ├── auth.ts
│   │   └── index.ts
│   ├── utils/
│   │   ├── validators.ts
│   │   ├── formatters.ts
│   │   └── errors.ts
│   ├── App.tsx
│   └── main.tsx
├── public/
├── vite.config.ts
├── tsconfig.json
├── eslint.config.js
└── package.json
```

### 7.2 Key pages & flows

#### Page: Login
- Form avec email/password.
- POST /api/v1/auth/login → store token (localStorage ou httpOnly cookie).
- Redirect to /dashboard on success.
- Error handling + display.

#### Page: Services (List)
- GET /api/v1/services (paginated).
- Display table/list avec columns: name, badge, order, published, actions.
- Actions: Edit, Delete, Preview.
- Buttons: "+ Add Service" → redirects to Create.

#### Page: Services (Create/Edit)
- Form avec fields: name, slug, excerpt, body, icon_class, order_index, published.
- RichText editor pour body (optionnel: SimpleMDE, React-Quill).
- POST /api/v1/admin/services (create) ou PUT /api/v1/admin/services/{id} (edit).
- Success toast + redirect to list.

#### Page: Posts (List/CRUD)
- Similar to Services.
- Additional fields: author, status (draft/published/scheduled), categories, tags.
- Multi-select for categories/tags.

#### Page: Media (Library)
- Upload zone (drag-drop).
- Grid/table view avec thumbnails.
- Delete capability.
- Copy URL to clipboard action.

---

## 8. Dépendances & librairies

### Backend PHP
| Lib | Purpose | Version |
|-----|---------|---------|
| Slim Framework | API framework | ^4.0 |
| PSR-7 (psr/http-message) | HTTP messages | ^2.0 |
| Firebase/JWT | JWT tokenization | ^6.0 |
| Monolog | Logging | ^3.0 |
| PHPUnit | Testing | ^10.0 |
| PHPStan | Static analysis | ^1.10 |
| PHPCS | Linting | ^3.7 |

### Frontend (React + TS)
| Lib | Purpose | Version |
|-----|---------|---------|
| React | UI framework | ^18.0 |
| TypeScript | Type safety | ^5.0 |
| React Router | Routing | ^6.0 |
| Axios | HTTP client | ^1.0 |
| Zustand ou Context | State management | ^4.0 (Zustand) |
| React Hook Form | Form management | ^7.0 |
| Zod | Schema validation | ^3.0 |
| Tailwind CSS | Styling | ^3.0 |
| ESLint + Prettier | Linting/formatting | Latest |
| Vitest | Unit testing | ^1.0 |
| React Testing Library | Component testing | ^14.0 |
| Playwright ou Cypress | E2E testing | ^1.0 |

---

## 9. Sécurité & Compliance

### 9.1 Mesures de sécurité

| Aspect | Mesure |
|--------|--------|
| **Auth** | JWT Bearer token, expiration 24h, refresh tokens |
| **Password** | Hash via bcrypt (min 12 rounds) |
| **CORS** | Whitelist domaines frontend |
| **CSRF** | N/A (JWT stateless, pas de cookies session) |
| **Rate limiting** | Rate limiter middleware (ex: 100 req/min par IP) |
| **Validation** | Input validation + sanitization côté server |
| **SQL Injection** | Prepared statements (PDO) |
| **XSS** | HTML escaping on response (JSON safe) |
| **Secrets** | .env (never committed), secret manager en prod (AWS Secrets Manager, etc.) |
| **HTTPS** | Enforced en production |
| **Versioning** | API versioned (/api/v1) pour éviter breaking changes |

### 9.2 Audit & Logging

- Log toutes les opérations CRUD (qui, quand, quoi).
- Store in audit_log table (optionnel pour Phase C, Phase D).
- Centralized logs avec Monolog → file/external service.

---

## 10. Tests & QA

### 10.1 Tests backend

| Type | Coverage | Framework |
|------|----------|-----------|
| **Unit** | 70%+ (services, entities) | PHPUnit |
| **Integration** | CRUD endpoints | PHPUnit + in-memory DB |
| **Contract** | OpenAPI spec matches impl | Dredd (optionnel) |

### 10.2 Tests frontend

| Type | Coverage | Framework |
|------|----------|-----------|
| **Unit** | 70%+ (hooks, utils) | Vitest |
| **Component** | Forms, inputs, flows | React Testing Library |
| **E2E** | Critical user flows (login, CRUD) | Playwright/Cypress |

### 10.3 Endpoints à tester (MVP)

```php
// Auth
- POST /api/v1/auth/login (valid, invalid credentials)
- GET /api/v1/auth/me (with token, without)
- POST /api/v1/auth/logout

// Services CRUD
- GET /api/v1/services (paginated, filtrage published)
- POST /api/v1/admin/services (create, validation errors)
- PUT /api/v1/admin/services/{id} (update, 404)
- DELETE /api/v1/admin/services/{id}

// Posts CRUD
- GET /api/v1/posts (pagination, status filter)
- POST /api/v1/admin/posts (create, category/tag association)
- PUT /api/v1/admin/posts/{id}
- DELETE /api/v1/admin/posts/{id}

// Media
- POST /api/v1/admin/media/upload (valid file, oversized, invalid type)
- GET /api/v1/media (pagination)
- DELETE /api/v1/admin/media/{id}
```

---

## 11. Livrables & Acceptance Criteria (Phase C)

### 11.1 Livrables

- ✅ OpenAPI spec (YAML) + Swagger UI.
- ✅ Backend API (Slim 4) avec controllers, services, repositories.
- ✅ Frontend (React + TS) avec pages CRUD + auth.
- ✅ Migrations DB (users table, audit logs optionnel).
- ✅ Test suite (70%+ coverage, E2E).
- ✅ Documentation (API docs, setup guide, runbook).
- ✅ Docker Compose (stack complet: PHP-FPM, Nginx, MySQL, Redis optionnel).
- ✅ CI/CD pipeline (GitHub Actions: lint, test, build, deploy staging).

### 11.2 Acceptance Criteria

- [ ] All endpoints return correct status codes & response schemas.
- [ ] Authentication & RBAC enforced (admin-only endpoints blocked for non-admin).
- [ ] CRUD operations work end-to-end (frontend → backend → DB).
- [ ] Validation errors returned with 400 + clear messages.
- [ ] Tests pass (unit, integration, E2E).
- [ ] Static analysis clean (PHPStan lvl 7, ESLint 0 errors).
- [ ] API docs available at `/api/docs` (Swagger UI).
- [ ] Frontend dashboard accessible & responsive (mobile-friendly).
- [ ] Auth flow (login → token → protected routes) works.
- [ ] CI green (all jobs passing on merge).

---

## 12. Roadmap: Order d'exécution Phase C

1. **Semaine 1–2** : API design + auth backend (task #2, #3).
2. **Semaine 2–3** : Backend architecture + CRUD endpoints (task #4, #5, #6).
3. **Semaine 3–4** : Frontend setup + auth UI (task #8, #9).
4. **Semaine 4–5** : Frontend CRUD pages (task #10).
5. **Semaine 5–6** : E2E tests + CI (task #11, #12).
6. **Semaine 6+** : Observabilité + hardening (task #13, #14).

---

## 13. Annexes

### A. Matrice des dépendances (tâches Phase C)

```
#1 (Cahier des charges) — Blocking task
  ├─ #2 (OpenAPI spec) ← #1
  │   ├─ #4 (Backend skeleton) ← #2, #3
  │   │   ├─ #5 (DB + Models) ← #1, #4
  │   │   │   └─ #6 (Endpoints CRUD) ← #4, #5, #3
  │   │   └─ #7 (Doc Swagger) ← #2, #6
  │   └─ #8 (Frontend setup) ← #2, #3
  │       ├─ #9 (Auth frontend) ← #3, #8
  │       └─ #10 (CRUD pages) ← #6, #8, #9
  │
  ├─ #3 (Auth & RBAC) ← #1, #2
  └─ #11 (E2E tests) ← #6, #10
  └─ #12 (CI/CD) ← #6, #8, #11
  └─ #13 (Observabilité) ← #6, #12
  └─ #14 (Audit + livraison) ← #7, #11, #12, #13
```

### B. Configuration `.env.example` (Phase C)

```env
# Database
DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=scaffolddb
DB_USER=root
DB_PASS=password
DB_CHARSET=utf8mb4

# API
API_BASE_URL=http://localhost:8000
API_PORT=8000
API_ENV=development

# JWT
JWT_SECRET=your-very-secret-key-here
JWT_EXPIRY=86400  # 24 hours in seconds
JWT_REFRESH_EXPIRY=604800  # 7 days

# Frontend
FRONTEND_URL=http://localhost:3000

# Mail (optionnel)
MAIL_FROM=noreply@app.local
MAIL_DRIVER=log

# Debug
APP_DEBUG=true
LOG_LEVEL=debug
```

---

**Document généré** : 12 novembre 2025  
**Statut** : READY FOR IMPLEMENTATION  
**Phase** : C (API & Dashboard, 3–6 semaines)
