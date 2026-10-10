# 5. Todo List, Roadmap & Revision History

## Active Todo List & Roadmap
- [ ] **Multi-Language & Localization Dictionaries:** Expand `Localization.php` with dedicated translation dictionaries (e.g., English and Swedish) to handle UI labels, error messages, and email templates dynamically.
- [ ] **Testing & Verification Suite:** Build a lightweight test script to simulate concurrent booking requests and verify that thread-safe file-locking (`flock()`) correctly prevents race conditions and data corruption.

---

## Revision History & Completed Milestones Log

| Completion Date | Description / Completed Milestones |
| :--- | :--- |
| **2026-10-10** | • Implemented security & anti-spam hardening: Added CSRF protection tokens and POST-based action forms in the admin dashboard (`admin.php`), and IP-based rate limiting on booking submissions in `api.php`. |
| **2026-10-10** | • Created automated installation and environment verification script (`install.php`) checking directory write permissions, PHP extensions, and generating initial configurations. |
| **2026-10-10** | • Added post-launch hardening, localization, installer, and testing items to the active roadmap backlog. |
| **2026-10-10** | • Implemented automated 24-hour email reminders script (`send_reminders.php`) for CLI/Cron execution with thread-safe file locks and duplicate prevention (`reminder_sent` flag). |
| **2026-10-10** | • Built administrative management dashboard (`admin.php`) with authentication and status update actions (approve/reject). |
| **2026-10-10** | • Implemented SMTP and mail notification handling (`EmailNotifier.php`) for client confirmations and admin alerts.<br>• Integrated `EmailNotifier` into `api.php` booking submission workflow. |
| **2026-10-10** | • Frontend integration (`index.php`) utilizing FullCalendar with month and week views.<br>• Removed legacy `index.html` to prioritize dynamic PHP routing. |
| **2026-10-10** | • Updated backlog to reflect the standalone lightweight file-based storage model.<br>• Added detailed FullCalendar frontend specifications (month view, 6–9 week forward range, timeGrid week view). |
| **2026-10-10** | • Initial architectural setup and file-based storage strategy with thread-safe file locking (`BookingRepository.php`)<br>• Core time slot value object and business logic engine (`TimeSlot.php`, `BookingEngine.php`)<br>• Apache configuration, URL routing, and security rules (`.htaccess`)<br>• REST API endpoint (`api.php`) for slot fetching and booking requests |
