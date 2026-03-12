# Aktiv — Sports Hub Scheduler

> **Primary Color:** `#004e89` · **Background:** `#f9fdf2` · **Style:** Flat, minimal, no gradients, use Nuxt UI components as much as possible

---

## Overview

Aktiv is a sports hub discovery and scheduling platform. Users can explore local sports hubs, book courts, join open play sessions, compete in tournaments, and track their rankings — all from one place.

---

## Coding Agent UI Guidelines

Use these rules for all frontend implementation tasks:

- Always use Nuxt UI components first. Build custom components only when Nuxt UI does not cover the use case.
- Do not use hardcoded colors in components, pages, or composables.
- Use Nuxt theme colors/tokens or CSS variables/classes defined in `frontend/app/assets/css/main.css`.
- Keep the visual style flat and minimal. Avoid gradients, glassmorphism, heavy shadows, and overly decorative effects.
- Before creating a new component, check existing files in `frontend/app/components/` (and related feature folders) to reuse or extend what already exists.

---

## Core Features

### 1. Explore Page

Browse and discover sports hubs in your area.

- Search by sport, location, availability, or price
- Filter by court type (tennis, badminton, basketball, etc.)
- **Hub Cards display:**
  - Hub name + cover photo
  - City / neighborhood
  - Courts available (count + types)
  - Lowest price per hour
  - Rating + review count
  - Open now indicator

---

### 2. Hub Profile

Each hub has a dedicated profile page with four main tabs:

#### 📅 Scheduler

The core booking interface.

- Two-panel custom resource grid (no third-party calendar library)
  - **Left:** Mini month calendar — click a day to load that day's availability
  - **Right:** Resource grid — courts as columns, 1-hour time slots as rows (vertically scrollable). Auto-scrolls to current time.
- Time slots are color-coded: Available (green) · Selected (blue) · Pending (amber) · Reserved (red) · Past/Closed (grey)
- **Multi-slot selection:** click any number of available slots across courts and days, then review and confirm in one action
- All scheduler bookings are **private** (exclusive court access). Open Play is a separate flow on the Open Play tab.
- A persistent info notice above the grid advises users to contact the venue to confirm availability, with the hub's contact number(s) displayed inline
- Booking Summary card below the grid shows per-court/day breakdown, sport selection, grand total, and a single **Book Now** button

> 📄 See [SCHEDULER_FLOW.md](SCHEDULER_FLOW.md) for the full booking flow, payment confirmation process, owner walk-in bookings, and schema details.

#### 🏃 Open Play

> **Implementation:** Open Play is a **separate flow from the Scheduler**. The Scheduler only creates private court bookings. Open Play sessions are created via a dedicated "Host a Session" form on this tab and use `session_type = open_play` on the same `bookings` table.

- Lists all upcoming Open Play sessions at this hub
- Each card shows: sport, court, date/time, host, spots filled / total capacity, price per player
- Users can join with one tap (charged per-player, not per-court)
- Host can set max players and a per-player price
- Joined players receive reminders and can see who else joined

#### 🏆 Tournaments

- Hub admins can register a tournament
- Tournament card shows: sport, format (single/double elim, round robin), date range, registration deadline, entry fee, prize info
- Once started, a live **match bracket** is displayed
  - Bracket updates in real time as scores are submitted
  - Players submit scores; hub admins confirm
- Leaderboard within the tournament shows standings (for round robin)

#### 🥇 Leaderboard

- Per-hub leaderboard ranked by:
  - Wins at this hub
  - Tournaments won
  - Hours played
  - Win rate
- Filter by sport type
- Shows player avatar, name, rank badge, key stats
- Global leaderboard tab aggregates stats across all hubs

---

## Database Schema

### Users

```
users
  id              UUID        PK
  name            VARCHAR
  email           VARCHAR     UNIQUE
  avatar_url      VARCHAR
  phone           VARCHAR
  created_at      TIMESTAMP
```

### Hubs

