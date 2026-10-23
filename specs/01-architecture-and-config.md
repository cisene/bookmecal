# 1. System Architecture & Configuration

## Overview
Book Me Calendar is a lightweight, object-oriented booking system built for PHP, specifically designed for hosting environments lacking traditional SQL databases by relying on secure, flat-file JSON storage.

The code is split into controllers (PHP that handles input and loads data), templates (HTML only) and classes (logic and storage). PHP files contain no user-facing language; all texts come from the language files (see §1.3).

## 1.1 Request flow
- `src/index.php`: Public booking page controller, renders `templates/public/booking.php`.
- `src/privacy.php`: Public privacy notice, renders `templates/public/privacy.php` (§1.8). Public pages share `templates/layout/public.php` (language switcher, footer link to the privacy notice, `js/public.js`).
- `src/api.php`: JSON API used by the booking page (see [04-api-and-integrations.md](04-api-and-integrations.md)).
- `src/cancel.php`: Customer cancellation page, opened from the link in the booking emails (see [04-api-and-integrations.md](04-api-and-integrations.md)).
- `src/calendar.php`: Calendar subscription feed (iCalendar) of bookings, opened with a secret token (see [04-api-and-integrations.md](04-api-and-integrations.md)). Also reachable as `/.well-known/calendar`.
- `src/admin/*.php`: Admin controllers. Each requires `admin/auth.php` (signed-in user required; built on `admin/session.php`), handles POST (CSRF-checked, Post/Redirect/Get) and renders a template in `templates/admin/` inside `templates/layout/admin.php`. `admin/login.php` and `admin/logout.php` handle sign-in (§1.4).
- `src/send_reminders.php`: CLI-only cron script for 24-hour reminder emails.
- `src/bootstrap.php`: Shared setup: loads `JsonStore`, `ResourceProvider`, `Localization`, `View`, `SecurityHeaders`, `Input`, sets the timezone and defines the helpers `e()` (HTML escaping), `request_string()` and `request_array()`.

## 1.2 Core Components
- `ResourceProvider.php`: Central access to data files (`dataPath()`), settings, services, opening hours, holidays and the configured timezone. All data paths go through `dataPath()` so per-customer data folders can be added later.
- `JsonStore.php`: Locked JSON read/write/update (`flock()`), see [02-storage-and-repository.md](02-storage-and-repository.md).
- `View.php`: Renders plain-PHP templates from `src/templates/` with an optional layout.
- `Localization.php`: Language selection and translation with the global `__t()` helper.
- `EmailNotifier.php`: Sends the emails (plain text, optional attachment, header-injection safe); nothing is sent unless `config.json` `smtp.enabled` is true. The transport is `SmtpTransport` when an SMTP server is set, otherwise PHP `mail()`; tests pass their own.
- `SmtpTransport.php`: Built-in SMTP client (STARTTLS or TLS, AUTH PLAIN/LOGIN), see [04-api-and-integrations.md](04-api-and-integrations.md).
- `BookingEmails.php`: The emails around one booking (receipt, admin alert, approval with `.ics`, rejection, moved to another employee); builds the texts from the booking, services and settings.
- `CustomerMessages.php`: Emails the admin writes to customers (one, those with upcoming bookings, all), found through their bookings; one email per customer and the list of sent messages (see [04-api-and-integrations.md](04-api-and-integrations.md)).
- `SecurityHeaders.php`: HTTP security headers, sent by `bootstrap.php` for every web request (§1.5).
- `RateLimiter.php`, `FormToken.php`: Abuse protection for the public booking API (see [04-api-and-integrations.md](04-api-and-integrations.md)); `RateLimiter` also counts failed admin sign-ins.
- `Input.php`: The input validation layer (§1.6).
- `AdminAccounts.php`: Admin accounts in `admin/.htpasswd`: password check, hashing, password policy (§1.4).
- `SecurityLog.php`: Security event log (§1.7).
- `SetupCheck.php`: The dashboard's list of missing setup items.
- `CancelLink.php`: Per-booking cancellation token (HMAC of the booking ID) and link.
- `Secrets.php`: Signing keys in `data/secrets.json` (`form_secret`, `cancel_secret`), created on first use.
- `CalendarFeed.php`: Feed token in `data/secrets.json` and the iCalendar output of `calendar.php`.
- `ContactCard.php`: Contact cards (vCard 3.0) for the venue and the employee, sent as `contact.vcf` with the approval email; venue location for the calendar file (see [04-api-and-integrations.md](04-api-and-integrations.md)).
- `Staff.php`: Who can be booked: bookable employees, the treatments each offers and their weekly hours and date exceptions, cut to the opening hours (see [03-business-logic-and-engine.md](03-business-logic-and-engine.md) §3.2).
- `src/bootstrap.php` also defines the escaping and request helpers used everywhere.

