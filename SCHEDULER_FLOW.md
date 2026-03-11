# Scheduler Flow

This document covers the full booking lifecycle for Aktiv, including the receipt-based payment confirmation process, owner walk-in bookings, auto-cancel rules, and UX details for the Scheduler tab.

---

## Hub Operating Hours

Hub operating hours are stored per day of the week in the `hub_operating_hours` table.

```
hub_operating_hours
  id              UUID        PK
  hub_id          UUID        FK → hubs.id
  day_of_week     INTEGER     (0 = Sunday, 1 = Monday, … 6 = Saturday)
  opens_at        TIME        (e.g. 06:00)
  closes_at       TIME        (e.g. 22:00)
  is_closed       BOOLEAN     DEFAULT false   (entire day closed, e.g. public holidays / rest day)
```

### Rules & Notes

- A hub should have up to 7 rows — one per day. Missing days are treated as closed.
- `is_closed = true` overrides `opens_at`/`closes_at` — the hub is closed that entire day regardless.
- The scheduler UI should grey out time slots outside operating hours and prevent bookings from being placed during closed hours.
- Owner walk-in bookings bypass the operating hours check (e.g. the owner needs to log an after-hours session manually).
- The **"Open now"** indicator on hub cards on the Explore page is derived from the current day's row: `is_closed = false` AND current time is between `opens_at` and `closes_at`.
- If a hub has no rows in `hub_operating_hours`, the "Open now" indicator is hidden and the scheduler shows a notice that hours are not set.

### Example

| day_of_week | opens_at | closes_at | is_closed |
| ----------- | -------- | --------- | --------- |
| 0 (Sun)     | 07:00    | 20:00     | false     |
| 1 (Mon)     | 06:00    | 22:00     | false     |
| 2 (Tue)     | 06:00    | 22:00     | false     |
| 3 (Wed)     | 06:00    | 22:00     | false     |
| 4 (Thu)     | 06:00    | 22:00     | false     |
| 5 (Fri)     | 06:00    | 23:00     | false     |
| 6 (Sat)     | 07:00    | 23:00     | false     |

---

## Booking Flow Overview (Self-Booked)

```
Guest browses scheduler
        │
        ▼
  Not logged in? ──► Redirect to login/register
        │
        ▼ (authenticated)
  Select court → sport → date, start time & duration → session type
        │
        ▼
  Booking created  [status: pending_payment]
  Slot immediately blocked on scheduler
  expires_at set to NOW() + 1 hour
        │
        ├── expires_at passes with no receipt uploaded?
        │         ▼
        │   Auto-cancelled  [status: cancelled]
        │   Slot released
        │
        ▼
  User pays offline (GCash, bank transfer, etc.)
  User uploads receipt image
        │
        ▼
  [status: payment_sent]
  Slot remains locked — owner action required
  Hub owner is notified via email
        │
        ├── Owner rejects (with reason)
        │         ▼
        │   [status: pending_payment]  ← user can re-upload
        │   expires_at reset to NOW() + 1 hour
        │   User notified with rejection note
        │
        ├── start_time passes while still payment_sent?
        │         ▼
        │   Auto-cancelled  [status: cancelled]
        │   Owner and customer both notified
        │
        └── Owner confirms
                  ▼
            [status: confirmed]
            User notified — booking is locked
```

---

## Booking Status Lifecycle

| Status            | Description                                                                                           |
| ----------------- | ----------------------------------------------------------------------------------------------------- |
| `pending_payment` | Booking created, slot held. Waiting for user to upload payment receipt. Expires after 1 hour.         |
| `payment_sent`    | Receipt uploaded. Slot locked. Waiting for hub owner to confirm payment. Owner action required.       |
| `confirmed`       | Owner confirmed payment. Slot is officially booked.                                                   |
| `cancelled`       | Booking cancelled — by user, owner, auto-cancel (expired hold), or no owner action before start time. |
| `completed`       | Session has taken place.                                                                              |

> `rejected` is not a terminal status — when an owner rejects a receipt, the booking returns to `pending_payment` with a note, and the user can re-upload. This keeps the status list clean and avoids a dead-end state.

---

## Auto-Cancel Rules

A scheduled Laravel queue job (`CancelExpiredBookings`) runs every few minutes and handles two cancellation cases:

**Case 1 — No receipt uploaded (expired hold)**

Cancels bookings where:

- `status = pending_payment`
- `expires_at < NOW()`

**Case 2 — Owner never acted before session start time**

Cancels bookings where:

- `status = payment_sent`
- `start_time < NOW()`

When cancelled in either case:

- `status` is set to `cancelled`
- The slot is released and becomes available on the scheduler
- The user (and owner, for Case 2) receives an email notification