```
hubs
  id              UUID        PK
  name            VARCHAR
  description     TEXT
  city            VARCHAR
  address         VARCHAR
  lat             DECIMAL
  lng             DECIMAL
  cover_image_url VARCHAR
  owner_id        UUID        FK → users.id
  is_approved     BOOLEAN     DEFAULT true   (auto-approved for now; manual review in future)
  is_verified     BOOLEAN     DEFAULT false  (Verified badge — granted by admin in future)
  created_at      TIMESTAMP

hub_sports
  id              UUID        PK
  hub_id          UUID        FK → hubs.id
  sport           VARCHAR     (tennis, badminton, basketball, etc.)

hub_operating_hours
  id              UUID        PK
  hub_id          UUID        FK → hubs.id
  day_of_week     INTEGER     (0 = Sunday … 6 = Saturday)
  opens_at        TIME        (stored in venue local time, UTC+8 — e.g. 06:00)
  closes_at       TIME        (stored in venue local time, UTC+8 — e.g. 22:00)
  is_closed       BOOLEAN     DEFAULT false   (entire day closed, e.g. public holidays)

hub_contact_numbers
  id              UUID        PK
  hub_id          UUID        FK → hubs.id
  type            VARCHAR     (mobile, landline)
  number          VARCHAR

hub_websites
  id              UUID        PK
  hub_id          UUID        FK → hubs.id
  url             VARCHAR(2048)
```

### Courts

Courts support multiple sports — a single court can be booked for any sport it supports.

```
courts
  id              UUID        PK
  hub_id          UUID        FK → hubs.id
  name            VARCHAR     (e.g. "Court A", "Court 3")
  surface         VARCHAR     (hardcourt, clay, synthetic, etc.)
  indoor          BOOLEAN
  price_per_hour  DECIMAL
  max_players     INTEGER
  is_active       BOOLEAN

court_sports
  id              UUID        PK
  court_id        UUID        FK → courts.id
  sport           VARCHAR     (tennis, badminton, basketball, etc.)
```

### Bookings (Scheduler)

```
bookings
  id                    UUID        PK
  court_id              UUID        FK → courts.id
  booked_by             UUID        FK → users.id        (nullable for anonymous walk-ins)
  sport                 VARCHAR     (sport selected for this booking)
  start_time            TIMESTAMP
  end_time              TIMESTAMP
  session_type          ENUM        (private, open_play)
  status                ENUM        (pending_payment, payment_sent, confirmed, cancelled, completed)
  booking_source        ENUM        (self_booked, owner_added)
  created_by            UUID        FK → users.id        (owner's user ID if owner_added)
  guest_name            VARCHAR     (nullable — anonymous walk-ins only)
  guest_phone           VARCHAR     (nullable — anonymous walk-ins only)
  total_price           DECIMAL     (recorded for future payment integration)
  receipt_image_url     VARCHAR     (nullable)
  receipt_uploaded_at   TIMESTAMP   (nullable)
  payment_note          TEXT        (nullable — rejection reason or internal note)
  payment_confirmed_by  UUID        FK → users.id        (nullable — hub owner who confirmed)
  payment_confirmed_at  TIMESTAMP   (nullable)
  expires_at            TIMESTAMP   (nullable — set to created_at + 1h; reset on rejection; null for owner_added)
  cancelled_by          ENUM        (nullable — user, owner, system)
  created_at            TIMESTAMP
```

> 📄 See [SCHEDULER_FLOW.md](SCHEDULER_FLOW.md) for the full booking lifecycle, receipt upload flow, owner walk-in process, and auto-cancel rules.

### Open Play

Open Play pricing is per player. The default price (₱150.00) is stored in a global `app_settings` table so it can be changed from the admin panel without a code deploy. Hub admins may override this per session in the future.

