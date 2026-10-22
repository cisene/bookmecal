# 4. API & Endpoints

## REST API (`src/api.php`)
JSON over HTTP. Every response has `success`. Errors also have a stable `code` (snake_case, translated by the client) and an English `error` for logs and debugging. Responses are sent with `Cache-Control: no-store` and the API security headers (see [01-architecture-and-config.md](01-architecture-and-config.md) §1.5).

| Action | Method | Parameters | Response |
| :--- | :--- | :--- | :--- |
| `get_services` | GET | – | `data`: active services keyed by SKU |
| `check_availability` | GET | `service_id`, `date`, optional `country` | `date`, `slots`: free starts for that day |
| `check_week` | GET | `service_id`, `date` (any day in the week), optional `country` | week plan, see below |
| `book` | POST (`application/json`, max 8 KB) | `service_id`, `date`, `time`, `customer_name`, `customer_email`, `form_token`, `website` (honeypot, must be empty) | `booking_id` |

### Week plan (`check_week`)
```json
{
  "success": true,
  "week_start": "2026-10-19",
  "step": 5,
  "duration": 45,
  "cleanup": 15,
  "days": [
    {
      "date": "2026-10-22",
      "open": "08:00",
      "close": "17:00",
      "busy": [ { "start": "10:05", "end": "11:05", "kind": "booked" } ],
      "slots": [ { "start_time": "08:00", "end_time": "08:45", "start_iso": "…", "end_iso": "…" } ]
    }
  ]
}
```
- `days` always has 7 entries, Monday first. Closed days and holidays have `open`/`close` set to `null` and no slots.
- `busy.kind` is `booked` (a booking including its cleanup) or `past` (time already passed today, or the whole day for past dates). Busy intervals are clipped to opening hours and never contain customer data.

### Booking
`book` re-checks the requested start under the booking lock. If it is no longer free the API answers `409` with `slot_unavailable`. On success the booking is saved with status `pending`, its `end_time` and the visitor's language.

### Abuse protection
Checked in this order for every request:

1. **CORS:** No `Access-Control-*` headers unless the request's `Origin` is listed in `settings.json` `allowed_origins`. Preflight (`OPTIONS`) from other origins gets `403 origin_not_allowed`.
2. **Rate limits per client** (IPv4 address, or IPv6 /64 prefix; proxy headers are not trusted): read actions 120 requests per 5 minutes, `book` 10 attempts per hour (failed attempts count too). Over the limit: `429 rate_limited` with `Retry-After`.
3. **Housekeeping:** Pending bookings older than `pending_expiry_hours` are expired and bookings older than `booking_retention_days` are deleted before availability is computed.

For `book` additionally:

4. **JSON only:** Other content types get `415 unsupported_media_type`. A cross-site HTML form cannot send JSON without a CORS preflight, which is refused. Bodies over 8 KB get `413 payload_too_large`.
5. **Honeypot:** The page has an off-screen field `website`. If it is filled in, the API answers with a fake success and saves nothing.
6. **Form token:** The page embeds `formToken` (Unix time + HMAC-SHA256, key in `data/secrets.json`). Missing or forged: `400 invalid_request`; younger than 3 seconds: `400 too_fast`; older than 24 hours: `400 form_expired`.
7. **Pending cap:** At most 3 upcoming pending bookings per email address (checked under the booking lock): `429 too_many_pending`.

Rejections in steps 2 and 5–7 are written to the security log (`src/log/security.log`, see [01-architecture-and-config.md](01-architecture-and-config.md) §1.7), without names or email addresses. All fields are validated by the `Input` layer (§1.6 there); `check_availability` and `check_week` answer `400 invalid_date` for an impossible date.

### Error codes
`missing_fields`, `invalid_country`, `invalid_request`, `method_not_allowed`, `invalid_date`, `invalid_time`, `invalid_email`, `invalid_name`, `unknown_service`, `slot_unavailable` (409), `save_failed` (500), `invalid_action`, `origin_not_allowed` (403), `rate_limited` (429), `unsupported_media_type` (415), `payload_too_large` (413), `too_fast`, `form_expired`, `too_many_pending` (429).

