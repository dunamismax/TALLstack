<p align="center">
  <img src="public/favicon.svg" alt="TALLstack Admin logo" width="84" />
</p>

<p align="center">
  A production-ready Laravel + Livewire admin dashboard template with RBAC, API endpoints, and modern UI primitives.
</p>

# TALLstack Admin Template

TALLstack is a reusable admin dashboard starter for Laravel teams that want server-driven interactivity without a heavy frontend SPA. It ships with authentication, role and permission management, Livewire-powered admin pages, and a versioned admin API so you can move from prototype to production faster.

## Trust Signals

![Runtime](https://img.shields.io/badge/PHP-8.2%2B-777BB4)
![Framework](https://img.shields.io/badge/Laravel-12.x-FF2D20)
![Frontend](https://img.shields.io/badge/Livewire-4.x-4E56A6)
![Tests](https://img.shields.io/badge/Tested_with-Pest_4-2E7D32)
![License](https://img.shields.io/badge/License-MIT-blue)

## Quick Start

### Prerequisites

- PHP `8.2+` (project and CI are running on PHP `8.4`/`8.5`)
- Composer `2+`
- Node.js `22+` and npm
- SQLite (default local database)

### Run

```bash
git clone <your-repo-url> TALLstack
cd TALLstack
cp .env.example .env
touch database/database.sqlite
composer setup
php artisan migrate --seed --no-interaction
composer dev
```

Expected result:

- App is available at `http://localhost:8000`
- Login page is available at `/login`
- Admin dashboard is available at `/dashboard` after authentication

Optional local seed reset:

```bash
php artisan migrate:fresh --seed --no-interaction
```

Default seeded account:

- Email: `test@example.com`
- Password: `password`
- Role: `super-admin`

## Features

- Fortify-backed authentication with registration, login, password reset, email verification, and 2FA settings.
- Role-based access control with `roles`, `permissions`, policy checks, and permission middleware.
- Livewire + Flux admin pages for dashboard analytics, user CRUD, and role/permission management.
- Versioned admin API under `/api/v1/admin` with request validation, resources, and authorization.
- Session-authenticated API access using Laravel `web` + `auth:web` + `verified` middleware.
- API route throttling via `throttle:admin-api` with per-user and per-IP limits.
- Consistent JSON API error responses (auth, validation, and exceptions), including clients that do not send JSON accept headers.
- CI workflows for testing and linting via Pest and Pint.

## Tech Stack

| Layer | Technology | Purpose |
|---|---|---|
| Backend | [Laravel 12](https://laravel.com/docs/12.x) | Core framework, routing, auth, authorization, validation, queues |
| Auth | [Laravel Fortify](https://laravel.com/docs/12.x/fortify) | Headless auth endpoints and security features |
| UI Engine | [Livewire 4](https://livewire.laravel.com/docs/4.x) | Reactive server-driven components and full-page routes |
| UI Components | [Flux UI 2](https://fluxui.dev/) | Accessible, reusable UI primitives for forms, tables, modals |
| Styling | [Tailwind CSS 4](https://tailwindcss.com/) | Utility-first styling and theme tokens |
| Data | SQLite (default) / Laravel Eloquent ORM | Relational data with model relationships and policies |
| Testing | [Pest 4](https://pestphp.com/) + PHPUnit 12 | Feature coverage for auth, admin pages, and API behavior |
| Tooling | Vite 7, Laravel Pint, Laravel Pail | Asset pipeline, formatting, and log tailing |

## Project Structure

```sh
TALLstack/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/V1/Admin/    # Admin API controllers
│   │   ├── Middleware/                   # Permission middleware alias target
│   │   ├── Requests/Api/                 # API request base + admin request validation
│   │   └── Resources/Admin/              # API resource transformers
│   ├── Models/                           # User, Role, Permission models + relationships
│   ├── Policies/                         # User and Role authorization policies
│   └── Providers/                        # Gate definitions and app boot configuration
├── database/
│   ├── migrations/                       # Core auth + RBAC schema
│   ├── factories/                        # User/Role/Permission factories
│   └── seeders/                          # Idempotent default data + RBAC seeding
├── resources/views/
│   ├── layouts/                          # App shell, sidebar, header
│   └── pages/
│       ├── admin/                        # Livewire admin pages (dashboard/users/roles)
│       ├── auth/                         # Fortify auth pages
│       └── settings/                     # Profile/password/2FA/appearance pages
├── routes/
│   ├── web.php                           # Web routes and Livewire admin pages
│   ├── api.php                           # Versioned admin API routes
│   └── settings.php                      # User settings routes
├── tests/Feature/                        # Auth, dashboard, admin, and API feature tests
└── .github/workflows/                    # CI test and lint pipelines
```

## Development Workflow and Common Commands

### Setup

```bash
cp .env.example .env
touch database/database.sqlite
composer setup
php artisan migrate --seed --no-interaction
```

### Run

```bash
composer dev
```

### Test

```bash
php artisan test --compact
php artisan test --compact tests/Feature/Admin/AdminPagesTest.php
php artisan test --compact tests/Feature/Api/AdminApiTest.php
```

### Lint and Format

```bash
composer lint
vendor/bin/pint --dirty --format agent
```

### Build

```bash
npm run build
```

### Deploy (Generic Flow)

```bash
npm ci
composer install --no-dev --optimize-autoloader
php artisan migrate --force --no-interaction
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Command verification notes for this documentation pass:

- Verified in this environment: `vendor/bin/pint --dirty --format agent`, `php artisan test --compact tests/Feature/Api/AdminApiTest.php`, `php artisan test --compact`.
- Not executed in this pass: `composer setup`, `composer dev`, `npm run dev`, full deploy command block.

## Deployment and Operations

This repository does not ship provider-specific deployment manifests. Use your platform of choice and apply the same Laravel release flow consistently.

- Build and migrate during deployment: run `npm run build` and `php artisan migrate --force` before switching traffic.
- Health check endpoint: `GET /up` (configured in `bootstrap/app.php`).
- Logs: use `php artisan pail` for local/hosted log tailing.
- Queues: `php artisan queue:work` (or your supervisor process) for async jobs.
- Rollback baseline: `php artisan migrate:rollback --step=1` and redeploy previous artifact.

## Security and Reliability Notes

- Authentication is handled by Fortify and Laravel session auth (`auth:web` middleware on admin API routes).
- Admin web routes require both `auth` and `verified` middleware.
- Admin APIs are session-authenticated and require `verified`, `throttle:admin-api`, and permission middleware.
- API exceptions are forced to JSON for all `/api/*` requests via `bootstrap/app.php`.
- Authorization uses policies and gate checks backed by persisted role/permission relationships.
- Input validation is enforced with dedicated Form Request classes for admin API writes (`ApiRequest` base ensures JSON validation responses).
- Passwords are stored with Laravel's hashed cast on `User::$casts`.
- CSRF protection and secure session middleware are applied through Laravel's web stack.
- Reliability guardrails include Pest feature tests and CI workflows in `.github/workflows/tests.yml` and `.github/workflows/lint.yml`.

## Documentation

| Path | Purpose |
|---|---|
| [AGENTS.md](AGENTS.md) | Workspace and project-specific coding/agent guidelines |
| [routes/web.php](routes/web.php) | Authenticated web routes and Livewire admin pages |
| [routes/api.php](routes/api.php) | Versioned admin API route definitions |
| [bootstrap/app.php](bootstrap/app.php) | Middleware aliases and API exception JSON rendering strategy |
| [app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php) | Permission gate registration, super-admin bypass, and API route rate limiters |
| [app/Http/Requests/Api/ApiRequest.php](app/Http/Requests/Api/ApiRequest.php) | Shared API Form Request behavior for consistent JSON validation responses |
| [app/Models/User.php](app/Models/User.php) | User auth model and RBAC helper methods |
| [database/seeders/AccessControlSeeder.php](database/seeders/AccessControlSeeder.php) | Default roles, permissions, and role assignment seeding |
| [tests/Feature/Admin/AdminPagesTest.php](tests/Feature/Admin/AdminPagesTest.php) | Admin page authorization coverage |
| [tests/Feature/Api/AdminApiTest.php](tests/Feature/Api/AdminApiTest.php) | Admin API authorization and CRUD coverage |
| [.github/workflows/tests.yml](.github/workflows/tests.yml) | CI test workflow |
| [.github/workflows/lint.yml](.github/workflows/lint.yml) | CI lint workflow |

## Contributing

1. Create a branch from `main`.
2. Implement your change with focused scope.
3. Run `php artisan test --compact` and `composer lint`.
4. Open a pull request with a clear summary and testing notes.

## License

Licensed under the [MIT License](LICENSE).