```
app_settings
  key             VARCHAR     PK   (e.g. "open_play_price_per_player")
  value           VARCHAR          (e.g. "150.00")
  description     VARCHAR
  updated_at      TIMESTAMP

open_play_sessions
  id              UUID        PK
  booking_id      UUID        FK → bookings.id   (the root booking)
  sport           VARCHAR     (sport selected for this session)
  max_players     INTEGER
  price_per_player DECIMAL    (copied from app_settings at time of creation)
  current_players INTEGER     (cached count, updated on join/leave)

open_play_participants
  id              UUID        PK
  session_id      UUID        FK → open_play_sessions.id
  user_id         UUID        FK → users.id
  joined_at       TIMESTAMP
  payment_status  ENUM        (pending, paid, refunded)   -- ⚠️ Payment integration: future
```

### Tournaments

```
tournaments
  id              UUID        PK
  hub_id          UUID        FK → hubs.id
  name            VARCHAR
  sport           VARCHAR
  format          ENUM        (single_elim, double_elim, round_robin)
  start_date      DATE
  end_date        DATE
  registration_deadline DATE
  entry_fee       DECIMAL
  max_teams       INTEGER
  status          ENUM        (upcoming, registration_open, ongoing, completed)
  created_by      UUID        FK → users.id

tournament_teams
  id              UUID        PK
  tournament_id   UUID        FK → tournaments.id
  name            VARCHAR
  captain_id      UUID        FK → users.id
  registered_at   TIMESTAMP

tournament_team_members
  id              UUID        PK
  team_id         UUID        FK → tournament_teams.id
  user_id         UUID        FK → users.id

tournament_matches
  id              UUID        PK
  tournament_id   UUID        FK → tournaments.id
  round           INTEGER
  match_number    INTEGER
  team_a_id       UUID        FK → tournament_teams.id
  team_b_id       UUID        FK → tournament_teams.id
  court_id        UUID        FK → courts.id
  scheduled_at    TIMESTAMP
  score_a         INTEGER
  score_b         INTEGER
  winner_team_id  UUID        FK → tournament_teams.id
  status          ENUM        (scheduled, in_progress, completed)
  confirmed_by    UUID        FK → users.id
```

### Leaderboards

```
player_stats
  id              UUID        PK
  user_id         UUID        FK → users.id
  hub_id          UUID        FK → hubs.id   (NULL = global)
  sport           VARCHAR
  total_hours     DECIMAL
  matches_played  INTEGER
  matches_won     INTEGER
  tournaments_won INTEGER
  updated_at      TIMESTAMP
```

### Reviews

```
hub_reviews
  id              UUID        PK
  hub_id          UUID        FK → hubs.id
  user_id         UUID        FK → users.id
  rating          INTEGER     (1–5)
  comment         TEXT
  created_at      TIMESTAMP
```

---

## Implementation Phases

### Phase 1 — Foundation & Explore (Weeks 1–4)

**Goal:** Users can discover hubs.

- [ ] Project setup: Nuxt 3 + Nuxt UI 4 (frontend), Laravel (backend API), PostgreSQL, Docker Compose
- [ ] Auth: Sign up / login via Laravel Sanctum (email + Google OAuth)
- [ ] Database: `users`, `hubs`, `courts`, `court_sports`, `hub_sports`, `hub_contact_numbers`, `hub_websites`, `app_settings` tables
- [ ] Hub listing: auto-approved on creation (`is_approved = true`), `is_verified = false` by default
- [ ] Explore page: hub cards with search + filters
- [ ] Map view using MapLibre GL JS + OpenFreeMap Bright tiles
- [ ] Hub profile shell (tabs: Scheduler, Open Play, Tournaments, Leaderboard)
- [ ] Hub owner dashboard: create and manage hub, courts, and court sports
- [ ] Design system: `#004e89` primary, `#f9fdf2` background, flat UI components

**Deliverables:** Browsable hub directory, hub detail page with map

---

### Phase 2 — Scheduler & Bookings (Weeks 5–8)

**Goal:** Users can book courts with manual payment confirmation via receipt upload.

