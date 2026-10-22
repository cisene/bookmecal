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

Methods:
- `getAvailableSlots(date, serviceId, country, employeeId = null)`: Free starts for one day as `{start_time, end_time, start_iso, end_iso}`, for anyone (`null`) or one employee. `null` for an invalid date, a service that is unknown, inactive or has no duration, or an employee who cannot be booked for it; `[]` when closed or full.
- `getWeekPlan(date, serviceId, country, employeeId = null)`: Monday-to-Sunday plan for the week containing `date`, used by the booking page's week view (see §4).
- `assign(date, serviceId, time, employeeId = null, country)`: Who takes a booking at `time`: the chosen employee if free; with no choice, the free employee with the fewest bookings that day (ties: the order of the employee list, by family name). Returns `{employee_id, end_time}` or `null` when the time is not free. The `book` API action calls it and saves the booking under the booking lock.
- `staffFor(serviceId)`, `hasStaff()`: Bookable employees offering a treatment; whether any bookable employee exists.

## 3.2 Staff: opening hours outside, employee hours inside
Opening hours are when the venue is open to customers: the **outer limit**. Each employee's weekly hours say when that employee can be booked: the **inner limit**. Several employees can be booked at the same time, so the venue's capacity at any moment is the number of free employees.

`Staff` (pure functions over employee records, see [employee.schema.json](employee.schema.json)):
- **Bookable:** `isBookable` is not `false` and `inactive` is not `true`.
- **Treatments:** `services` lists the treatments (services.json keys) the employee offers. An empty or missing list means all treatments.
- **Hours:** `schedule` maps each weekday to the whole hours worked, e.g. `"monday": ["08:00", "09:00", "13:00"]` (08:00–10:00 and 13:00–14:00). Consecutive hours join into one period, and every period is cut to that day's opening hours, so hours outside them are never bookable. An employee **without** a `schedule` follows the opening hours. The weekly pattern repeats every week; date exceptions (vacation, sick days) are not supported yet.
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
