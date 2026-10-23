# 5. Todo List, Roadmap & Revision History

## Active Todo List & Roadmap

The project is a proof of concept, run on a local workstation over plain HTTP and trialled with a few users. English and Swedish are the languages that must be complete.

- [x] **Calendar Feeds:** Read-only subscription feed and `.ics` file in the approval email done. Customer cancellation link done.
- [ ] **Advanced Google Calendar API:** Add two-way synchronization for adding and removing calendar events via the Google Calendar API.
- [x] **Bulk & Direct SMTP Messaging:** Booking emails done (receipt, admin alert, approval, rejection). Authenticated SMTP done (built-in `SmtpTransport`, Config page with test email). Messages to one customer, the customers with upcoming bookings, or all customers done (Messages page).

### When deployed to a hosted server

The hosted server has HTTPS/TLS in front of it. These are setup steps there, not code work:

- [ ] **HTTPS for the admin:** Turn on `admin_require_https` ([08-deployment.md](08-deployment.md)).
- [ ] **Email delivery:** Set up SPF/DKIM for the sender domain and test that mails are not taken for spam.
- [ ] **Privacy link in emails:** Set `public_url` so customer emails link to the privacy notice.
- [ ] **fail2ban (optional):** Only where the host gives root access. The application's own sign-in lockout and rate limiting work without it ([08-deployment.md](08-deployment.md) §8.7).

### After the proof of concept

- [ ] **SMS Reminders:** Implement SMS reminder notifications 1–2 days ahead of appointments. A two-way number can be arranged through an SMS service company the owner knows, so customers could also reply (for example to confirm or cancel).
- [ ] **Data files out of git (before the first official release):** `config.json`, `settings.json`, `services.json`, `openhours.json` and `credentials.json` are tracked on purpose as examples and placeholders with development values, so the server is updated with `rsync` instead of `git pull` (see [08-deployment.md](08-deployment.md) §8.9). Ship `*.example.json` files and create the real ones on install.
- [ ] **Translations:** Native review of all language files; complete the 12 partial languages (they fall back to English per key).
- [ ] **Privacy notice review:** Have it reviewed for the actual business (it is a starting point, not legal advice).
- [ ] **Week view drag on touch devices:** Verify dragging the selection block on real phones and tablets.

Security hardening (OWASP ASVS level 1) done in code: rate limiting, honeypot, form token, pending cap and expiry, CORS allow-list, security headers, input validation layer, admin sign-in with lockout, security event log, GDPR basics (privacy notice, retention, access and erasure).

---

## Revision History & Completed Milestones Log

