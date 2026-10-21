# 2. Data Layer & Repository (BookingRepository)

## Architecture Pattern
The system employs a Repository pattern to cleanly separate data storage from business logic. Storage is handled via flat-file JSON documents inside a writable `src/data/` directory.

## Datasets in `src/data/`
- `pending/booking_<id>.json`: One file per booking request. `<id>` matches `^book_[0-9a-f]+$`. Fields: `id`, `service_id`, `date` (`YYYY-MM-DD`), `time` (`HH:MM`), `end_time` (`HH:MM`, end of the treatment, without cleanup), `customer_name`, `customer_email`, `language`, `status` (`pending`, `approved`, `confirmed`, `rejected`, `cancelled`), `created_at_iso`, optional `reminder_sent`.
- `pending/.booking.lock`: Lock file held while a booking is checked and saved (prevents double booking).
- `services.json`: Treatments keyed by SKU, see [services.schema.json](services.schema.json). `duration` and `cleanup` are in minutes.
- `openhours.json`: Opening hours per weekday (`is_open`, `open_time`, `close_time`). This is what the public booking uses.
- `booking/availability.json`: Admin slot matrix. Not used by the public booking at the moment.
- `employees/emp_<id>.json`: One file per employee, see [employee.schema.json](employee.schema.json). Fields not known to the admin form are kept on save.
- `config.json`: Application configuration, see [config.schema.json](config.schema.json).
- `settings.json`: `default_country`, `timezone`, `supported_timezones`.
- `credentials.json` / `tokens.json`: Google OAuth client and tokens.
- `audit.json`: Admin audit log, capped at the latest 200 entries.
- `src/holidays/<cc>.json`: Holidays per country, `<cc>` is a two-letter code, see [holiday.schema.json](holiday.schema.json).

## Concurrency & Thread Safety
- `JsonStore::write()` and `update()` take an exclusive lock (`LOCK_EX`); `update()` does read-modify-write under one lock. `read()` reads without a lock (todo: shared lock or atomic replace, so a reader never sees a half-written file).
- `BookingRepository::withBookingLock()` runs "check availability, then create" under an exclusive lock on `pending/.booking.lock`, so two concurrent requests cannot take the same time.

## BookingRepository
- `getBookings()`: All bookings, sorted by date and time.
- `updateBooking(id, changes)`: Locked merge into one booking file. IDs are validated against the pattern above.
- `getBlockingIntervals(date)`: Intervals taken by bookings with a blocking status (`pending`, `approved`, `confirmed`) on that date. Each interval runs from `time` to `end_time` (or `time + duration` for older records) plus the booked service's `cleanup`.
- `createPendingRequest(data)`: Writes a new booking file.
