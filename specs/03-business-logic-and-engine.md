# 3. Business Logic & Engine

## Responsibilities
The business logic layer is fully decoupled from the storage medium and interface.

## 3.1 BookingService (used by the public booking)
`BookingService` implements **soft slots**: there is no fixed slot grid per treatment. A treatment may start at any step of `BookingService::SLOT_STEP` minutes (currently **5**) from opening time, as long as:

1. the date is not a holiday and the weekday is open (`openhours.json`),
2. the treatment (`duration`) ends no later than closing time,
3. treatment plus cleanup (`duration + cleanup`) does not overlap any blocking interval (§2), which already includes the other bookings' cleanup,
4. the start is not in the past.

The cleanup buffer may run past closing time; only the treatment itself has to end by closing.

Example: opening 08:00–17:00, Medical Foot Care 45 + 15 min, one booking at 10:05 (blocks 10:05–11:05). Free starts are 08:00, 08:05 … 09:05 and 11:05, 11:10 … 16:15.

Methods:
- `getAvailableSlots(date, serviceId, country)`: Free starts for one day as `{start_time, end_time, start_iso, end_iso}`. `null` for an invalid date or a service that is unknown, inactive or has no duration; `[]` when closed or full.
- `getWeekPlan(date, serviceId, country)`: Monday-to-Sunday plan for the week containing `date`, used by the booking page's week view (see §4).

See [availability_logic_appendix.md](availability_logic_appendix.md) for the evaluation pipeline.

## 3.2 Other classes
- `TimeSlot.php`: Value object representing a time interval (start and end datetimes).
- `BookingEngine.php`, `MultiSlotEngine.php`, `AvailabilityEngine.php`, `Database.php`, `GoogleSync.php`: Kept for the planned multi-customer version (Google Calendar busy times, SQL storage, multi-slot bookings). Not used by the public booking at the moment.
