# 3. Business Logic & Engine (BookingEngine)

## Responsibilities
The business logic layer is fully decoupled from the storage medium and interface.

## Core Classes
- `TimeSlot.php`: Value object representing a time interval (start and end datetimes).
- `BookingEngine.php`: 
  - Verifies if a given date is a holiday or closed according to the operating schedule.
  - Generates available bookable time windows based on configured slot durations.
  - Filters out occupied slots based on existing active requests.
