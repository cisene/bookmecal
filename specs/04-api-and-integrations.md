# 4. API & Endpoints

## REST API (`api.php`)
Acts as the central backend endpoint for the frontend client:
- `GET /api.php?action=get_slots&start=...`: Fetches available time slots formatted for calendar consumption.
- `POST /api.php?action=book`: Receives booking payloads and creates pending requests via the repository.
