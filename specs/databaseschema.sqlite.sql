CREATE TABLE IF NOT EXISTS settings (
    key_name TEXT PRIMARY KEY,
    value_text TEXT
);

CREATE TABLE IF NOT EXISTS slot_matrix (
    day_of_week INTEGER PRIMARY KEY,
    start_time TEXT NOT NULL,
    end_time TEXT NOT NULL,
    is_active INTEGER DEFAULT 1
);

CREATE TABLE IF NOT EXISTS holiday_matrix (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    holiday_date TEXT NOT NULL,
    description TEXT
);

CREATE TABLE IF NOT EXISTS booking_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_name TEXT NOT NULL,
    client_email TEXT NOT NULL,
    start_datetime TEXT NOT NULL,
    end_datetime TEXT NOT NULL,
    status TEXT DEFAULT 'pending',
    gcal_event_id TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);