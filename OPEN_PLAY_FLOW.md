# Open Play Flow

This document covers the full Open Play feature for Aktiv — how hub owners create open play sessions, how users (and guests) discover and join them, the participant payment lifecycle, and auto-cancel rules.

---

## Overview

An **Open Play session** is a court time slot reserved by a hub owner that is publicly joinable by individual players. Unlike a private booking (one party owns the entire court), an open play session has a configurable player cap and players join and pay independently.

The session occupies a confirmed court reservation (blocking the slot on the scheduler), while each participant goes through the same payment lifecycle as a regular booking.

---

## Data Model

### `open_play_sessions` table

Linked 1:1 to a `bookings` row that represents the court reservation.

```
open_play_sessions
  id                    UUID        PK
  booking_id            UUID        FK → bookings.id (session_type = open_play, status = confirmed, booking_source = owner_added)
  sport                 VARCHAR     sport for this session
  max_players           INTEGER     maximum number of participants allowed
  price_per_player      DECIMAL     cost per participant (0 = free)
  notes                 TEXT        nullable — e.g. "Bring your own racket"
  guests_can_join       BOOLEAN     DEFAULT false — whether unregistered guests may join
  status                ENUM        open | full | cancelled | completed
  created_at            TIMESTAMP
  updated_at            TIMESTAMP
```

**Status rules:**

| Status      | Meaning                                                                      |
| ----------- | ---------------------------------------------------------------------------- |
| `open`      | Session is accepting participants                                            |
| `full`      | `confirmed` participant count has reached `max_players` — no new joins       |
| `cancelled` | Owner cancelled the session — all participants notified                      |
| `completed` | Session end time has passed                                                  |

> `status` is derived and updated reactively: it becomes `full` when confirmed participant count reaches `max_players`, and `open` again if a confirmed participant leaves or is cancelled.

### `open_play_participants` table

One row per player join attempt. Tracks per-participant payment state.

```
open_play_participants
  id                    UUID        PK
  open_play_session_id  UUID        FK → open_play_sessions.id
  user_id               UUID        FK → users.id    (nullable for guests)
  guest_name            VARCHAR     nullable — guest only
  guest_phone           VARCHAR     nullable — guest only
  guest_email           VARCHAR     nullable — guest only
  guest_tracking_token  VARCHAR     nullable — for guest status lookup
  payment_method        ENUM        pay_on_site | digital_bank
  payment_status        ENUM        pending_payment | payment_sent | confirmed | cancelled
  receipt_image_url     VARCHAR     nullable
  receipt_uploaded_at   TIMESTAMP   nullable
  payment_note          TEXT        nullable — owner's rejection reason
  payment_confirmed_by  UUID        FK → users.id    (hub owner who confirmed)
  payment_confirmed_at  TIMESTAMP   nullable
  expires_at            TIMESTAMP   nullable — payment deadline (see Auto-Cancel Rules)
  cancelled_by          ENUM        nullable — user | owner | system
  joined_at             TIMESTAMP
  created_at            TIMESTAMP
  updated_at            TIMESTAMP
```

### `bookings` row for the session

When an owner creates an open play session, a `bookings` row is created with:

| Field            | Value                   |
| ---------------- | ----------------------- |
| `session_type`   | `open_play`             |
| `status`         | `confirmed`             |
| `booking_source` | `owner_added`           |
| `booked_by`      | NULL                    |
| `created_by`     | owner's `user_id`       |
| `total_price`    | `0` (owner covers cost) |
| `expires_at`     | NULL                    |

This reservation blocks the court slot immediately, preventing private bookings from overlapping.

---

## Owner Flow — Creating an Open Play Session

The owner creates a session from the **Dashboard Bookings page** via the **Add Booking** modal. A radio toggle switches between **Walk-in** and **Open Play** modes.

### Open Play form fields

