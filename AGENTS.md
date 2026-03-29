# AGENTS.md

This file provides guidance to Codex (Codex.ai/code) when working with code in this repository.

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

> **All `php artisan` and `composer` commands must be run inside the Docker container:**
>
> ```bash
> docker compose exec backend php artisan <command>
> docker compose exec backend composer <command>
> ```
>
> Never run these directly on the host — the backend process runs inside Docker and needs access to the database, Redis, and other services.

```bash
# Dev server + queue + logs + Vite
docker compose exec backend composer run dev

# Run all tests
docker compose exec backend php artisan test --compact

# Run specific test file or filter
docker compose exec backend php artisan test --compact --filter=BookingTest
docker compose exec backend php artisan test --compact tests/Feature/BookingTest.php

# Format PHP code after changes (required before finalizing)
docker compose exec backend vendor/bin/pint --dirty --format agent

# Database migrations
docker compose exec backend php artisan migrate
docker compose exec backend php artisan migrate:fresh --seed
```

> **Tests do NOT touch the dev database.** The test suite uses an isolated SQLite in-memory database (configured in `phpunit.xml`). Running `php artisan test` will never affect your PostgreSQL dev data. If your dev data disappears, it was caused by a manual `migrate:fresh` command, not by tests.

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

### Timezone Rules

All timestamps are stored as **UTC** in PostgreSQL `timestamp without timezone` columns (Laravel default). The app's local timezone is **Asia/Manila (UTC+8)**.

**Backend — date-range queries:** When filtering by a YYYY-MM-DD calendar date from the frontend, always convert through the Manila timezone AND call `.utc()` before passing to Eloquent:

```php
// CORRECT
Carbon::parse($request->date_from, 'Asia/Manila')->startOfDay()->utc()
Carbon::parse($request->date_to,   'Asia/Manila')->endOfDay()->utc()
now('Asia/Manila')->startOfDay()->utc()

// WRONG — naive string passed to PostgreSQL, compared against UTC-stored values
Carbon::parse($request->date_from, 'Asia/Manila')->startOfDay()
now()->startOfDay()
```

**Backend — relative-time comparisons:** Bare `now()` (UTC) is correct for `expires_at` storage and expiry checks — both sides of the comparison are UTC.

**Frontend → API date params:** Send `YYYY-MM-DD` strings built from local JS date methods (`.getFullYear()` etc.). This is correct — it represents the Manila calendar date the user is viewing. The backend's `Asia/Manila` Carbon parse will interpret it correctly.

**Frontend — displaying API timestamps:** API responses contain ISO strings with a UTC offset (e.g. `2026-03-20T22:00:00+00:00`). When passing these to `toLocaleString` / `toLocaleDateString` / `toLocaleTimeString`, always include `timeZone: 'Asia/Manila'` so display is correct regardless of the browser's system timezone:

```ts
// CORRECT
new Date(iso).toLocaleString('en-PH', { timeZone: 'Asia/Manila', ... })

// WRONG — depends on browser's system timezone
new Date(iso).toLocaleString('en-PH', { ... })
```

**Frontend — booking time construction:** `setHours(h, m, 0, 0)` then `.toISOString()` is correct — it sets the local Manila hour and converts to UTC ISO for the API.

---

### Key Backend Conventions

- **Hub ratings use a Bayesian average** (`C=5, prior=3.5`): `round((5 * 3.5 + avg * count) / (5 + count), 1)`. Never display a raw average — always apply this formula. Returns `null` when `count === 0`.
- **`select()` must come before `withAvg()`/`withCount()`** on Eloquent relationship queries — calling `select()` after these clears the aggregate subqueries and they return `null`.
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
- Any operation that makes an API call must always show a Nuxt UI toast on both success and error — no exceptions
- Check `frontend/app/components/` for existing components before creating new ones
- Primary color: `#004e89` · Background: `#ecf4fc`

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

| Service  | Purpose               | Host Port  |
| -------- | --------------------- | ---------- |
| nginx    | Reverse proxy         | 8080       |
| frontend | Nuxt dev server       | internal   |
| backend  | PHP-FPM               | internal   |
| db       | PostgreSQL 16         | 5433       |
| redis    | Cache + queue         | 6379       |
| mailpit  | Email catcher (dev)   | 8025       |
| minio    | S3-compatible storage | 9000, 9001 |

**Image uploads**: `ImageUploadService` compresses to 500KB max and stores on MinIO (dev) / Cloudflare R2 (prod).

---

## Artisan Quickref

```bash
# Create files the Laravel way (always prefix with docker compose exec backend)
docker compose exec backend php artisan make:model Court --no-interaction
docker compose exec backend php artisan make:controller Api/CourtController --no-interaction
docker compose exec backend php artisan make:test --pest CourtTest --no-interaction
docker compose exec backend php artisan make:request StoreCourt --no-interaction
docker compose exec backend php artisan make:job ProcessBookingExpiry --no-interaction

# Always pass --no-interaction to artisan make: commands
```
