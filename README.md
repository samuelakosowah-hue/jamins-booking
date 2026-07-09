# Jamin's Nutrition Consult — Appointment Booking

A self-contained appointment booking site for **Jamin's Nutrition Consult**, built around
the published *One Month Service Price List*. Clients pick services, choose a day and a
time window, and get a printable reference. Nothing is charged online.

## Run it locally

```bash
/Applications/XAMPP/xamppfiles/bin/php -S localhost:8123 \
  -t event-booking/public event-booking/public/index.php
```

Then open <http://localhost:8123>.

No Composer, no npm, no build step. Data lives in a SQLite file that is created
automatically on first request (`data/bookings.sqlite`).

## Hosting it online

See **[DEPLOY.md](DEPLOY.md)** for the full step-by-step (cPanel / shared hosting and
Nginx). In short: point the web root at `public/`, put your secrets in
`config.local.php`, make `data/` writable, and turn on HTTPS. Secrets never live in the
committed code, and a missing `config.local.php` fails safe (SMS off, login disabled)
rather than exposing anything.

## Pages

| Route | What it does |
|---|---|
| `/` | Landing page + the full service price list, with star ratings |
| `/book` | 4-step form (details → services → date & time → notes) |
| `/availability?date=…` | JSON: how many places remain in each window that day |
| `/confirmed?ref=…` | Printable ticket with the reference and cost estimate |
| `/lookup` | Client finds their booking by reference |
| `/review?ref=…` | Client rates a completed appointment (1–5 stars + comment) |
| `/admin` | Staff dashboard — confirm, mark seen, reschedule, cancel, moderate reviews |
| `/admin/services` | Add services, edit prices, reorder, retire, delete |
| `/admin/messages` | SMS outbox — every message sent or logged, with retry |
| `/admin/export` | Downloads all bookings as CSV |

## Admin access

Default password: **`jamins2026`**

Change it before you put this anywhere public:

```bash
EVENT_ADMIN_PASSWORD='something-better' /Applications/XAMPP/xamppfiles/bin/php -S localhost:8123 ...
```

## Configuration

[`config.php`](config.php) holds:

- `company` — name, taglines, blurb, **location and phone numbers** (shown in the contact bar).
- `slots` — appointment windows, and how many clients fit in each one **per day**.
- `booking_horizon` — how many days ahead clients may book (default 60).
- `service_icons` — the icons staff may pick from; each must exist in `views/_icons.php`.
- `services` — **seed data only**, see below.

### Services live in the database

Staff manage the price list at **`/admin/services`**: add a service, change its prices,
reorder it, retire it, delete it. The `services` array in `config.php` is written to the
database once, when the `services` table is empty. **Editing that array later changes
nothing on an existing database** — edit through the admin screen instead.

A service's `slug` is generated from its name on creation and never changes afterwards,
because bookings reference it.

- **Retire** takes a service off the price list and the booking form, while past bookings
  that chose it keep showing it. Retired services cannot be booked, enforced server-side.
- **Delete** is only offered once no booking references the service.

### Prices are bands, not figures

The price list quotes ranges (`250–400`), so the site never quotes a single number. Booking
shows an **estimate** — the minimums summed and the maximums summed — and says plainly that
the exact fee is agreed at the first consultation. Estimates are always recalculated from
the service table when a booking is saved, so a tampered form cannot change what is recorded.

**Each booking freezes the prices it was quoted** in `bookings.services_snapshot`. Raising a
price tomorrow does not rewrite a client's ticket from last week. Bookings taken before
snapshots existed fall back to today's prices for their line items; their stored totals were
always correct.

### Capacity is per day

`slots` capacity applies to each time window **on each date**. Filling 8–9am tomorrow leaves
8–9am the day after untouched. The booking form re-fetches `/availability` whenever the
client changes the date; the server re-checks capacity again on submit regardless.

Time windows are seeded **only when the database is first created**. To change them after
bookings exist, edit the `slots` table directly.

## SMS notifications

Clients are texted their booking ID, date, time and services the moment they book, and
again whenever staff **confirm**, **reschedule** or **cancel**. The practice gets an alert
SMS for every new booking. An appointment lands as **pending** and becomes **confirmed**
when staff press Confirm — which is the moment the confirmation text goes out.

