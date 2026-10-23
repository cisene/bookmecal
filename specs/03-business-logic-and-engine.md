# 3. Business Logic & Engine

## Responsibilities
The business logic layer is fully decoupled from the storage medium and interface.

## 3.1 BookingService (used by the public booking)
`BookingService` implements **soft slots**: there is no fixed slot grid per treatment. A treatment may start at any step of `BookingService::SLOT_STEP` minutes (currently **5**) from opening time, as long as:

1. the date is not a holiday and the weekday is open (`openhours.json`),
2. the treatment (`duration`) ends no later than closing time,
3. at least one **employee** can take it (§3.2): the employee offers the treatment, works for the whole treatment, and treatment plus cleanup (`duration + cleanup`) does not overlap any of that employee's blocking intervals (§2), which already include the other bookings' cleanup,
4. the start is not in the past.

The cleanup buffer may run past closing time and past the employee's hours; only the treatment itself has to fit.

Example: opening 08:00–17:00, Medical Foot Care 45 + 15 min, one employee, one booking at 10:05 (blocks 10:05–11:05). Free starts are 08:00, 08:05 … 09:05 and 11:05, 11:10 … 16:15. With a second employee working the same hours, every start from 08:00 to 16:15 is free, and a third booking at 10:05 would be refused.

**Avoiding short gaps** (settings.json `avoid_small_gaps`, default off; Config page): customers are then offered only starts that leave, within the employee's free period, a gap of either 0 minutes or at least the shortest active treatment plus its cleanup, before and after. Before the end of the working period only the treatment counts, since cleanup may run past it; time already passed is no gap. When a free period leaves a shorter remainder whatever the start, only its first and last start are offered, so no free period loses all its starts. Applied per employee, so `assign()` only picks an employee for whom the start leaves no short gap; `canMoveTo()` ignores the rule. Example: 08:00–12:00, a 45 + 15 min treatment, a 20 + 10 min one (30 min is the shortest), a booking 10:00–11:00: offered 08:00, 08:30, 09:00, 11:00 and 11:15 instead of every 5 minutes.

Methods:
- `getAvailableSlots(date, serviceId, country, employeeId = null)`: Free starts for one day as `{start_time, end_time, start_iso, end_iso}`, for anyone (`null`) or one employee. `null` for an invalid date, a service that is unknown, inactive or has no duration, or an employee who cannot be booked for it; `[]` when closed or full.
- `getWeekPlan(date, serviceId, country, employeeId = null)`: Monday-to-Sunday plan for the week containing `date`, used by the booking page's week view (see §4).
- `assign(date, serviceId, time, employeeId = null, country)`: Who takes a booking at `time`: the chosen employee if free; with no choice, the free employee with the fewest bookings that day (ties: the order of the employee list, by family name). Returns `{employee_id, end_time}` or `null` when the time is not free. The `book` API action calls it and saves the booking under the booking lock.
- `canMoveTo(booking, employeeId, country)`: Whether a pending or approved booking can move to another employee: the employee offers the treatment and is free for it at the booking's time, ignoring the booking itself. The Booking requests page (`admin/pending.php`) moves a booking under the booking lock, sets `employee_requested` to `false` and records `moved_by`/`moved_at`; the customer is not emailed.
- `staffFor(serviceId)`, `hasStaff()`: Bookable employees offering a treatment; whether any bookable employee exists.

## 3.2 Staff: opening hours outside, employee hours inside
Opening hours are when the venue is open to customers: the **outer limit**. Each employee's weekly hours say when that employee can be booked: the **inner limit**. Several employees can be booked at the same time, so the venue's capacity at any moment is the number of free employees.

`Staff` (pure functions over employee records, see [employee.schema.json](employee.schema.json)):
- **Bookable:** `isBookable` is not `false` and `inactive` is not `true`.
- **Treatments:** `services` lists the treatments (services.json keys) the employee offers. An empty or missing list means all treatments.
- **Hours:** `schedule` maps each weekday to the whole hours worked, e.g. `"monday": ["08:00", "09:00", "13:00"]` (08:00–10:00 and 13:00–14:00). Consecutive hours join into one period, and every period is cut to that day's opening hours, so hours outside them are never bookable. An employee **without** a `schedule` follows the opening hours. The weekly pattern repeats every week.
- **Date exceptions:** `exceptions` maps a date to `{hours, note}`, e.g. `"2026-12-24": {"hours": ["09:00", "10:00"], "note": "Christmas Eve"}`. On that date the hours replace the weekly pattern (still cut to the opening hours); no hours means a day off (vacation, sick day). An exception also applies to an employee without a `schedule`. They are set per date range on the Working hours page (`Staff::setExceptions()`, at most `Staff::EXCEPTION_MAX_DAYS` = 366 days at a time) and shown joined into ranges (`Staff::exceptionRanges()`); past dates are dropped on save. Bookings already made on those dates are kept.
- **No staff:** When no bookable employee exists, the venue itself is the one bookable resource and the opening hours alone decide (the behaviour before employees).
- **Older bookings** without `employee_id` block every employee, so they can never be double booked.

### Exclusive treatments
A treatment with `exclusive: true` needs the venue to itself: while it runs (including cleanup) no other booking can run with any employee, and it can only start when no other booking is running. In the engine every booking blocks every employee when either the booked or the requested treatment is exclusive (`BookingRepository::getBlockingIntervals()` marks exclusive bookings).

### Sets
A set is a service with `items`: two or more single treatments (services.json keys) done back to back, in the order of the service list, by **one** employee who offers all of them. The set has its own name and price; its duration is the sum of the treatments' durations and its cleanup is the last treatment's cleanup (`ResourceProvider::serviceDuration()` / `serviceCleanup()`). A set is exclusive when it is marked so or any of its treatments is. Sets cannot contain sets; a set with a missing treatment (or one without a duration) cannot be booked and is left out of `get_services`. A treatment that is part of a set cannot be deleted. Normally a customer books one treatment at a time; a set is how several are booked together.

The customer can pick an employee or "first available" (§4). Bookings store `employee_id` and `employee_requested` (whether the customer chose the employee).

See [availability_logic_appendix.md](availability_logic_appendix.md) for the evaluation pipeline.

## 3.3 Other classes
- `TimeSlot.php`: Value object representing a time interval (start and end datetimes).
- `BookingEngine.php`, `MultiSlotEngine.php`, `AvailabilityEngine.php`, `Database.php`, `GoogleSync.php`: Kept for the planned multi-customer version (Google Calendar busy times, SQL storage, multi-slot bookings). Not used by the public booking at the moment.
