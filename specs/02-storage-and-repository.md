# 2. Data Layer & Repository (BookingRepository)

## Architecture Pattern
The system employs a Repository pattern to cleanly separate data storage from business logic. Storage is handled via flat-file JSON documents inside a writable `src/data/` directory.

## Datasets in `src/data/`
- `pending/booking_<id>.json`: One file per booking request. `<id>` matches `^book_[0-9a-f]+$`. Fields: `id`, `service_id`, `employee_id` (who takes it; `null` when there is no staff, missing in older records), `employee_requested` (the customer chose the employee instead of "first available"), `date` (`YYYY-MM-DD`), `time` (`HH:MM`), `end_time` (`HH:MM`, end of the treatment, without cleanup), `customer_name`, `customer_email`, `language`, `status` (`pending`, `approved`, `confirmed`, `rejected`, `cancelled`, `expired`), `created_at_iso`, optional `reminder_sent`, `processed_by`, `processed_at`, `decision_emailed` (whether the approve/reject email was sent), `expired_at_iso`, `cancelled_at_iso` and `cancelled_by` (`customer`) after an online cancellation.
- `pending/.booking.lock`: Lock file held while a booking is checked and saved (prevents double booking).
- `services.json`: Treatments keyed by SKU, see [services.schema.json](services.schema.json). `duration` and `cleanup` are in minutes. `exclusive` (default false): nothing else may run at the same time. `items` (list of keys): the service is a set of these treatments; its `duration` and `cleanup` are then `null` and calculated ([03-business-logic-and-engine.md](03-business-logic-and-engine.md) §3.2).
- `openhours.json`: Opening hours per weekday (`is_open`, `open_time`, `close_time`): the outer limit of all bookings.
- `employees/emp_<id>.json`: One file per employee, see [employee.schema.json](employee.schema.json). Fields not known to the admin form are kept on save. `services` (treatments offered, empty = all) and `schedule` (weekly working hours, missing = follows the opening hours) and `exceptions` (per-date hours or days off) decide when the employee can be booked ([03-business-logic-and-engine.md](03-business-logic-and-engine.md) §3.2). The former shared slot matrix `booking/availability.json` was replaced by `schedule` and removed.
- `config.json`: Application configuration, see [config.schema.json](config.schema.json). `smtp`: `enabled`, `from_email`, `from_name`, `notification_email` (edited on the admin Config page).
- `settings.json`: `default_country`, `timezone`, `supported_timezones`, `show_past_slots`, `pending_expiry_hours` (default 48, 0 = never; editable on the admin Config page), `allowed_origins` (list of origins allowed to call the API cross-origin, default empty) `admin_require_https` (default false; refuse admin pages over plain HTTP), `booking_retention_days` (default 365, 0 = keep; bookings are deleted that many days after the appointment), `business_name` and `privacy_contact` (shown in the privacy notice), `public_url` (base address of the booking site, used for links in emails; empty = no links), `cancel_cutoff_hours` (default 24; customers can cancel online until this many hours before the appointment, 0 = until the start), `venue` (contact details: `website`, `phone`, `email`, `street`, `postal_code`, `city`, `country`, `latitude` and `longitude` in WGS-84 decimal degrees, `plus_code`; Config page, Contact card) and `vcard` (`off` default, `venue`, `staff`: which contact cards the approval email carries).
- `secrets.json`: Generated on first use; holds the keys that sign booking form tokens (`form_secret`) and cancellation links (`cancel_secret`) and, while the calendar feed is on, its token (`calendar_feed_token`). Not in git.
- `ratelimit/<sha256>.json`: Request timestamps per client and bucket (the file name is a hash, no IP addresses are stored). Files untouched for a day are swept. Not in git.
- `credentials.json` / `tokens.json`: Google OAuth client and tokens.
- `audit.json`: Admin audit log (including sign-in and sign-out), capped at the latest 200 entries. Not in git.
- `src/admin/.htpasswd`: Admin accounts (`username:bcrypt hash`). Not in git.
- `src/log/security.log`: Security events as JSON lines, rotated at 1 MB. Not in git.
- `src/holidays/<cc>.json`: Holidays per country, `<cc>` is a two-letter code, see [holiday.schema.json](holiday.schema.json).

## Concurrency & Thread Safety
- `JsonStore::write()` and `update()` take an exclusive lock (`LOCK_EX`); `update()` does read-modify-write under one lock. `read()` takes a shared lock (`LOCK_SH`), so it waits for a running write and never sees a half-written file (verified by `JsonStoreTest`).
- `BookingRepository::withBookingLock()` runs "check availability, then create" under an exclusive lock on `pending/.booking.lock`, so two concurrent requests cannot take the same time.

## BookingRepository
- `getBookings()`: All bookings, sorted by date and time.
- `updateBooking(id, changes)`: Locked merge into one booking file. IDs are validated against the pattern above.
- `getBlockingIntervals(date)`: Intervals taken by bookings with a blocking status (`pending`, `approved`, `confirmed`) on that date, each with the booking's `employee_id` (`null` = blocks every employee) and `exclusive` (the booked treatment is exclusive: blocks every employee). Each interval runs from `time` to `end_time` (or `time + duration` for older records) plus the booked service's `cleanup`.
- `createPendingRequest(data)`: Writes a new booking file.
- `expireStalePending(hours)`: Sets `pending` bookings created more than `hours` ago to `expired` (re-checked under the file lock), which frees their time. Called by the API and the admin dashboard and pending pages. Expired bookings can no longer be approved.
- `countUpcomingPendingFor(email)`: Pending bookings for an email address (case-insensitive) that have not started yet.
- `purgeEndedBefore(days)`: Deletes bookings whose appointment date is more than `days` days ago (0 = keep). Records without a valid date are left alone.
- `housekeeping()`: `expireStalePending()` plus `purgeEndedBefore()` with the configured values; called by the API and the admin dashboard and bookings pages.
- `deleteBooking(id)`: Removes one booking file (IDs validated).
- `getBookingsFor(email)`: All bookings of one customer (case-insensitive), for access and erasure requests.
