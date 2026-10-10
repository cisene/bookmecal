# 5. Todo List, Roadmap & Revision History

## Active Todo List & Roadmap
- [ ] *(All core milestones completed! Ready for production deployment and optional feature expansions.)*

---

## Revision History & Completed Milestones Log

| Completion Date | Description / Completed Milestones |
| :--- | :--- |
| **2026-10-10** | • Implemented automated 24-hour email reminders script (`send_reminders.php`) for CLI/Cron execution with thread-safe file locks and duplicate prevention (`reminder_sent` flag). |
| **2026-10-10** | • Built administrative management dashboard (`admin.php`) with authentication and status update actions (approve/reject). |
| **2026-10-10** | • Implemented SMTP and mail notification handling (`EmailNotifier.php`) for client confirmations and admin alerts.<br>• Integrated `EmailNotifier` into `api.php` booking submission workflow. |
| **2026-10-10** | • Frontend integration (`index.php`) utilizing FullCalendar with month and week views.<br>• Removed legacy `index.html` to prioritize dynamic PHP routing. |
| **2026-10-10** | • Updated backlog to reflect the standalone lightweight file-based storage model.<br>• Added detailed FullCalendar frontend specifications (month view, 6–9 week forward range, timeGrid week view). |
| **2026-10-10** | • Initial architectural setup and file-based storage strategy with thread-safe file locking (`BookingRepository.php`)<br>• Core time slot value object and business logic engine (`TimeSlot.php`, `BookingEngine.php`)<br>• Apache configuration, URL routing, and security rules (`.htaccess`)<br>• REST API endpoint (`api.php`) for slot fetching and booking requests |
