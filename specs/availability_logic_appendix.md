# Appendix A: The 4-Tier Availability Evaluation Logic

To guarantee absolute accuracy, prevent double-bookings, and respect national holidays, opening hours and each employee's working hours, Book Me Calendar evaluates visitor booking availability through a sequential 4-tier filtering pipeline. Implemented in `BookingService` (see [03-business-logic-and-engine.md](03-business-logic-and-engine.md)).

## Pipeline Overview


```

[Requested Date & Service]
│
▼
┌───────────┐      Holiday / Red Day?
│  Tier 2   │ ─────────────────────────► [ CLOSED ]
└─────┬─────┘                              (No Slots)
│ No
▼
┌───────────┐      Closed Day / Off?
│  Tier 3   │ ─────────────────────────► [ CLOSED ]
└─────┬─────┘                              (No Slots)
│ Open
▼
[ Generate Candidate Starts ]
(every SLOT_STEP = 5 minutes from opening time,
 while start + duration <= closing time)
│
▼
┌───────────┐      for every employee offering the treatment
│  Tier 4   │      (or the chosen one): does the treatment fit
│           │      in their hours (cut to opening hours)?
└─────┬─────┘
│
▼
┌───────────┐      start + duration + cleanup overlaps one of
│  Tier 1   │      that employee's bookings (incl. cleanup),
│           │      or start is in the past?
│           │      No employee left ─────► [ FILTERED OUT ]
└─────┬─────┘
│ Survives
▼
[ FREE STARTS SHOWN IN THE WEEK VIEW ]

```

---

## Tier Breakdown

### Tier 1: Calendar Truth (Base Layer)
* **Role:** Represents the ultimate source of truth regarding schedule occupancy.
* **Mechanism:** Local booking records with status `pending`, `approved` or `confirmed` each supply a busy interval from start to end of treatment plus the booked service's cleanup, for the employee in its `employee_id` (records without one block every employee). A start is free for an employee when its own treatment plus cleanup overlaps none of that employee's busy intervals, and free for the customer when at least one employee is free. Google Calendar busy times are planned to join this tier.
* **Double-booking guard:** The `book` API action repeats this check, picks the employee and saves the booking while holding an exclusive lock, so the result cannot change between check and save.

### Tier 2: The Holiday Matrix (Regional Mask)
* **Role:** Handles national bank holidays, public closures, and red days (*röda dagar*).
* **Mechanism:** Evaluates the target date against country-specific holiday calendars (`src/holidays/<cc>.json`). If a match occurs, the entire day returns an empty availability array immediately without checking opening hours.

### Tier 3: Opening Hours (Operational Layer)
* **Role:** Enforces the clinic's standard working hours configured in the admin dashboard (Opening hours page, `data/openhours.json`).
* **Mechanism:** Looks up the specific weekday to retrieve opening and closing times. Candidate starts are generated in fixed steps of `SLOT_STEP` minutes (soft slots), not in blocks of the service's duration, so treatments of different lengths can fill the day without forced gaps.

### Tier 4: Employee hours (Staff Layer)
* **Role:** Opening hours are the outer limit, each employee's weekly hours the inner one ([03-business-logic-and-engine.md](03-business-logic-and-engine.md) §3.2).
* **Mechanism:** Only bookable employees offering the treatment count (or only the employee the customer chose). Each one's hours for the weekday are joined into periods and cut to the opening hours; the whole treatment must lie inside one period. An employee without saved hours follows the opening hours. Without any bookable employee this tier is skipped and the venue is the one resource.

## Soft slots and fragmentation
With a 5-minute step a booking can leave gaps that are too short for any treatment (for example 09:50–10:05 before a 10:05 booking when the treatment takes 45 + 15 minutes). The week view shows such time as white (open but not bookable for the chosen treatment). A future option is to prefer starts that sit directly before or after existing bookings.