---

## Re-Upload After Rejection

When an owner rejects a receipt:

- `status` returns to `pending_payment`
- `payment_note` is populated with the owner's rejection reason
- `expires_at` is reset to `NOW() + 1 hour` — the full window restarts from the rejection time
- User is notified by email with the rejection reason
- The slot remains held — the user has 1 hour to re-upload before the booking is auto-cancelled

---

## Schema — `bookings` Table (Full)

> All `TIMESTAMP` fields are stored in UTC. The frontend always converts to UTC+8 (Asia/Manila) for display.

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
  created_by            UUID        FK → users.id        (owner's user ID when booking_source = owner_added)
  guest_name            VARCHAR     (nullable — anonymous walk-ins only)
  guest_phone           VARCHAR     (nullable — anonymous walk-ins only; basic format validation at API boundary)
  total_price           DECIMAL     (recorded for future payment integration)
  receipt_image_url     VARCHAR     (nullable — public URL of uploaded receipt)
  receipt_uploaded_at   TIMESTAMP   (nullable)
  payment_note          TEXT        (nullable — owner's rejection reason or internal note)
  payment_confirmed_by  UUID        FK → users.id        (nullable — hub owner who confirmed)
  payment_confirmed_at  TIMESTAMP   (nullable)
  expires_at            TIMESTAMP   (nullable — set to created_at + 1h; reset on rejection; null for owner_added)
  cancelled_by          ENUM        (nullable — user, owner, system)
  created_at            TIMESTAMP
```

### Status Rules by `booking_source`

| Rule                       | `self_booked`     | `owner_added` |
| -------------------------- | ----------------- | ------------- |
| Initial `status`           | `pending_payment` | `confirmed`   |
| Receipt upload required    | Yes               | No            |
| `expires_at` set           | Yes               | No            |
| 1-hour auto-cancel applies | Yes               | No            |
| Slot blocked immediately   | Yes               | Yes           |

---

## Owner Walk-in Bookings

Hub owners can create bookings directly from the dashboard for customers who are present on-site.

### Two cases:

**1. Registered user walk-in**

- Owner searches by name, email, or phone
- Selects the matching user account
- `booked_by` = customer's `user_id`, `created_by` = owner's `user_id`

**2. Anonymous walk-in**

- Owner enters `guest_name` + `guest_phone` (no account required)
- `booked_by` = NULL, `guest_name` + `guest_phone` populated
- `created_by` = owner's `user_id`

### Walk-in booking behavior:

- `booking_source = owner_added`
- `status = confirmed` immediately — payment is collected on-site
- No receipt required, no `expires_at`, no auto-cancel
- Slot is blocked on the scheduler instantly

### Dashboard UX — "Add Walk-in" form:

1. Search for registered user (optional)
2. If no match: enter guest name + phone number
3. Select court, sport, date, start time, duration & session type
4. Confirm → booking created as `confirmed`, slot blocked

---

## Scheduler UX — Contact Notice

A **non-dismissible** info alert is displayed above the calendar on the Scheduler tab at all times:

```
ℹ️  We recommend contacting the venue directly to confirm availability
    before booking, as the schedule may not always reflect real-time updates.

    📞 [contact numbers from hub_contact_numbers]
```

### Implementation notes:

- Uses `UAlert` (Nuxt UI) with `color="primary"` and `variant="soft"`
- Not dismissible — this is a safety notice, not a banner
- Contact numbers are pulled from the `hub_contact_numbers` relationship, which supports multiple entries with a `type` field (e.g. phone, mobile, viber)
- If the hub has no contact numbers on file, the notice still renders but omits the contact line
- Shown on both day and week views, above the calendar timeline
- Not shown inside the booking modal

---

## Email Notifications (via Resend / Mailpit in dev)

| Trigger                                  | Recipient        | Subject                                        |
| ---------------------------------------- | ---------------- | ---------------------------------------------- |
| Booking created                          | Customer         | "Your booking is pending payment"              |
| Receipt uploaded                         | Hub owner        | "New receipt to review — [Hub Name]"           |
| Booking confirmed by owner               | Customer         | "Your booking is confirmed!"                   |
| Owner rejects receipt                    | Customer         | "Receipt rejected — action required"           |
| Booking auto-cancelled (expired hold)    | Customer         | "Your booking has been cancelled"              |
| Booking auto-cancelled (no owner action) | Customer + Owner | "Booking cancelled — no payment confirmation"  |
| Owner cancels a booking                  | Customer         | "Your booking has been cancelled"              |
| User cancels a booking                   | Hub owner        | "A booking has been cancelled by the customer" |