- [ ] Scheduler UI: two-panel custom resource grid — mini month calendar (left) + courts × time slots grid (right)
- [ ] Info notice above grid: contact venue to confirm availability (shows hub contact number(s) and website(s))
- [ ] Booking flow: select one or more slots on the grid → review Booking Summary card → Book Now
- [ ] Multi-slot selection: users can pick slots across multiple courts and days in one session
- [ ] All scheduler bookings are `session_type = private`; no session type toggle in the UI
- [ ] Booking requires a logged-in account; guests are shown a Log in to Book button
- [ ] New booking starts as `pending_payment`; slot is immediately blocked on the scheduler
- [ ] User uploads GCash/bank transfer receipt image within 1 hour or booking is auto-cancelled
- [ ] Auto-cancel job: cancels `pending_payment` bookings older than 1 hour with no receipt uploaded
- [ ] On receipt upload, status moves to `pending_review`; hub owner is notified via email
- [ ] Hub owner dashboard: "Pending Confirmations" queue — review receipt, confirm or reject with a note
- [ ] On confirm → status becomes `confirmed`; user is notified
- [ ] On reject → status returns to `pending_payment`; user is notified with rejection reason and can re-upload
- [ ] Owner walk-in bookings: owner can add a booking directly (registered user or anonymous guest) — instantly `confirmed`, no receipt required
- [ ] Conflict detection (no double-booking)
- [ ] Booking management: view, cancel, reschedule
- [ ] Email notifications via Resend (booking created, receipt uploaded, confirmed, rejected, auto-cancelled)
- [ ] ⏳ Online payment gateway: deferred to future phase

**Deliverables:** Full court booking flow with receipt-based manual payment confirmation and owner walk-in support

---

### Phase 3 — Open Play (Weeks 9–11)

**Goal:** Users can create and join public sessions.

- [ ] "Host a Session" form on the Open Play tab: select court, sport, date + time, duration, max players, per-player price → creates a `session_type = open_play` booking
- [ ] Default per-player price (₱150.00) pulled from `app_settings`
- [ ] Open Play tab on hub profile: browse upcoming sessions, spots remaining
- [ ] Open Play slots appear as occupied (non-clickable) on the Scheduler grid for the relevant court + time
- [ ] Join flow (no online payment yet — pay on-site; payment_status tracked in DB)
- [ ] Player list visible to all participants in a session
- [ ] Notifications when session fills up or is cancelled (email via Resend)
- [ ] ⏳ Online payment collection for Open Play: deferred to future phase

**Deliverables:** Discoverable open play sessions, join flow

---

### Phase 4 — Tournaments (Weeks 12–16)

**Goal:** Hubs can run tournaments.

- [ ] Tournament creation by hub admins
- [ ] Team registration (entry fee recorded, collected on-site; ⏳ online payment deferred)
- [ ] Bracket generation (single elim, double elim, round robin)
- [ ] Match scheduling: assign courts & times
- [ ] Score submission by players; **hub admins confirm scores**
- [ ] Live bracket view (polling or server-sent events)
- [ ] Tournament history on hub profile

**Deliverables:** End-to-end tournament management with live brackets

---

### Phase 5 — Leaderboards & Stats (Weeks 17–19)

**Goal:** Player progression and rankings.

- [ ] `player_stats` table with automated updates via match/booking events
- [ ] Per-hub leaderboard (filter by sport)
- [ ] Global leaderboard
- [ ] Player profile: stats, match history, tournaments
- [ ] Rank badges (Bronze, Silver, Gold, etc.)

**Deliverables:** Functional leaderboards, player profiles

---

### Phase 6 — Polish & Launch (Weeks 20–22)

**Goal:** Production-ready website launch.