## Booking page (`src/index.php`, `js/booking.js`)
1. The visitor picks a treatment; the page loads the week of the chosen date (today by default) with `check_week`.
2. The week view shows one column per day with time running downwards: green = bookable (from the first to the last free start of a run, until that treatment would end), striped = booked, grey = passed, hatched narrow column = closed. White open time is too short for the chosen treatment.
3. A block as long as the treatment marks the selected time. It is placed by tapping a green area (centred on the tap, snapped to the nearest free start), dragged (also across days), moved with the arrow keys (up/down: previous/next start, left/right: same time on another day), or stepped with the Earlier/Later buttons.
4. Previous/next buttons and a date field move between weeks; weeks before the current one are not reachable.
5. Name and email are entered and the request is sent with `book`, together with the page's form token and the (empty) honeypot field. A short notice above the button links to the privacy notice (`privacy.php`).

## Calendar feed (`src/calendar.php`)
Read-only iCalendar (RFC 5545) subscription for the business's own calendar app: `calendar.php?token=<64 hex>` (also `/.well-known/calendar?token=...`). Turned on, renewed and turned off on the admin Config page; the page shows the link and a `webcal://` button. The token is kept in `data/secrets.json` (`calendar_feed_token`); without one the feed is off.

- **Contents:** `pending` bookings as `STATUS:TENTATIVE` (summary starts with `? `), `approved` and `confirmed` as `STATUS:CONFIRMED`. Other statuses are left out, so rejected and expired requests disappear from the calendar. Bookings that ended more than 30 days ago (`CalendarFeed::PAST_DAYS`) are left out.
- **Events:** `UID` is `<booking id>@bookmecal`; start and end in UTC (no `VTIMEZONE` needed); end is the treatment end without cleanup (one hour if missing). `SUMMARY` is `<service> – <customer name>`, `DESCRIPTION` holds name, email and phone. Text is escaped and control characters dropped, so customer input cannot add properties; long lines are folded at 75 octets without splitting UTF-8 characters.
- **Protection:** Token compared in constant time; wrong or missing token: `404`, logged as `feed_token_invalid`. Rate limit 30 requests per 5 minutes per client (`429` with `Retry-After`). `Cache-Control: no-store`, API security headers. Runs the same housekeeping (expiry, retention) as the API.
- Calendar apps are asked to refresh every 15 minutes (`REFRESH-INTERVAL`, `X-PUBLISHED-TTL`); Google Calendar decides on its own and can take hours.

## Integrations
- Google Calendar: OAuth enrollment wizard in `admin/setup_google.php` (with `state` parameter). Calendar sync is not active yet.
- Email: `EmailNotifier` (PHP `mail()`, configured through `sendmail_path` / `SMTP` in php.ini), texts from the `email.*` language keys. Off unless `config.json` `smtp.enabled` is true (admin Config page, which also sets sender name and address, the alert address and `public_url`).

## Booking emails (`BookingEmails`)
| When | To | Language | Content |
| :--- | :--- | :--- | :--- |
| Request saved (`api.php` `book`) | Customer | Booking's language | Receipt (`email.received.*`): service, date and time, not confirmed yet |
| Request saved | `smtp.notification_email` (if set) | `config.json` `app.language` | Alert (`email.admin.newRequest.*`) with name, email, time and, with `public_url`, a link to `admin/pending.php` |
| Admin approves | Customer | Booking's language | Confirmation (`email.approved.*`) with `booking.ics` attached (one event, `METHOD:PUBLISH`, summary `<service> – <business>`, no contact details) |
| Admin rejects | Customer | Booking's language | Decline (`email.rejected.*`) |
| Day before (cron) | Customer | Booking's language | Reminder (`send_reminders.php`) |

Customer emails sign off with `business_name` and, when `public_url` is set, end with a link to the privacy notice (`email.privacyLink`). A mail failure never undoes a booking or a decision. After approve/reject the bookings page says whether the customer got an email, or that emails are off and the customer must be told some other way; the result is stored as `decision_emailed`. Placeholders are filled in one pass, so a customer name containing `{service}` stays as typed.
