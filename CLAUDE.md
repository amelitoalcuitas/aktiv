# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

---

## Project Overview

**Aktiv** is a sports hub discovery and scheduling platform. Users explore local sports hubs, book courts, join open play sessions, compete in tournaments, and track rankings.

**Architecture**: Decoupled monorepo — `frontend/` (Nuxt 3) + `backend/` (Laravel 12 REST API) communicating over HTTP via Nginx reverse proxy.

---

## Development

### Running the Full Stack

```bash
# Start all Docker services (nginx, frontend, backend, db, redis, minio, mailpit)
docker compose up -d

# Access: http://localhost:8080
# Mailpit (email catcher): http://localhost:8025
# MinIO console: http://localhost:9001
```

### Backend (Laravel 12)

```bash
cd backend

# Dev server + queue + logs + Vite
composer run dev

# Run all tests
php artisan test --compact

# Run specific test file or filter
php artisan test --compact --filter=BookingTest
php artisan test --compact tests/Feature/BookingTest.php

# Format PHP code after changes (required before finalizing)
vendor/bin/pint --dirty --format agent

# Database migrations
php artisan migrate
php artisan migrate:fresh --seed
```

### Frontend (Nuxt 3)

```bash
cd frontend

# Dev server
npm run dev

# Production build
npm run build
```

---

## Architecture

### Request Flow

`browser → nginx:8080 → /api/* → Laravel (PHP-FPM:9000) | /* → Nuxt (Node:3000)`

### Authentication

Laravel Sanctum API tokens. Frontend stores the token in a cookie (`aktiv_token`, 30-day expiry) and attaches it as `Authorization: Bearer <token>` on all requests. The `useApi()` composable in `frontend/app/utils/api.ts` handles this automatically. Google OAuth is available via Socialite.

### Frontend State

- **Pinia stores** (`frontend/app/stores/`): `auth`, `hub`, `booking`, `openPlay`, `tournament`
- **Composables** (`frontend/app/composables/`): handle API calls and business logic (e.g., `useAuth`, `useHubs`, `useBooking`)
- Nuxt auto-imports components, composables, and stores — no explicit imports needed

### Backend Structure (Laravel 12)

- **Middleware/routing**: configured in `bootstrap/app.php`, NOT in `app/Http/Kernel.php` (removed in Laravel 11+)
- **Controllers**: `app/Http/Controllers/Api/` — grouped by domain (Auth, Hub, Court, Booking, Dashboard)
- **Form Requests**: Always use Form Request classes (`app/Http/Requests/`) for validation — never inline validation
- **API Resources**: Use Eloquent API Resources for all API responses
- **Models**: Casts defined via `casts()` method, not `$casts` property
- **env()**: Never call `env()` outside of config files; always use `config('...')`

### Key Backend Conventions

- Prefer `Model::query()` over `DB::` raw queries
- Always eager-load relationships to prevent N+1 queries
- Use queued jobs (`ShouldQueue`) for time-consuming operations
- Enum keys are TitleCase (e.g., `BookingStatus::PendingPayment`)
- PHP 8 constructor property promotion in `__construct()`
- Explicit return types on all methods

### Frontend UI Guidelines

- **Always use Nuxt UI components first** — build custom components only when Nuxt UI doesn't cover the use case
- No hardcoded colors — use Nuxt theme tokens or CSS variables from `frontend/app/assets/css/main.css`
- Style is flat and minimal: no gradients, glassmorphism, or heavy shadows
- Check `frontend/app/components/` for existing components before creating new ones
- Primary color: `#004e89` · Background: `#f9fdf2`

### Booking Flow Summary

1. User selects time slots on the scheduler grid
2. Booking created with `status = pending_payment`
3. User uploads receipt image (within 1 hour or auto-cancelled)
4. Hub owner confirms or rejects via dashboard
5. Walk-in bookings (owner-added) skip payment and are instantly `confirmed`

See `SCHEDULER_FLOW.md` for full booking flow details.

---

## Testing (Backend)

- Use **Pest v4** for all tests: `php artisan make:test --pest {Name}`
- Every backend change must have a corresponding test
- Use model factories; check for existing factory states before manually setting attributes
- Most tests should be **feature tests** (not unit)
- Do not delete tests without approval

---

## Infrastructure

| Service | Purpose | Host Port |
|---------|---------|-----------|
| nginx | Reverse proxy | 8080 |
| frontend | Nuxt dev server | internal |
| backend | PHP-FPM | internal |
| db | PostgreSQL 16 | 5433 |
| redis | Cache + queue | 6379 |
| mailpit | Email catcher (dev) | 8025 |
| minio | S3-compatible storage | 9000, 9001 |

**Image uploads**: `ImageUploadService` compresses to 500KB max and stores on MinIO (dev) / Cloudflare R2 (prod).

---

## Artisan Quickref

```bash
# Create files the Laravel way
php artisan make:model Court --no-interaction
php artisan make:controller Api/CourtController --no-interaction
php artisan make:test --pest CourtTest --no-interaction
php artisan make:request StoreCourt --no-interaction
php artisan make:job ProcessBookingExpiry --no-interaction

# Always pass --no-interaction to artisan make: commands
```