- [ ] Reviews & ratings for hubs
- [ ] Hub admin: mark hubs with Verified tag (is_verified flag)
- [ ] Hub analytics dashboard (bookings, court utilization, popular times)
- [ ] Performance optimization (caching, lazy load, image optimization)
- [ ] Accessibility audit (WCAG 2.1 AA)
- [ ] SEO (hub pages, explore page)
- [ ] Beta testing + feedback loop
- [ ] **Provision Hetzner VPS** — set up server, Docker Compose, SSL (Let's Encrypt), domain, env vars
- [ ] Deploy to Hetzner and smoke test before public launch

---

### Future Phases (Post-Launch)

- [ ] **Payment integration** — online booking payments + Open Play per-player collection
- [ ] **Admin panel** — manage `app_settings` (e.g. Open Play price), approve/verify hubs
- [ ] **PWA / Mobile app** — progressive web app first, then evaluate native
- [ ] **SMS notifications** — via free/low-cost gateway

---

## Tech Stack

| Layer           | Choice                                                                |
| --------------- | --------------------------------------------------------------------- |
| Frontend        | Nuxt 3 + Nuxt UI 4 + Tailwind CSS + Pinia                             |
| Backend         | Laravel (PHP)                                                         |
| Auth            | Laravel Sanctum (API token auth)                                      |
| Database        | PostgreSQL (local Docker) → PostgreSQL on Hetzner VPS pre-launch      |
| File Storage    | Local storage (dev) → VPS storage or Cloudflare R2 free tier (prod)   |
| Maps            | OpenFreeMap (Bright tiles) + MapLibre GL JS (free, no API key needed) |
| Email           | Resend free tier (3,000 emails/mo)                                    |
| Dev Environment | WSL Ubuntu + Docker Compose                                           |
| Hosting         | ⏳ Hetzner VPS — to be configured before Phase 6 launch               |
| Payments        | ⏳ To be added in a future phase                                      |
| Mobile          | ⏳ PWA / native app to be added in a future phase                     |

### Maps Setup

Map tiles are served by [OpenFreeMap](https://openfreemap.org) using the **Bright** style, rendered with **MapLibre GL JS**. No API key is required — tiles are free and open.

```js
// nuxt.config.ts — install maplibre-gl via npm
// npm install maplibre-gl

const map = new maplibregl.Map({
  container: 'map',
  style: 'https://tiles.openfreemap.org/styles/bright',
  center: [125.6, 7.07], // default: Davao City
  zoom: 12
});
```

---

## Docker Setup

### Containers

| Container  | Image                   | Purpose                                                            |
| ---------- | ----------------------- | ------------------------------------------------------------------ |
| `frontend` | `node:20-alpine`        | Nuxt 3 dev server                                                  |
| `backend`  | `php:8.3-fpm` + Laravel | Laravel REST API                                                   |
| `db`       | `postgres:16-alpine`    | Main PostgreSQL database                                           |
| `redis`    | `redis:alpine`          | Queue driver + API response caching                                |
| `nginx`    | `nginx:alpine`          | Reverse proxy — routes `/api/*` → Laravel, `/*` → Nuxt             |
| `mailpit`  | `axllent/mailpit`       | Local email catcher for dev (replaces Resend during local testing) |

### Port Map (Local)

| Service         | Local URL                                                        |
| --------------- | ---------------------------------------------------------------- |
| App (via Nginx) | `http://localhost:8080`                                          |
| Mailpit UI      | `http://localhost:8025`                                          |
| PostgreSQL      | `localhost:5433` (direct, for DB clients like TablePlus/DBeaver) |
| Redis           | `localhost:6379` (direct, for debugging)                         |

> **Note:** PostgreSQL is on host port `5433` (not `5432`) to avoid conflict with other projects running on the default port.

> **WSL note:** Use `localhost` from your Windows browser — WSL 2 automatically forwards ports to Windows. If ports don't resolve, run `wsl hostname -I` to get the WSL IP as a fallback.

### WSL-Specific Tips

- Store the project inside the WSL filesystem (`~/projects/aktiv`) **not** on the Windows mount (`/mnt/c/...`). File I/O on Windows mounts is significantly slower.
- Docker Desktop for Windows with the WSL 2 backend is the recommended setup. Alternatively, install Docker Engine directly inside WSL.
- `vendor/` and `node_modules/` live on your local WSL filesystem and are mounted into the containers. This means VS Code IntelliSense and autocomplete work natively — run `composer install` and `npm install` in WSL once to set them up locally.
- Mailpit replaces Resend in local dev — all outgoing emails are caught at `http://localhost:8025`. No emails actually send during local development.

### Common Commands

```bash
# Start all containers
docker compose up -d

# View logs
docker compose logs -f frontend
docker compose logs -f backend

# Laravel artisan
docker compose exec backend php artisan migrate
docker compose exec backend php artisan make:controller ExampleController

# Nuxt / npm (adding packages)
docker compose exec frontend npm install maplibre-gl
```

---

## Frontend Folder Structure

> This is the recommended structure for the `frontend/` directory. Follow this as a guide when creating new files.

```
frontend/
├── assets/
│   ├── css/
│   │   └── main.css              # Tailwind base + custom global styles
│   └── images/                   # Static images (logos, placeholders)
│
├── components/
│   ├── app/
│   │   ├── AppHeader.vue
│   │   ├── AppFooter.vue
│   │   └── AppSidebar.vue
│   ├── hub/
│   │   ├── HubCard.vue           # Used on Explore page
│   │   ├── HubMap.vue            # MapLibre map component
│   │   ├── HubBadge.vue          # Verified badge, sport tags
│   │   └── HubReviewCard.vue
│   ├── scheduler/
│   │   ├── SchedulerCalendar.vue
│   │   ├── SchedulerSlot.vue
│   │   └── BookingModal.vue
│   ├── open-play/
│   │   ├── OpenPlayCard.vue
│   │   └── OpenPlayJoinModal.vue
│   ├── tournament/
│   │   ├── TournamentCard.vue
│   │   ├── TournamentBracket.vue
│   │   └── TournamentRegisterModal.vue
│   ├── leaderboard/
│   │   └── LeaderboardRow.vue
│   └── ui/                       # Reusable primitives on top of Nuxt UI
│       ├── BaseAvatar.vue
│       ├── BaseBadge.vue
│       └── BaseEmptyState.vue
│
├── composables/
│   ├── useAuth.ts                # Login, logout, current user
│   ├── useHubs.ts                # Fetch/search hubs
│   ├── useBooking.ts             # Create/cancel bookings
│   ├── useOpenPlay.ts            # Sessions, join/leave
│   ├── useTournament.ts          # Tournament data + bracket
│   ├── useLeaderboard.ts         # Fetch leaderboard stats
│   └── useMap.ts                 # MapLibre GL JS setup + helpers
│
├── layouts/
│   ├── default.vue               # Main layout (header + footer)
│   ├── hub.vue                   # Hub profile layout (tabs)
│   └── auth.vue                  # Auth pages (no header/footer)
│
├── middleware/
│   ├── auth.ts                   # Redirect to login if not authenticated
│   └── hub-owner.ts              # Restrict hub dashboard to owners only
│
├── pages/
│   ├── index.vue                 # Landing / home
│   ├── explore.vue               # Explore hubs page
│   ├── auth/
│   │   ├── login.vue
│   │   └── register.vue
│   ├── hubs/
│   │   ├── [id]/
│   │   │   ├── index.vue         # Hub profile (redirects to scheduler tab)
│   │   │   ├── scheduler.vue
│   │   │   ├── open-play.vue
│   │   │   ├── tournaments.vue
│   │   │   └── leaderboard.vue
│   │   └── create.vue            # Create new hub (hub owners)
│   ├── dashboard/
│   │   ├── index.vue             # Hub owner dashboard
│   │   ├── bookings.vue
│   │   ├── courts.vue
│   │   └── tournaments.vue
│   └── profile/
│       └── index.vue             # User profile + stats
│
├── plugins/
│   └── maplibre.client.ts        # MapLibre GL JS (client-only, needs window)
│
├── stores/
│   ├── auth.ts                   # Current user, token, login state
│   ├── hub.ts                    # Active hub, hub list
│   ├── booking.ts                # Scheduler state, selected slot
│   ├── openPlay.ts               # Open play session state
│   └── tournament.ts             # Active tournament, bracket state
│
├── types/
│   ├── hub.ts                    # Hub, Court, CourtSport interfaces
│   ├── booking.ts                # Booking, SessionType interfaces
│   ├── open-play.ts              # OpenPlaySession, Participant interfaces
│   ├── tournament.ts             # Tournament, Team, Match interfaces
│   ├── user.ts                   # User, PlayerStats interfaces
│   └── api.ts                    # Generic API response wrapper types
│
├── utils/
│   ├── api.ts                    # $fetch wrapper with Sanctum token + base URL
│   ├── date.ts                   # Date formatting helpers (slots, schedules)
│   ├── price.ts                  # Format ₱ currency
│   └── bracket.ts                # Tournament bracket generation logic
│
├── app.vue
├── nuxt.config.ts
├── tailwind.config.ts
└── tsconfig.json
```

### Key Notes

**`utils/api.ts`** — configure `$fetch` here with your Laravel base URL and attach the Sanctum token on every request automatically. This keeps API calls clean and consistent across all composables.

**`plugins/maplibre.client.ts`** — the `.client` suffix tells Nuxt to only load MapLibre in the browser, never during SSR. Required because MapLibre depends on `window` and `document`.

**`composables/` vs `stores/`** — composables handle API calls and business logic; Pinia stores hold shared reactive state. For example, `useBooking.ts` makes the API call, but `stores/booking.ts` holds the currently selected time slot so the calendar and booking modal stay in sync without prop drilling.

**`components/ui/`** — put any custom wrapper components around Nuxt UI here (e.g. a `BaseBadge` that always uses your brand color). Keeps Nuxt UI overrides in one place.

---

## Decisions & Notes

| Topic                         | Decision                                                                                                                                                                                                           |
| ----------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Multi-sport courts            | ✅ A court supports multiple sports via `court_sports` table; user picks sport at booking time                                                                                                                     |
| Open Play pricing             | ✅ Per-player at ₱150.00 default; stored in `app_settings` for admin panel changes                                                                                                                                 |
| Tournament score confirmation | ✅ Hub admins confirm submitted scores                                                                                                                                                                             |
| Mobile app                    | ⏳ Website only for now; PWA/native planned post-launch                                                                                                                                                            |
| Hub listing approval          | ✅ Auto-approved (`is_approved = true`) for now; `is_verified` flag reserved for future Verified badge                                                                                                             |
| Booking payment               | ✅ Receipt upload flow — user pays offline (GCash/bank transfer), uploads receipt, owner confirms manually. Statuses: `pending_payment` → `payment_sent` → `confirmed`. See [SCHEDULER_FLOW.md](SCHEDULER_FLOW.md) |
| Online payment gateway        | ⏳ Deferred to future phase; all price fields stored in DB now to ease future integration                                                                                                                          |
| Booking auth requirement      | ✅ Account required to book; guests redirected to login/register                                                                                                                                                   |
| Slot holding / auto-cancel    | ✅ `pending_payment` bookings auto-cancelled after 1 hour if no receipt uploaded                                                                                                                                   |
| Owner walk-in bookings        | ✅ Owners can add bookings for on-site customers (registered user or anonymous guest); instantly confirmed                                                                                                         |
| Scheduler contact notice      | ✅ Non-dismissible info alert above calendar; shows hub contact numbers from `hub_contact_numbers`                                                                                                                 |
| Maps                          | ✅ OpenFreeMap (Bright tiles) + MapLibre GL JS — fully free, no API key needed                                                                                                                                     |
| Frontend framework            | ✅ Nuxt 3 + Nuxt UI 4 + Pinia (uses npm)                                                                                                                                                                           |
| Hosting                       | Local dev on WSL Ubuntu + Docker Compose; Hetzner VPS provisioned before Phase 6 launch                                                                                                                            |
