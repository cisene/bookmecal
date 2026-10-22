# Book Me Calendar - Master Specification Index

## 1. Core Architecture & Storage
* **Storage Philosophy:** Zero-maintenance, file-based JSON storage repository utilizing thread-safe `flock()` concurrency control.
* **Configuration:** Centralized settings managed in `data/config.json` (supporting configurable locales, languages, and date/time formats).
* **Directory Structure:**
  * `src/data/` (Protected by `.htaccess` rules, see [02-storage-and-repository.md](02-storage-and-repository.md))
  * `src/data/pending/` (Individual JSON files for incoming booking requests)
  * `src/data/openhours.json` (Opening hours used by the booking)
  * `src/data/booking/availability.json` (Admin slot matrix, not used by the booking yet)
  * `src/templates/` (HTML templates), `src/classes/` (logic), `src/language/` (translations)
* **Availability:** Soft slots, a treatment can start every 5 minutes where treatment plus cleanup fits (see [availability_logic_appendix.md](availability_logic_appendix.md)).

---

## 2. Specification Schemas (MySQL, SQLite & JSON Standards)
Our specification treats relational schemas and their JSON data contracts as first-class artifacts:

* **Global Settings:** Key-value configuration parameters (`settings`).
* **Operating Hours & Slot Matrix:** Day-of-week active hours (`slot_matrix`).
* **Holiday Matrix:** Blocked non-working dates and descriptions (`holiday_matrix`).
* **Services & Pricing:** Treatment metadata, durations, and pricing (`services`).
* **Booking Requests & Lifecycle:** Client data, appointment statuses (`scheduled`, `approved`, `cancelled`, `late_no_show`), timezone preferences, preferred languages, cancellation tokens, and `gcal_event_id` tracking (`booking_requests`).

---

## 3. Implementation & Automation Modules
* **Booking page (`index.php`, `js/booking.js`):** Week view with a draggable selection block, see [04-api-and-integrations.md](04-api-and-integrations.md).
* **API (`api.php`):** `get_services`, `check_availability`, `check_week`, `book`.
* **Security (`admin/login.php`, `admin/session.php`, `api.php`, `Input`, `AdminAccounts`, `SecurityLog`, `SecurityHeaders`, `RateLimiter`, `FormToken`):** Admin sign-in with lockout and session timeouts, CSRF tokens on all admin forms, hardened cookies, one input validation layer for the API and admin forms, rate limiting, honeypot and form token on bookings, pending cap and expiry, CORS allow-list, strict Content-Security-Policy and other security headers, security event log (see [01-architecture-and-config.md](01-architecture-and-config.md) §1.4–1.7).
* **Booking emails (`BookingEmails`, `EmailNotifier`):** Receipt and admin alert on a new request, approval with an `.ics` file, rejection; on/off and sender on the Config page (see [04-api-and-integrations.md](04-api-and-integrations.md)).
* **Customer cancellation (`cancel.php`, `CancelLink`):** Secret link in the customer emails, cancel until `cancel_cutoff_hours` before the appointment (see [04-api-and-integrations.md](04-api-and-integrations.md)).
* **Calendar feed (`calendar.php`, `CalendarFeed`):** Read-only iCalendar subscription of bookings for Apple, Google or Outlook calendars, with a secret link managed on the Config page (see [04-api-and-integrations.md](04-api-and-integrations.md)).
* **Personal data (`privacy.php`, `admin/pending.php`, `BookingRepository`):** Privacy notice, automatic deletion after the retention period, export and deletion of one customer's bookings (see [01-architecture-and-config.md](01-architecture-and-config.md) §1.8).
* **Localization (`Localization.php`):** 14 languages with per-key English fallback and the `__t()` helper function (see [01-architecture-and-config.md](01-architecture-and-config.md) §1.3).
* **Reminders (`send_reminders.php`):** CLI/cron script sending 24-hour reminder emails for approved bookings.
* **Testing Suite (`tests/`):** PHPUnit tests, see [07-tests-and-validation.md](07-tests-and-validation.md).

### Not implemented yet
* Installer (`install.php`), background Google Calendar sync. See [05-todo-and-roadmap.md](05-todo-and-roadmap.md).
