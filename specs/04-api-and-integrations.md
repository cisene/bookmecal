# 4. API & Endpoints

## REST API (`src/api.php`)
JSON over HTTP. Every response has `success`. Errors also have a stable `code` (snake_case, translated by the client) and an English `error` for logs and debugging.

| Action | Method | Parameters | Response |
| :--- | :--- | :--- | :--- |
| `get_services` | GET | – | `data`: active services keyed by SKU |
| `check_availability` | GET | `service_id`, `date`, optional `country` | `date`, `slots`: free starts for that day |
| `check_week` | GET | `service_id`, `date` (any day in the week), optional `country` | week plan, see below |
| `book` | POST (JSON body) | `service_id`, `date`, `time`, `customer_name`, `customer_email` | `booking_id` |

### Week plan (`check_week`)
```json
{
  "success": true,
  "week_start": "2026-10-19",
  "step": 5,
  "duration": 45,
  "cleanup": 15,
  "days": [
    {
      "date": "2026-10-22",
      "open": "08:00",
      "close": "17:00",
      "busy": [ { "start": "10:05", "end": "11:05", "kind": "booked" } ],
      "slots": [ { "start_time": "08:00", "end_time": "08:45", "start_iso": "…", "end_iso": "…" } ]
    }
  ]
}
```
- `days` always has 7 entries, Monday first. Closed days and holidays have `open`/`close` set to `null` and no slots.
- `busy.kind` is `booked` (a booking including its cleanup) or `past` (time already passed today, or the whole day for past dates). Busy intervals are clipped to opening hours and never contain customer data.

### Booking
`book` re-checks the requested start under the booking lock. If it is no longer free the API answers `409` with `slot_unavailable`. On success the booking is saved with status `pending`, its `end_time` and the visitor's language.

### Error codes
`missing_fields`, `invalid_country`, `invalid_request`, `method_not_allowed`, `invalid_date`, `invalid_time`, `invalid_email`, `invalid_name`, `unknown_service`, `slot_unavailable` (409), `save_failed` (500), `invalid_action`.

## Booking page (`src/index.php`, `js/booking.js`)
1. The visitor picks a treatment; the page loads the week of the chosen date (today by default) with `check_week`.
2. The week view shows one column per day with time running downwards: green = bookable (from the first to the last free start of a run, until that treatment would end), striped = booked, grey = passed, hatched narrow column = closed. White open time is too short for the chosen treatment.
3. A block as long as the treatment marks the selected time. It is placed by tapping a green area (centred on the tap, snapped to the nearest free start), dragged (also across days), moved with the arrow keys (up/down: previous/next start, left/right: same time on another day), or stepped with the Earlier/Later buttons.
4. Previous/next buttons and a date field move between weeks; weeks before the current one are not reachable.
5. Name and email are entered and the request is sent with `book`.

## Integrations
- Google Calendar: OAuth enrollment wizard in `admin/setup_google.php` (with `state` parameter). Calendar sync is not active yet.
- Email: `EmailNotifier` (PHP `mail()`), texts from the `email.*` language keys in the booking's language.
