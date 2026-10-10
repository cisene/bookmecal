# Book Me Calendar - Master Specification Index

## 1. Core Architecture & Storage
* **Storage Philosophy:** Zero-maintenance, file-based JSON storage repository utilizing thread-safe `flock()` concurrency control.
* **Configuration:** Centralized settings managed in `data/config.json` (supporting configurable locales, languages, and date/time formats).
* **Directory Structure:**
  * `src/data/` (Protected by `.htaccess` redirection rules)
  * `src/data/pending/` (Individual JSON files for incoming booking requests)
  * `src/data/booking/availability.json` (Slot matrices and operating hours)

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
* **Installer (`install.php`):** Automated server environment checks, permission verifications, and initial configuration generation.
* **Security & Anti-Spam (`admin.php`, `api.php`):** CSRF token validation, session management, and IP-based rate limiting on booking submissions.
* **Localization (`Localization.php`):** Multi-language support (English/Swedish) with static JSON dictionary caching and the `__t()` helper function.
* **Zero-Maintenance Background Sync:** Automated Google Calendar appointment request synchronization (with automatic consumption/deletion of processed pending files to prevent duplicates) and 24-hour reminder emails triggered transparently during admin dashboard access.
* **Testing Suite (`test_concurrency.php`):** Parallel process simulation to verify thread-safe file-locking protection against race conditions.