## 1.3 Localization
- One flat JSON dictionary per language in `src/language/`, keys namespaced as `area.section.camelCase` (e.g. `admin.employees.familyName`, `booking.timeline.selected`). Placeholders use `{name}`.
- Language order: `?lang=` (remembered in a cookie for one year), the cookie, `config.json` `app.language`, then `en`.
- Missing keys fall back to English per key, then to the key itself.
- `en` and `sv` are complete; other languages may be partial but must only contain keys that exist in `en.json` (enforced by `tests/LanguageFilesTest.php`).
- JavaScript receives its texts through a JSON `<script>` tag (`Localization::exportPrefix('booking.')`). API errors carry a stable `code` (e.g. `slot_unavailable`) that the page maps to `booking.error.slotUnavailable`.
- Dates and weekday names on the booking page are formatted by the browser (`Intl.DateTimeFormat`) using the current language.

## 1.4 Web access protection
- `src/.htaccess`: Turns off `display_errors` and forbids `cache/`, `data/`, `log/`, `classes/`, `templates/`, `bootstrap.php`, `admin/session.php` and any `.ht*` file.
- Repository root `.htaccess`: Only `src/` is reachable when the repository root is the document root.
- `src/admin/`: Sign-in form (`login.php`), checked by PHP against `admin/.htpasswd`. Apache Basic Auth is no longer used; `admin/.htaccess` only denies access to `.htpasswd`. Admin pages are sent with `Cache-Control: no-store`.

### Admin sign-in
- **Accounts:** `admin/.htpasswd` (`username:hash`, not in git), managed on the Users page. Usernames are 3–20 letters, digits or underscores. Passwords need at least 10 characters and at most 72 bytes (the bcrypt limit).
- **Hashes:** New passwords are stored as bcrypt (`$2y$`), which Apache also reads. Existing `$apr1$` (Apache MD5) and `$5$`/`$6$` (SHA-crypt) hashes are accepted and replaced by bcrypt at the next successful sign-in. Unknown usernames are checked against a dummy hash so the response time does not reveal which accounts exist.
- **Lockout:** Failed sign-ins are counted per client (5 per 15 minutes) and per username (10 per 15 minutes). Above either limit the form answers `429` with `Retry-After`, even for the right password, until the window has passed. A successful sign-in clears both counters.
- **Session:** Cookie `bookmecal_admin`, path limited to the admin folder, `HttpOnly`, `SameSite=Lax` (OAuth callbacks are cross-site navigations; all changes are CSRF-checked POSTs), `Secure` on HTTPS, strict mode. Sign-in gets a new session ID and CSRF token. A session ends after 30 minutes of inactivity, after 12 hours in any case, and when its account is removed or gets a new password (the session stores a fingerprint of the password hash).
- **Sign-out:** POST with CSRF token from the button in the admin header.
- **HTTPS:** With `settings.json` `admin_require_https: true`, admin pages refuse plain HTTP (`403`). Off by default for local development; turn it on in production.

## 1.5 Security headers
Sent by `SecurityHeaders::send()` from `bootstrap.php` (pages) and `api.php` (JSON):

| Header | Pages | API |
| :--- | :--- | :--- |
| `Content-Security-Policy` | `default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'; object-src 'none'` | `default-src 'none'; frame-ancestors 'none'; base-uri 'none'` |
| `X-Content-Type-Options` | `nosniff` | `nosniff` |
| `X-Frame-Options` | `DENY` | `DENY` |
| `Referrer-Policy` | `same-origin` | `same-origin` |
| `Cross-Origin-Opener-Policy` / `Cross-Origin-Resource-Policy` | `same-origin` | `same-origin` |
| `Permissions-Policy` | camera, microphone, geolocation, payment, usb disabled | same |
| `Strict-Transport-Security` | `max-age=31536000`, only when served over HTTPS | same |

`X-Powered-By` is removed. Static files get `nosniff`, `Referrer-Policy` and `Cross-Origin-Resource-Policy` from `src/.htaccess` (needs `mod_headers`).

Rules that keep the CSP working: no inline `<script>` code, no `style="…"` attributes, no inline event handlers (`onclick`) in templates. Data for scripts goes in `<script type="application/json">` blocks; scripts may set `element.style` properties. The language and session cookies are `HttpOnly`, `SameSite` and `Secure` when served over HTTPS.