| Completion Date | Description / Completed Milestones |
| :--- | :--- |
| **2026-10-23** | • Messages page: email one customer, the customers with upcoming bookings (by dates, employee or treatment) or all customers who have booked. Preview with the recipient list first, one email per customer, `{name}` filled in, footer with the reason and the privacy link, list of sent messages.<br>• To-do list grouped into proof-of-concept work, steps on the hosted server, and later work. |
| **2026-10-23** | • Emails can go through the mail provider's SMTP server (STARTTLS or SSL/TLS, sign-in, certificate checked) set on the Config page, with a test email button; the password is kept out of git in `secrets.json`. No external library.<br>• Long email subjects are split into standard-length encoded words.<br>• Moving an approved booking to another employee can email the customer, with an updated calendar file and contact card.<br>• Optional "Avoid short unusable gaps" (Config page): customers are only offered times that leave no gap too short for any treatment. |
| **2026-10-22** | • Date exceptions to an employee's weekly hours (vacation, sick day, a shorter or extra day) on the Working hours page.<br>• Move a booking to another employee who offers the treatment and is free at that time (Booking requests page). |
| **2026-10-22** | • Contact cards: the approval email can carry `contact.vcf` with the venue's details (website, phone, email, address, WGS-84 position, plus code; Config page) and, with their consent, the employee's card. Employee profiles hold every field a vCard can carry (name parts, nickname, role, department, website, photo, languages, social profiles, presentation). The calendar file gets the venue's address and position. |
| **2026-10-22** | • Exclusive treatments: nothing else is booked at the same time, with any employee.<br>• Sets: several treatments booked together, done back to back by one employee who offers all of them, with their own price and the summed duration (Services page). |
| **2026-10-22** | • Availability per employee: opening hours are the outer limit, each employee's weekly working hours (Working hours page, `schedule` in the employee file) the inner one, and several employees can be booked at the same time. Employees get a treatment list (none ticked = all). Customers choose an employee or "first available" (the free one with the fewest bookings that day); bookings store `employee_id`. The employee is shown on the bookings page, dashboard, calendar feed and in the emails. Replaces the unused shared slot matrix `data/booking/availability.json`, which was removed. |
| **2026-10-22** | • `src/data/.htaccess` denies all requests (403). It used to redirect to a broken address that showed the server's file path. |
| **2026-10-22** | • Setup checklist on the admin dashboard ("Before customers book").<br>• Deployment guide ([08-deployment.md](08-deployment.md)) with a cron file and a `fail2ban` filter and jail in `deploy/`. |
| **2026-10-22** | • Customer cancellation: secret link in the receipt, approval and reminder emails opens `cancel.php`, which cancels after a button press until `cancel_cutoff_hours` (default 24, Config page) before the appointment; the business gets an alert. Signing keys moved to a shared `Secrets` class.<br>• Removed unused email keys (`email.confirmation.*`, `email.admin.newBooking.*`, `email.admin.cancellation.*`, `email.reminder.cancellationLink`). |
| **2026-10-22** | • Booking emails: receipt to the customer and alert to the business on a new request, confirmation with an `.ics` calendar file on approval, decline on rejection. Email on/off, sender, alert address and site address (`public_url`, for links) on the admin Config page; the bookings page says whether the customer was emailed.<br>• Translation placeholders are filled in one pass. |
| **2026-10-22** | • Calendar subscription feed (`calendar.php`, iCalendar): approved bookings and, as tentative, unanswered requests, for Apple, Google or Outlook calendars. Secret link turned on, renewed and turned off on the admin Config page; wrong tokens logged; privacy notice mentions the calendar provider while the feed is on.<br>• Removed the unused `admin_user`/`admin_pass` from `data/config.json`. |
| **2026-10-22** | • `JsonStore::read()` takes a shared lock: readers no longer see half-written files during an update. |
| **2026-10-22** | • GDPR basics: privacy notice page (`privacy.php`) linked from the booking form and every public page, business name and privacy contact on the Config page, bookings deleted automatically after `booking_retention_days` (default 365), admin search by customer email with JSON export (access) and delete all (erasure), single bookings deletable. |
| **2026-10-22** | • Input validation layer (`Input`) used by the API and every admin form: real dates and times, length limits in characters, no control characters, numeric ranges.<br>• Admin sign-in form instead of Apache Basic Auth: lockout after failed attempts (per client and per username), bcrypt hashes (old Apache hashes upgraded at sign-in), password policy, session timeouts, sign-out, sessions end when the password changes, optional HTTPS-only admin.<br>• Security event log (`src/log/security.log`, JSON lines) for API rejections, sign-ins and CSRF failures, shown on the Users page. |
| **2026-10-21** | • Booking abuse protection: per-client rate limits, honeypot field, signed form token with minimum and maximum age, max 3 upcoming pending bookings per email, unanswered requests expire after `pending_expiry_hours` (admin setting, default 48), JSON-only booking requests with a size limit, CORS only for configured origins.<br>• Security headers on all pages and API responses (strict Content-Security-Policy, `nosniff`, frame denial, referrer and permissions policies, HSTS on HTTPS); admin pages not cached. |
| **2026-10-21** | • Soft slots: treatments can start every 5 minutes (`BookingService::SLOT_STEP`) wherever treatment plus cleanup fits, instead of a fixed grid per treatment. Past times are no longer offered.<br>• New `check_week` API action and a week view on the booking page (days as columns, time downwards) with a draggable, snapping selection block, keyboard control and Earlier/Later buttons. |
| **2026-10-21** | • Separated HTML from code: controllers plus templates in `src/templates/` rendered by `View`, CSS and JS moved to `src/css/` and `src/js/`, shared `bootstrap.php` and `JsonStore`.<br>• All UI text translatable with namespaced keys (`area.section.camelCase`); `en` and `sv` complete, other languages fall back to English per key; language switcher on public and admin pages.<br>• Employees stored per file following `employee.schema.json`; services and employees editable; dashboard shows real upcoming bookings. |
| **2026-10-21** | • Double-booking prevention with an exclusive booking lock and blocking intervals that include the cleanup buffer.<br>• Unified language file keys. |
| **2026-10-21** | • Fixed approve/reject, reminder sending, service duration field (`duration`), inactive services shown, service save wiping fields and several file paths. |
| **2026-10-21** | • Fixed parse errors and hardened dangerous code paths: path traversal via country code, fail-closed admin auth, OAuth `state`, mail header injection, API input validation, hidden error output, protected logs. |
| **2026-10-13** | • Committed refactored services architecture to the dev repository featuring SKU codes, active/deactivated states, VAT calculations, and duration/cleanup buffers.<br>• Integrated regional holiday management and robust flat-file data handling. |
| **2026-10-10** | • Implemented dedicated illustrated Google setup guide page (`google_guide.php`) for client credentials and calendar ID configuration.<br>• Added config unenroll action to securely purge Google OAuth tokens and reset integration state. |
| **2026-10-10** | • Migrated configuration to `data/config.json` supporting locale, language, and date/time formatting options.<br>• Refactored `BookingRepository` to manage individual pending requests inside `data/pending/` and availability slots in `data/booking/availability.json`.<br>• Separated sensitive Google OAuth credentials into `data/tokens.json`.<br>• Implemented real Google Calendar identity and OAuth redirect enrollment wizard (`setup_google.php`).<br>• Added rolling retention policy capped at 200 entries for audit logs (`audit.json`).<br>• Added storage backend selection (JSON, SQLite, MySQL) with safety warnings in configuration. |
| **2026-10-10** | • Built concurrency and file-locking test suite (`test_concurrency.php`) simulating parallel process writes to verify thread-safe `flock()` protection against race conditions and JSON corruption. |
| **2026-10-10** | • Implemented object-oriented multi-language support (`Localization.php`) with static JSON dictionary caching, cookie/session language selection, and the `__t()` global helper function, accompanied by English and Swedish language dictionaries. |
| **2026-10-10** | • Implemented security & anti-spam hardening: Added CSRF protection tokens and POST-based action forms in the admin dashboard (`admin.php`), and IP-based rate limiting on booking submissions in `api.php`. |
| **2026-10-10** | • Created automated installation and environment verification script (`install.php`) checking directory write permissions, PHP extensions, and generating initial configurations. |
| **2026-10-10** | • Implemented automated 24-hour email reminders script (`send_reminders.php`) for CLI/Cron execution with thread-safe file locks and duplicate prevention (`reminder_sent` flag). |
| **2026-10-10** | • Built administrative management dashboard (`admin.php`) with authentication and status update actions (approve/reject). |
| **2026-10-10** | • Implemented SMTP and mail notification handling (`EmailNotifier.php`) for client confirmations and admin alerts.<br>• Integrated `EmailNotifier` into `api.php` booking submission workflow. |
| **2026-10-10** | • Frontend integration (`index.php`) utilizing FullCalendar with month and week views.<br>• Removed legacy `index.html` to prioritize dynamic PHP routing. |
| **2026-10-10** | • Initial architectural setup and file-based storage strategy with thread-safe file locking (`BookingRepository.php`).<br>• Core time slot value object and business logic engine (`TimeSlot.php`, `BookingEngine.php`).<br>• Apache configuration, URL routing, and security rules (`.htaccess`).<br>• REST API endpoint (`api.php`) for slot fetching and booking requests. |