| Field          | Input type     | Notes                                 |
| -------------- | -------------- | ------------------------------------- |
| Court          | Dropdown       | Same as walk-in                       |
| Date           | Date picker    | Same as walk-in                       |
| Start time     | Time selector  | Same as walk-in                       |
| End time       | Time selector  | Same as walk-in                       |
| Sport          | Selector       | If the court is multi-sport           |
| Max players    | Number input   | Minimum 2                             |
| Price/player   | Decimal input  | `0` = free session                    |
| Notes          | Textarea       | Optional — displayed on the join card |
| Guests can join | Toggle        | DEFAULT off — allows unauthenticated players to join via email verification |

### Slot conflict check

On submit, the API performs the same conflict check used for walk-ins:

- No existing `confirmed` or `pending_payment` booking overlaps the selected court + time range
- Operating hours are **not** enforced for owner-created sessions (same rule as walk-ins)

If the slot is occupied, return a validation error.

### Creation result

```
Owner fills Open Play form → submits
        │
        ▼
  Conflict check passes
        │
        ▼
  bookings row created  [session_type=open_play, status=confirmed, booking_source=owner_added]
  open_play_sessions row created  [status=open]
  Court slot blocked on scheduler
        │
        ▼
  Session appears on scheduler grid as an "Open Play" slot
  Session appears on the hub's Open Play tab
```

### Owner — managing sessions

From the bookings calendar/table view, open play sessions appear as a distinct item (different color from private bookings). The owner can:

- **View participants** — expand to see participant list with payment statuses
- **Confirm payment** — same confirm flow as regular booking receipts
- **Reject receipt** — same reject flow; participant can re-upload
- **Cancel session** — cancels the session and all active participants; notifies everyone

---

## Scheduler Grid — Open Play Slot State

Open play slots appear as a new **joinable** slot state in `SchedulerResourceGrid`. They are never rendered as "taken" (grey/red) — they are an invitation.

### Slot states (updated)

| State      | Appearance                                           | Interaction                             |
| ---------- | ---------------------------------------------------- | --------------------------------------- |
| Available  | Green · `₱X/hr`                                      | Click to select for private booking     |
| Selected   | Blue · ✓ + price                                     | Click to deselect                       |
| Pending    | Amber · "Pending"                                    | Not clickable                           |
| Reserved   | Red · "Reserved"                                     | Not clickable                           |
| Open Play  | Teal · sport name · player count · `₱X/pax`         | Click to open Join modal                |
| Past/Closed | Grey                                                | Not clickable                           |

- The cell is **not selectable** for private booking — the court is already reserved
- Clicking it opens the **Join Open Play modal** (not the booking modal)
- Player count displayed as `3 / 8` (confirmed + pending / max)

---

## Join Flow — Authenticated User

```
User clicks Open Play slot on scheduler grid (or Join button on Open Play tab)
        │
        ▼
  JoinOpenPlayModal opens — shows:
    Sport · Court · Date · Time
    Price per player (or "Free")
    Current players: X / Y
    Notes (if any)
    Payment method selector (if price > 0)
        │
        ▼
  User clicks "Join"
        │
        ▼
  open_play_participants row created  [payment_status = pending_payment]
  expires_at set (see Auto-Cancel Rules)
        │
        ├── price = 0 (free session)?
        │         ▼
        │   payment_status = confirmed immediately
        │   Slot count incremented
        │   Toast: "You've joined!"
        │
        └── price > 0
                  │
                  ├── pay_on_site?
                  │         ▼
                  │   Participant code shown (same as booking QR code)
                  │   User shows code at venue; owner scans to confirm
                  │
                  └── digital_bank?
                            ▼
                      Receipt upload modal (same as SchedulerReceiptUploadModal)
                            │
                            ▼
                      [payment_status = payment_sent]
                      Owner notified via email
                            │
                            ├── Owner rejects → payment_status = pending_payment
                            │   expires_at reset to NOW() + 1 hour
                            │   User notified with rejection note
                            │
                            └── Owner confirms → payment_status = confirmed
                                Player count updated
                                User notified
```

When confirmed participant count reaches `max_players`:
- `open_play_sessions.status` → `full`
- Slot on scheduler shows "Full" overlay — Join button disabled
- Waiting users see a "Session is full" message

---

## Join Flow — Guest (Unregistered User)

Only available when `open_play_sessions.guests_can_join = true`.