### Turning it on

Out of the box the SMS **driver is `log`**: every message is written to the outbox at
`/admin/messages` and *nothing is delivered*. The site is fully usable this way, and you can
read the exact wording clients would receive. To send for real via mNotify:

```bash
SMS_DRIVER=mnotify \
MNOTIFY_API_KEY=your-api-key \
SMS_SENDER_ID=JaminsNutr \
SMS_ADMIN_PHONE=0249601468 \
/Applications/XAMPP/xamppfiles/bin/php -S localhost:8123 -t event-booking/public event-booking/public/index.php
```

The sender ID must be approved by mNotify (max 11 characters). All SMS settings live under
`sms` in [`config.php`](config.php).

### How it behaves

- Every message is recorded in the `messages` table **before** the network call, so the
  outbox is a complete audit trail regardless of delivery.
- A gateway failure never reaches the client — the booking already succeeded, and the
  failure shows in the outbox with a **Retry** button.
- Ghanaian numbers are normalised to `233XXXXXXXXX` before sending; `0244…`, `+233244…`,
  `024 4…` and `00233244…` all resolve to the same recipient.
- Re-clicking Confirm does not re-text the client — notifications fire only on an actual
  status change. Failed reschedule attempts send nothing.

## Ratings

A client rates their **appointment**, not each service individually — one review per booking,
1–5 stars plus an optional comment. That rating then counts once toward every service on the
booking, which is how each price-list row earns a star average.

Who may review:

- Only someone holding the booking reference (`/review?ref=JNC-…`).
- Only after the appointment happened — staff marked them **Seen**, or the date has passed.
- Cancelled appointments can never be reviewed.
- One review per booking, enforced by a `UNIQUE` constraint on `booking_id`.

Eligibility is re-checked on `POST`, **before** the CSRF check, so hiding the form is not the
security boundary. Comments are escaped on output and may be hidden from the public site by
staff (`Hide from website`); hidden reviews drop out of the averages and the testimonials
immediately. Public comments are attributed as "first name from town" — never a phone number.

With no reviews, the star UI does not render at all; there is no "0.0 (0)" on a fresh site.

## Notes

- Capacity is re-checked inside a transaction at insert time, so two people racing for
  the last appointment cannot both get it.
- Cancelling frees the appointment; restoring one is refused if that day's window has
  since filled up.
- Forms are CSRF-protected; admin routes require a session.
- Sessions are stored in `data/sessions` with a 4-hour lifetime. PHP's defaults put them
  in XAMPP's shared temp directory with a 24-minute lifetime, where any other XAMPP app's
  garbage collection could delete them mid-booking — which surfaced as "session expired".
- If a token does go stale, `/book` re-renders the form with every answer preserved and a
  fresh token, rather than dead-ending on an error page.
- `migrate()` in `src/db.php` adds missing columns on boot, so an existing
  `bookings.sqlite` picks up schema changes without being deleted. It must run **before**
  any `CREATE INDEX` naming a column it adds.
- Booking references are prefixed `JNC-`.
- The brand mark is inline SVG in [`views/_logos.php`](views/_logos.php) — there are no
  image files anywhere in the project, so it works fully offline.
- The ticket and the admin list both have print stylesheets (`Print / save as PDF`).

## Layout

```
config.php          branding, contact, services + price bands, slots, admin password
public/index.php    front controller + router
public/assets/      stylesheet
src/db.php          schema, migration, seeding, queries, review aggregation
src/sms.php         phone normalisation, mNotify driver, message templates
src/helpers.php     escaping, CSRF, session, validation, money, stars, view rendering
views/              layout + one file per page
views/admin_services.php  add / edit / reorder / retire / delete services
views/admin_messages.php  SMS outbox with delivery status and retry
views/review*.php   rating form + its "too early" / "already done" / "no such booking" states
views/_icons.php    inline SVG icon set (one per service) + star_svg()
views/_logos.php    inline SVG Jamin's brand mark
data/               SQLite database + session files (created on first run)
```
