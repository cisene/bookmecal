# Book Me Calendar — System Documentation

A zero-database dependency, file-based PHP booking system featuring multi-user administrative management, rolling audit logging, and secure Google Calendar synchronization.

## 📂 File & Directory Structure

```text
src/
├── admin/
│   ├── auth.php              # Shared session bootstrap, CSRF protection, & rolling audit logger
│   ├── header.php            # Unified admin navigation header component
│   ├── pending.php           # Pending booking approvals manager
│   ├── config.php            # Global application settings & storage backend editor
│   ├── availability.php      # Weekly hourly slot availability matrix manager
│   ├── users.php             # Staff account manager (.htpasswd) & rolling audit log viewer
│   └── setup_google.php      # Real Google Calendar identity & OAuth token enrollment wizard
├── data/
│   ├── config.json           # Application preferences, storage backend, & calendar identity
│   ├── tokens.json           # Securely isolated OAuth access & refresh tokens
│   ├── audit.json            # Rolling audit trail log (strictly capped at 200 entries)
│   ├── pending/              # Pending booking requests storage
│   └── booking/
│       └── availability.json # Weekly open/closed hours availability matrix
└── secure/
    └── .htpasswd             # Apache Basic Authentication user credential store