If `guests_can_join = false`, the join button/slot shows an auth wall instead.

```
Guest clicks Join
        │
        ▼
  Guest details form:
    Name (required)
    Email (required) → verification code sent (same as guest booking flow)
    Phone (optional)
        │
        ▼
  Email verification code confirmed
        │
        ▼
  Proceeds through same payment flow as authenticated user
  guest_tracking_token generated for status lookup
```

---

## Leave / Cancel Participant

### User cancels themselves

- Participant can cancel their own join from:
  - The Open Play tab on the hub page
  - Their "My Bookings" page
- `payment_status` → `cancelled`, `cancelled_by = user`
- If participant was `confirmed`, player count decrements and session may revert from `full` → `open`

### Owner cancels a participant

- From the session's participant list in the dashboard
- `payment_status` → `cancelled`, `cancelled_by = owner`
- Participant notified via email

### Owner cancels the entire session

- All non-cancelled participants are cancelled → `cancelled_by = system`
- `bookings` row `status` → `cancelled`
- `open_play_sessions.status` → `cancelled`
- Court slot released on scheduler
- All affected participants notified via email

---

## Participant Payment Status Lifecycle

Mirrors the booking payment lifecycle exactly.

| Status            | Description                                                                                        |
| ----------------- | -------------------------------------------------------------------------------------------------- |
| `pending_payment` | Joined, slot tentatively held. Waiting for receipt upload (digital_bank) or venue show-up (pay_on_site). |
| `payment_sent`    | Receipt uploaded. Waiting for hub owner to confirm.                                                |
| `confirmed`       | Payment confirmed. Player is officially in the session.                                            |
| `cancelled`       | Cancelled — by user, owner, or system (expired).                                                   |

---

## Auto-Cancel Rules

Participant slots expire using the same logic as regular bookings. The existing `CancelExpiredBookings` command is extended to also cancel expired participants.

### `expires_at` calculation (per participant)

| Payment method | `expires_at` value                               |
| -------------- | ------------------------------------------------ |
| `pay_on_site`  | `session start_time` exactly                     |
| `digital_bank` | `MIN(NOW() + 1 hour, session start_time)`        |

If the session starts in 20 minutes, a digital_bank participant has 20 minutes to upload — not a full hour.

### Case 1 — No receipt uploaded (expired hold)

Cancels participants where:
- `payment_status = pending_payment`
- `expires_at < NOW()`

### Case 2 — Owner never acted before session start

Cancels participants where:
- `payment_status = payment_sent`
- session `start_time < NOW()`

When a participant is auto-cancelled:
- `payment_status` → `cancelled`, `cancelled_by = system`
- Player count decrements (if they were previously confirmed)
- Session status recalculated (`full` → `open` if applicable)
- Participant notified via email

**Re-upload after rejection** follows the same rule as regular bookings: `expires_at` resets to `NOW() + 1 hour` (capped at `start_time`) and the participant can re-upload.

---

## Open Play Tab — Public Discovery

The **Open Play** tab on the hub's about page (`/hubs/[id]/open-play`) lists all upcoming open sessions for the hub.

### Session card layout

```
┌─────────────────────────────────────────────────────┐
│  🏸 Badminton Open Play                             │
│  Court 2 · Today, Apr 1 · 2:00 PM – 4:00 PM        │
│                                                     │
│  👥 3 / 8 players   ·   ₱150 / player              │
│                                                     │
│  "Bring your own racket"                            │
│                                                     │
│                              [  Join  ]             │
└─────────────────────────────────────────────────────┘
```

- **Joined** badge replaces the Join button if the current user is already a confirmed participant
- **Full** badge replaces the Join button if `status = full`
- Past sessions are hidden by default (optional toggle to show)
- Filter by sport (if hub has multiple)

---

## API Endpoints

### Public

```
GET    /api/hubs/{hub}/open-play                          List upcoming open play sessions for a hub
POST   /api/hubs/{hub}/open-play/{session}/join           Join a session (auth or guest email verification)
DELETE /api/hubs/{hub}/open-play/{session}/leave          Leave a session (auth required)
POST   /api/hubs/{hub}/open-play/{session}/receipt        Upload payment receipt (auth or guest token)
```

