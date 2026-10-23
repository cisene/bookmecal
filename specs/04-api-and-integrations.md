# 4. API & Endpoints

## REST API (`src/api.php`)
JSON over HTTP. Every response has `success`. Errors also have a stable `code` (snake_case, translated by the client) and an English `error` for logs and debugging. Responses are sent with `Cache-Control: no-store` and the API security headers (see [01-architecture-and-config.md](01-architecture-and-config.md) §1.5).

| Action | Method | Parameters | Response |
| :--- | :--- | :--- | :--- |
| `get_services` | GET | – | `data`: active, bookable services keyed by SKU (sets with their calculated `duration`), each with `employee_ids` (who offers it; for a set, who offers all its treatments); `staff`: bookable employees as `id => {name, title}` (empty when there is no staff) |
| `check_availability` | GET | `service_id`, `date`, optional `country`, `employee_id` | `date`, `slots`: free starts for that day |
| `check_week` | GET | `service_id`, `date` (any day in the week), optional `country`, `employee_id` | week plan, see below |
| `book` | POST (`application/json`, max 8 KB) | `service_id`, `date`, `time`, optional `employee_id`, `customer_name`, `customer_email`, `form_token`, `website` (honeypot, must be empty) | `booking_id` |

`employee_id` empty or missing means "first available" (anyone offering the treatment). An ID that is not a bookable employee offering the service gets `400 unknown_employee`.

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
- Busy intervals are times within opening hours when nobody (of the chosen employee, or of all employees offering the treatment) can take a customer. `busy.kind` is `past` (time already passed today, or the whole day for past dates), `booked` (someone works then but is booked, including cleanup) or `off` (nobody works then). They never contain customer data or say who is booked.

### Booking
`book` re-checks the requested start under the booking lock and picks the employee (`BookingService::assign()`: the chosen one, or the free employee with the fewest bookings that day). If nobody is free the API answers `409` with `slot_unavailable`. On success the booking is saved with status `pending`, its `end_time`, `employee_id`, `employee_requested` and the visitor's language.

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
`missing_fields`, `invalid_country`, `invalid_request`, `method_not_allowed`, `invalid_date`, `invalid_time`, `invalid_email`, `invalid_name`, `unknown_service`, `unknown_employee`, `slot_unavailable` (409), `save_failed` (500), `invalid_action`, `origin_not_allowed` (403), `rate_limited` (429), `unsupported_media_type` (415), `payload_too_large` (413), `too_fast`, `form_expired`, `too_many_pending` (429).

## Booking page (`src/index.php`, `js/booking.js`)
1. The visitor picks a treatment; the page loads the week of the chosen date (today by default) with `check_week`.
2. When two or more employees offer the treatment, a "Who would you like to see?" choice appears: "First available" (default) or one employee (name and job title). Changing it reloads the week for that employee. With one or no employee the choice stays hidden.
3. The week view shows one column per day with time running downwards: green = bookable (from the first to the last free start of a run, until that treatment would end), striped = booked, light hatching = not bookable (nobody works), grey = passed, hatched narrow column = closed. White open time is too short for the chosen treatment.
4. A block as long as the treatment marks the selected time. It is placed by tapping a green area (centred on the tap, snapped to the nearest free start), dragged (also across days), moved with the arrow keys (up/down: previous/next start, left/right: same time on another day), or stepped with the Earlier/Later buttons.
5. Previous/next buttons and a date field move between weeks; weeks before the current one are not reachable.
6. Name and email are entered and the request is sent with `book`, together with the employee choice, the page's form token and the (empty) honeypot field. A short notice above the button links to the privacy notice (`privacy.php`).

## Customer cancellation (`src/cancel.php`)
`cancel.php?id=<booking id>&token=<32 hex>&lang=<booking language>`, sent in the customer emails when `public_url` is set. The token is the first 32 hex characters of HMAC-SHA256(`cancel|<id>`) with `cancel_secret` from `data/secrets.json` (`CancelLink`), so nothing is stored per booking and older bookings get links too.

1. GET shows the booking (service, date, time) and a button. Only the POST cancels, so mail scanners and link previews cannot cancel.
2. Allowed for `pending`, `approved` and `confirmed` bookings that start more than `cancel_cutoff_hours` from now (default 24, admin Config page). Later: "contact us" with `privacy_contact` or the sender address. Checked again under the booking's file lock (`BookingRepository::cancelBooking()`).
3. The booking gets `status: cancelled`, `cancelled_at_iso`, `cancelled_by: customer`; the time is free again and the booking leaves the calendar feed. The business gets an alert email.
4. Unknown booking or wrong token: `404`, logged as `cancel_token_invalid`. Rate limit 30 requests per 5 minutes per client. `Cache-Control: no-store`, `X-Robots-Tag: noindex`. The language switch keeps `id` and `token` (layout `$keepQuery`).

## Calendar feed (`src/calendar.php`)
Read-only iCalendar (RFC 5545) subscription for the business's own calendar app: `calendar.php?token=<64 hex>` (also `/.well-known/calendar?token=...`). Turned on, renewed and turned off on the admin Config page; the page shows the link and a `webcal://` button. The token is kept in `data/secrets.json` (`calendar_feed_token`); without one the feed is off.

