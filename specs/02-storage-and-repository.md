# 2. Data Layer & Repository (BookingRepository)

## Architecture Pattern
The system employs a Repository pattern to cleanly separate data storage from business logic. Storage is handled via flat-file JSON documents inside a writable `src/data/` directory.

## Datasets in `src/data/`
- `pending/booking_<id>.json`: One file per booking request. `<id>` matches `^book_[0-9a-f]+$`. Fields: `id`, `service_id`, `date` (`YYYY-MM-DD`), `time` (`HH:MM`), `end_time` (`HH:MM`, end of the treatment, without cleanup), `customer_name`, `customer_email`, `language`, `status` (`pending`, `approved`, `confirmed`, `rejected`, `cancelled`, `expired`), `created_at_iso`, optional `reminder_sent`, `processed_by`, `processed_at`, `expired_at_iso`.
- `pending/.booking.lock`: Lock file held while a booking is checked and saved (prevents double booking).
- `services.json`: Treatments keyed by SKU, see [services.schema.json](services.schema.json). `duration` and `cleanup` are in minutes.
- `openhours.json`: Opening hours per weekday (`is_open`, `open_time`, `close_time`). This is what the public booking uses.
- `booking/availability.json`: Admin slot matrix. Not used by the public booking at the moment.
- `employees/emp_<id>.json`: One file per employee, see [employee.schema.json](employee.schema.json). Fields not known to the admin form are kept on save.
- `config.json`: Application configuration, see [config.schema.json](config.schema.json).
- `settings.json`: `default_country`, `timezone`, `supported_timezones`, `show_past_slots`, `pending_expiry_hours` (default 48, 0 = never; editable on the admin Config page), `allowed_origins` (list of origins allowed to call the API cross-origin, default empty) `admin_require_https` (default false; refuse admin pages over plain HTTP), `booking_retention_days` (default 365, 0 = keep; bookings are deleted that many days after the appointment), `business_name` and `privacy_contact` (shown in the privacy notice).
- `secrets.json`: Generated on first use; holds the key that signs booking form tokens. Not in git.
- `ratelimit/<sha256>.json`: Request timestamps per client and bucket (the file name is a hash, no IP addresses are stored). Files untouched for a day are swept. Not in git.
- `credentials.json` / `tokens.json`: Google OAuth client and tokens.
- `audit.json`: Admin audit log (including sign-in and sign-out), capped at the latest 200 entries. Not in git.
- `src/admin/.htpasswd`: Admin accounts (`username:bcrypt hash`). Not in git.
- `src/log/security.log`: Security events as JSON lines, rotated at 1 MB. Not in git.
- `src/holidays/<cc>.json`: Holidays per country, `<cc>` is a two-letter code, see [holiday.schema.json](holiday.schema.json).

## Concurrency & Thread Safety
- `JsonStore::write()` and `update()` take an exclusive lock (`LOCK_EX`); `update()` does read-modify-write under one lock. `read()` reads without a lock (todo: shared lock or atomic replace, so a reader never sees a half-written file).
- `BookingRepository::withBookingLock()` runs "check availability, then create" under an exclusive lock on `pending/.booking.lock`, so two concurrent requests cannot take the same time.

## BookingRepository
- `getBookings()`: All bookings, sorted by date and time.
- `updateBooking(id, changes)`: Locked merge into one booking file. IDs are validated against the pattern above.
- `getBlockingIntervals(date)`: Intervals taken by bookings with a blocking status (`pending`, `approved`, `confirmed`) on that date. Each interval runs from `time` to `end_time` (or `time + duration` for older records) plus the booked service's `cleanup`.
- `createPendingRequest(data)`: Writes a new booking file.
- `expireStalePending(hours)`: Sets `pending` bookings created more than `hours` ago to `expired` (re-checked under the file lock), which frees their time. Called by the API and the admin dashboard and pending pages. Expired bookings can no longer be approved.
- `countUpcomingPendingFor(email)`: Pending bookings for an email address (case-insensitive) that have not started yet.
- `purgeEndedBefore(days)`: Deletes bookings whose appointment date is more than `days` days ago (0 = keep). Records without a valid date are left alone.
- `housekeeping()`: `expireStalePending()` plus `purgeEndedBefore()` with the configured values; called by the API and the admin dashboard and bookings pages.
- `deleteBooking(id)`: Removes one booking file (IDs validated).
- `getBookingsFor(email)`: All bookings of one customer (case-insensitive), for access and erasure requests.