### Owner (dashboard)

```
POST   /api/dashboard/hubs/{hub}/open-play                Create open play session
DELETE /api/dashboard/hubs/{hub}/open-play/{session}      Cancel session
GET    /api/dashboard/hubs/{hub}/open-play/{session}/participants   List participants
POST   /api/dashboard/hubs/{hub}/open-play/{session}/participants/{participant}/confirm   Confirm payment
POST   /api/dashboard/hubs/{hub}/open-play/{session}/participants/{participant}/reject    Reject receipt
DELETE /api/dashboard/hubs/{hub}/open-play/{session}/participants/{participant}           Cancel participant
```

---

## Frontend Components

| Component                        | Description                                                    |
| -------------------------------- | -------------------------------------------------------------- |
| `BookingWalkInModal.vue`         | Extended with Walk-in / Open Play radio toggle                 |
| `OpenPlayJoinModal.vue`          | Join confirmation + payment method selection                   |
| `OpenPlaySessionCard.vue`        | Session card shown on the Open Play tab                        |
| `OpenPlayParticipantList.vue`    | Owner's participant list with payment status + confirm/reject  |
| `SchedulerResourceGrid.vue`      | Extended with `open_play` slot state                           |
| `open-play.vue` (page)           | Replaces Phase 3 placeholder — full session list + filters     |

---

## Frontend Composables

| Composable            | New methods                                                                           |
| --------------------- | ------------------------------------------------------------------------------------- |
| `useOpenPlay.ts`      | `fetchSessions`, `joinSession`, `leaveSession`, `uploadParticipantReceipt`            |
| `useOwnerOpenPlay.ts` | `createSession`, `cancelSession`, `fetchParticipants`, `confirmParticipant`, `rejectParticipant`, `cancelParticipant` |

---

## Email Notifications

| Trigger                                        | Recipient              | Subject                                              |
| ---------------------------------------------- | ---------------------- | ---------------------------------------------------- |
| User joins (free session)                      | Participant            | "You've joined [Sport] Open Play at [Hub]"           |
| User joins (paid — pending receipt)            | Participant            | "Complete your payment to confirm your spot"         |
| Receipt uploaded by participant                | Hub owner              | "New receipt to review — Open Play at [Hub]"         |
| Owner confirms participant payment             | Participant            | "Your spot is confirmed!"                            |
| Owner rejects participant receipt              | Participant            | "Receipt rejected — action required"                 |
| Participant auto-cancelled (expired hold)      | Participant            | "Your open play spot has been released"              |
| Participant auto-cancelled (no owner action)   | Participant + Owner    | "Open play spot cancelled — no payment confirmation" |
| Owner cancels entire session                   | All participants       | "[Hub] has cancelled the Open Play session"          |
| Session reaches full capacity                  | —                      | (no notification — UI reflects state)                |

---

## Implementation Phases

### Phase A — Backend Foundation
1. Migration: `open_play_sessions` + `open_play_participants`
2. Models + relationships (`OpenPlaySession`, `OpenPlayParticipant`)
3. `OwnerOpenPlayController` — create, cancel session, manage participants
4. `OpenPlayController` — list sessions, join, leave, upload receipt
5. Extend `CancelExpiredBookings` command for participants
6. Tests (Pest)

### Phase B — Owner Dashboard
7. Extend `BookingWalkInModal` with Walk-in / Open Play toggle
8. `useOwnerOpenPlay.ts` composable
9. `OpenPlayParticipantList.vue` component
10. Open play sessions visible in bookings calendar/table (distinct teal color)

### Phase C — Public Discovery & Join
11. Extend `SchedulerResourceGrid` with `open_play` slot state
12. `OpenPlayJoinModal.vue` component
13. `OpenPlaySessionCard.vue` component
14. Replace `open-play.vue` placeholder with full session list
15. `useOpenPlay.ts` composable

### Phase D — Notifications
16. Email notifications for all participant lifecycle events
17. Broadcast events for real-time slot count updates (`session_updated`, `participant_confirmed`, `session_full`)
