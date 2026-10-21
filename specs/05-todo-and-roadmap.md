# 5. Todo List, Roadmap & Revision History

## Active Todo List & Roadmap

- [ ] **Calendar Feeds:** Implement read-only Apple Calendar and Google Calendar feed integrations (`ics` export/sync).
- [ ] **Advanced Google Calendar API:** Add two-way synchronization for adding and removing calendar events via the Google Calendar API.
- [ ] **Bulk & Direct SMTP Messaging:** Complete SMTP integration for sending targeted emails to a single customer, group, or all customers.
- [ ] **SMS Reminders:** Implement SMS reminder notifications 1–2 days ahead of appointments.
- [ ] **One availability source:** The admin slot matrix (`data/booking/availability.json`) is not used by the public booking, which uses opening hours (`data/openhours.json`). Merge or remove one of them.
- [ ] **Week view drag on touch devices:** Verify dragging the selection block on real phones and tablets.
- [ ] **Gap-aware starts:** Optionally prefer soft-slot starts next to existing bookings to reduce unusable gaps.
- [ ] **Translations:** Native review of all language files; complete the 12 partial languages (they fall back to English per key).
- [ ] **Security hardening (OWASP ASVS level 1):** Done: rate limiting, honeypot, form token, pending cap and expiry, CORS allow-list, security headers. Next: one input validation layer, admin login protection (HTTPS, brute-force lockout), security event logging, GDPR (privacy notice, retention, deletion).
- [ ] **Locked reads:** `JsonStore::read()` should take a shared lock or writes should replace files atomically.
- [ ] **Config cleanup:** Remove the unused plaintext `admin_user`/`admin_pass` from `data/config.json` (admin login uses `.htpasswd`).
- [ ] **Stop tracking runtime files:** `src/log/*.log` and `src/data/audit.json` are still in git.

---

## Revision History & Completed Milestones Log

| Completion Date | Description / Completed Milestones |
| :--- | :--- |
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