- **Contents:** `pending` bookings as `STATUS:TENTATIVE` (summary starts with `? `), `approved` and `confirmed` as `STATUS:CONFIRMED`. Other statuses are left out, so rejected and expired requests disappear from the calendar. Bookings that ended more than 30 days ago (`CalendarFeed::PAST_DAYS`) are left out.
- **Events:** `UID` is `<booking id>@bookmecal`; start and end in UTC (no `VTIMEZONE` needed); end is the treatment end without cleanup (one hour if missing). `SUMMARY` is `<service> – <customer name>`, followed by ` (<employee name>)` when the booking has an employee, `DESCRIPTION` holds name, email and phone. Text is escaped and control characters dropped, so customer input cannot add properties; long lines are folded at 75 octets without splitting UTF-8 characters.
- **Protection:** Token compared in constant time; wrong or missing token: `404`, logged as `feed_token_invalid`. Rate limit 30 requests per 5 minutes per client (`429` with `Retry-After`). `Cache-Control: no-store`, API security headers. Runs the same housekeeping (expiry, retention) as the API.
- Calendar apps are asked to refresh every 15 minutes (`REFRESH-INTERVAL`, `X-PUBLISHED-TTL`); Google Calendar decides on its own and can take hours.

## Integrations
- Google Calendar: OAuth enrollment wizard in `admin/setup_google.php` (with `state` parameter). Calendar sync is not active yet.
- Email: `EmailNotifier`, texts from the `email.*` language keys. Off unless `config.json` `smtp.enabled` is true (admin Config page, which also sets sender name and address, the alert address and `public_url`). With `smtp.host` set, mail goes through that SMTP server (`SmtpTransport`, below); otherwise through PHP `mail()` (php.ini `sendmail_path`). The Config page has a "Send test email" button that shows the server's answer when sending fails.
- `SmtpTransport` (no external library): RFC 5321 client with `STARTTLS` (port 587, TLS 1.2 or 1.3), implicit TLS (`ssl`, port 465) or no encryption (`none`, only sensible for a relay on the same machine). The server certificate and name are verified. Signs in with `AUTH PLAIN`, or `AUTH LOGIN` when only that is offered; a password is never sent unencrypted to another machine (refused both on the Config page and in the transport). Uses `BODY=8BITMIME` when offered, doubles leading dots, adds `To`, `Subject`, `Date` and `Message-ID`. Settings: `config.json` `smtp.host`, `port`, `encryption` (`starttls`, `ssl`, `none`), `username`; the password is kept in `data/secrets.json` as `smtp_password` (not tracked in git, mode 0640) and never shown again on the Config page.
- Header values are UTF-8 encoded words of at most 75 characters, folded onto continuation lines (`EmailNotifier::encodeHeader()`, RFC 2047).

## Booking emails (`BookingEmails`)
| When | To | Language | Content |
| :--- | :--- | :--- | :--- |
| Request saved (`api.php` `book`) | Customer | Booking's language | Receipt (`email.received.*`): service, date and time, not confirmed yet |
| Request saved | `smtp.notification_email` (if set) | `config.json` `app.language` | Alert (`email.admin.newRequest.*`) with name, email, time and, with `public_url`, a link to `admin/pending.php` |
| Admin approves | Customer | Booking's language | Confirmation (`email.approved.*`) with `booking.ics` attached (one event, `METHOD:PUBLISH`, summary `<service> – <business>`, no contact details) |
| Admin rejects | Customer | Booking's language | Decline (`email.rejected.*`) |
| Admin moves an approved booking to another employee and ticks "Tell the customer" | Customer | Booking's language | Change (`email.moved.*`): new employee, previous one, same time; `booking.ics` with the same `UID` and a higher `SEQUENCE` (the booking's `sequence`, raised on every move) so calendars update the event, and `contact.vcf` as on approval |
| Day before (cron) | Customer | Booking's language | Reminder (`send_reminders.php`) |
| Customer cancels online | `smtp.notification_email` (if set) | `config.json` `app.language` | Alert (`email.admin.cancelled.*`) |

Contact cards (`ContactCard`): with settings.json `vcard` set to `venue` or `staff`, the approval email also carries `contact.vcf` (vCard 3.0, `text/vcard`): the venue's card (name, phone, email, website or else `public_url`, address, `GEO` in WGS-84, plus code and coordinates in `NOTE`) and, with `staff`, the card of the employee taking the booking if that employee agreed (`shareContact`). The employee card maps every profile field with a vCard counterpart: `N` (family, given, middle name, title before and after), `FN`, `NICKNAME`, `ORG` (business; department), `TITLE`, `ROLE`, `TEL` (mobile as CELL, phone as WORK), `EMAIL`, `URL`, `PHOTO` (only with `showPhoto`), `X-SOCIALPROFILE` (network from the host), `CATEGORIES` (qualifications) and `NOTE` (presentation and languages, as vCard 3.0 has no `LANG`). Swish and organisation numbers are not sent. All text is escaped and folded like the calendar files. The approval's `booking.ics` gets `LOCATION` (street, postal code, city) and `GEO` when the venue has them.

Customer emails sign off with `business_name`. When `public_url` is set, the receipt, approval and reminder end with the cancellation link (`email.cancelLink`), and all customer emails with a link to the privacy notice (`email.privacyLink`). A mail failure never undoes a booking or a decision. After approve/reject the bookings page says whether the customer got an email, or that emails are off and the customer must be told some other way; the result is stored as `decision_emailed`. Placeholders are filled in one pass, so a customer name containing `{service}` stays as typed. When the booking has an employee, `{service}` reads "<service> with <employee>" (`email.serviceWithEmployee`, sv "hos"), in subjects and bodies alike.
