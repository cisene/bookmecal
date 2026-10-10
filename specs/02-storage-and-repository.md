# 2. Data Layer & Repository (BookingRepository)

## Architecture Pattern
The system employs a Repository pattern to cleanly separate data storage from business logic. Storage is handled via flat-file JSON documents inside a writable `data/` directory.

## Datasets in `data/`
- `bookings.json`: Stores incoming booking requests with a `pending` status.
- `holidays.json`: Defines national holidays and closed dates.
- `operating_hours.json`: Defines operating hours per day of the week.

## Concurrency & Thread Safety
All file read and write operations use PHP's built-in `flock()` with shared (`LOCK_SH`) and exclusive (`LOCK_EX`) locks to prevent data corruption during concurrent requests.