## 1.6 Input validation
Request values pass through `Input` (in `src/classes/Input.php`) before they are used or stored. Each method takes the trimmed string and returns the clean value or `null`:

| Method | Accepts |
| :--- | :--- |
| `date` | `YYYY-MM-DD`, real calendar dates only |
| `time` | `HH:MM`, 24-hour |
| `email` | PHP's email validator, at most 254 bytes |
| `text(value, max, multiline)` | Valid UTF-8, at most `max` characters, no control characters (C0, DEL, C1, U+2028/2029). Multiline allows line breaks and tabs and normalizes line endings to `\n`. |
| `int(value, min, max)` | Digits with optional minus sign, in range |
| `decimal(value, min, max)` | Up to 4 decimals, comma or point, in range |
| `oneOf(value, allowed)` | Strict match against a list |
| `phone` | Digits, spaces and `+ - ( )`, 3–30 characters |
| `id(value)` | Letters (any script), digits, `_ . -`, no `..` and no slashes |

Used by the API (dates, times, names, emails, service IDs) and by every admin form (services, employees, holidays, opening hours, settings). Limits: customer name 100, service name 255, SKU 64, description 2000, duration 5–720 and cleanup 0–240 minutes, price 0–1,000,000, VAT 0–100 %, employee text fields 100 and qualifications 500 characters, holiday name 100. Templates still escape all output with `e()`.

## 1.7 Security event log
`SecurityLog::record()` appends one JSON line per event to `src/log/security.log` (not in git, rotated to `security.log.1` at 1 MB). Fields: `time`, `event`, `ip`, `client` (rate-limit key) and event details. Never customer names, emails or passwords.

| Event | When |
| :--- | :--- |
| `rate_limited` | Rate limit hit (`bucket`: `read` or `book` for the API, `feed` for the calendar feed, `cancel` for the cancellation page) |
| `honeypot` | Booking with the hidden field filled in |
| `form_too_fast`, `form_token_invalid` | Booking form token too young, missing or forged |
| `pending_limit` | Pending cap per email reached |
| `login_ok`, `login_failed`, `login_locked` | Admin sign-in (with `username`) |
| `csrf_failed` | Admin POST without a valid CSRF token (with `page`) |
| `cancel_token_invalid` | Cancellation page opened with an unknown booking or a wrong token |
| `feed_token_invalid` | Calendar feed requested with a wrong or missing token, or while the feed is off |

The newest 100 events are shown on the admin Users page. The one-line JSON format suits `fail2ban` (match on `"event":"login_failed"` and the `ip` field) for blocking at the firewall.

## 1.8 Personal data (GDPR)
The booking stores the customer's name, email address, chosen treatment and time, language and request time. Nothing else about customers is collected; there are no tracking cookies (the only public cookie remembers the language).

- **Information:** The booking form says the name and email are only used for the booking and links to the privacy notice (`privacy.php`, also linked from every public page footer). The notice covers controller and contact, data, purpose and legal basis (GDPR 6.1 b; the security log 6.1 f), retention, sharing, cookies and the customer's rights. Business name and contact email come from `settings.json` `business_name` and `privacy_contact` (admin Config page); without them the notice uses neutral wording. Texts: `privacy.*` keys (en and sv complete).
- **Retention:** Bookings are deleted automatically `booking_retention_days` days after the appointment date (default 365, 0 = keep, admin Config page). `BookingRepository::housekeeping()` runs the purge together with the pending expiry on every API request and on the admin dashboard and bookings pages.
- **Access and erasure:** On the admin bookings page, a search by email lists one customer's bookings (case-insensitive). From there the admin can download them as JSON (right of access) or delete them all (right to erasure); single bookings can be deleted from any list. Bulk deletion only works with an email filter. Exports and deletions are recorded in the audit log without the email address.
- **Calendar subscription:** When the admin turns on the calendar feed, the calendar app (and its provider, e.g. Apple or Google) keeps a copy of the bookings. The privacy notice then adds `privacy.sharing.calendar` to the sharing section. Renewing the link or turning the feed off stops further updates, but the provider's copy is outside this system.
- **Emails:** Customer emails contain only their own booking. With `public_url` set they end with a link to the privacy notice. The admin alert contains the customer's name and email and goes only to `smtp.notification_email`.
- **Other personal data:** The admin audit log keeps admin usernames and IP addresses (newest 200 entries). The security log keeps IP addresses of rejected requests and sign-ins and rotates at 1 MB. Rate-limit files contain only hashes.
