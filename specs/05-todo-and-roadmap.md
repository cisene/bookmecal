# 5. Todo List, Roadmap & Revision History

## Active Todo List & Roadmap

* [x] Make locale configurable in `data/config.json`
* [x] Make language configurable in `data/config.json`
* [x] Make date formats configurable in `data/config.json`
* [x] Make time formats configurable in `data/config.json`
* [x] Make pending booking requests folder in `data/pending/`
* [x] Pending booking requests are JSON files with all data regarding booking request
* [x] Available booking slots are stored in `data/booking/availability.json`
* [x] Separate sensitive Google OAuth credentials into `data/tokens.json`
* [x] Implement real Google Calendar identity and OAuth redirect enrollment wizard (`setup_google.php`)
* [x] Add dedicated illustrated Google setup guide page (`google_guide.php`) for `client_id`, `client_secret`, and `calendar_id`
* [x] Implement config unenroll action to purge Google OAuth tokens and reset integration state
* [x] Add rolling retention policy capped at 200 entries for audit logs (`audit.json`)
* [x] Add storage backend selection (JSON, SQLite, MySQL) with safety warnings in configuration

---

## Revision History & Completed Milestones Log

| Completion Date | Description / Completed Milestones |
| --- | --- |
| **2026-10-10** | • Implemented real Google OAuth 2.0 redirect enrollment workflow in `setup_google.php` with automatic authorization code exchange.<br>

<br>• Created an illustrated step-by-step setup guide (`google_guide.php`) to help administrators locate Google Cloud Console client credentials and calendar IDs.<br>

<br>• Added config UI enrollment detection that disables the wizard button when active and provides a red unenroll button to purge tokens and reset integration.<br>

<br>• Isolated sensitive Google OAuth credentials (`access_token`, `refresh_token`, `token_expiry`) into `src/data/tokens.json` separately from global application configuration.<br>

<br>• Enforced a rolling retention policy strictly capped at 200 entries for `src/data/audit.json`.<br>

<br>• Added storage backend options (JSON, SQLite, MySQL) with security warning text under the configuration panel.<br>

<br>• Replaced `preg_match` validation routines with native string functions (`strlen`, `ctype_alnum`) to resolve PCRE JIT memory allocation restrictions. |
| **2026-10-10** | • Migrated configuration to `data/config.json` supporting locale, language, and date/time formatting options.<br>

<br>• Refactored `BookingRepository` to manage individual pending requests inside `data/pending/` and availability slots in `data/booking/availability.json`. |
| **2026-10-10** | • Built concurrency and file-locking test suite (`test_concurrency.php`) simulating parallel process writes to verify thread-safe `flock()` protection against race conditions and JSON corruption. |
| **2026-10-10** | • Implemented object-oriented multi-language support (`Localization.php`) with static JSON dictionary caching, cookie/session language selection, and the `__t()` global helper function, accompanied by English and Swedish language dictionaries. |
| **2026-10-10** | • Implemented security & anti-spam hardening: Added CSRF protection tokens and POST-based action forms in the admin dashboard (`admin.php`), and IP-based rate limiting on booking submissions in `api.php`. |
| **2026-10-10** | • Created automated installation and environment verification script (`install.php`) checking directory write permissions, PHP extensions, and generating initial configurations. |
| **2026-10-10** | • Implemented automated 24-hour email reminders script (`send_reminders.php`) for CLI/Cron execution with thread-safe file locks and duplicate prevention (`reminder_sent` flag). |
| **2026-10-10** | • Built administrative management dashboard (`admin.php`) with authentication and status update actions (approve/reject). |
| **2026-10-10** | • Implemented SMTP and mail notification handling (`EmailNotifier.php`) for client confirmations and admin alerts.<br>

<br>• Integrated `EmailNotifier` into `api.php` booking submission workflow. |
| **2026-10-10** | • Frontend integration (`index.php`) utilizing FullCalendar with month and week views.<br>

<br>• Removed legacy `index.html` to prioritize dynamic PHP routing. |
| **2026-10-10** | • Updated backlog to reflect the standalone lightweight file-based storage model.<br>

<br>• Added detailed FullCalendar frontend specifications (month view, 6–9 week forward range, timeGrid week view). |
| **2026-10-10** | • Initial architectural setup and file-based storage strategy with thread-safe file locking (`BookingRepository.php`)<br>

<br>• Core time slot value object and business logic engine (`TimeSlot.php`, `BookingEngine.php`)<br>

<br>• Apache configuration, URL routing, and security rules (`.htaccess`)<br>

<br>• REST API endpoint (`api.php`) for slot fetching and booking requests |