# Appendix A: The 3-Tier Availability Evaluation Logic

To guarantee absolute accuracy, prevent double-bookings, and respect both clinic operational hours and national holidays, Book Me Calendar evaluates visitor booking availability through a sequential 3-tier filtering pipeline.

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
[ Generate Candidate Slots ]
(e.g. 09:00 - 17:00 in 15m intervals)
│
▼
┌───────────┐      Overlap with existing booking?
│  Tier 1   │ ─────────────────────────► [ FILTERED OUT ]
└─────┬─────┘
│ Survives
▼
[ FINAL AVAILABLE SLOTS DISPLAYED TO VISITOR ]

```

---

## Tier Breakdown

### Tier 1: Calendar Truth (Base Layer)
* **Role:** Represents the ultimate source of truth regarding schedule occupancy.
* **Mechanism:** Queries cached Google Calendar event data (or local booking records). Each event supplies a busy start and end timestamp. Any candidate slot overlapping with a busy interval is instantly discarded.

### Tier 2: The Holiday Matrix (Regional Mask)
* **Role:** Handles national bank holidays, public closures, and red days (*röda dagar*).
* **Mechanism:** Evaluates the target date against country-specific holiday calendars (e.g., Swedish public holidays). If a match occurs, the entire day returns an empty availability array immediately without checking opening hours.

### Tier 3: Admin Availability Matrix (Operational Layer)
* **Role:** Enforces the clinic's standard working hours configured in the admin dashboard.
* **Mechanism:** Looks up the specific weekday (e.g., Tuesday) to retrieve opening and closing times. Generates granular time windows scaled to the exact duration of the requested service.

