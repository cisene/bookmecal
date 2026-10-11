# Book Me Calendar — System Documentation

A zero-database dependency, file-based PHP booking system featuring multi-user administrative management, rolling audit logging, and secure Google Calendar synchronization.

## 📂 File & Directory Structure

```text
specs/
├── 01-architecture-and-config.md
├── 02-storage-and-repository.md
├── 03-business-logic-and-engine.md
├── 04-api-and-integrations.md
├── 05-todo-and-roadmap.md
├── 06-master-specification-index.md
├── availability_logic_appendix.md
├── schemas_appendix.md
├── README.md
├── booking_request.schema.json
├── config.schema.json
├── holiday.schema.json
├── holiday_matrix.schema.json
├── locale.schema.json
├── services.schema.json
├── settings.schema.json
├── slot_matrix.schema.json
├── tokens.schema.json
├── databaseschema.mysql.sql
├── databaseschema.sqlite.sql
├── databaseschema.sql
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


```


| File                                                                  | Description |
| --------------------------------------------------------------------- | ----------- |
| [01-architecture-and-config.md](01-architecture-and-config.md)        |             |
| [02-storage-and-repository.md](02-storage-and-repository.md)          |             |
| [03-business-logic-and-engine.md](03-business-logic-and-engine.md)    |             |
| [04-api-and-integrations.md](04-api-and-integrations.md)              |             |
| [05-todo-and-roadmap.md](05-todo-and-roadmap.md)                      |             |
| [06-master-specification-index.md](06-master-specification-index.md)  |             |
| [availability_logic_appendix.md](availability_logic_appendix.md)      |             |
| [schemas_appendix.md](schemas_appendix.md)                            |             |
| [README.md](README.md)                                                |             |
| [booking_request.schema.json](booking_request.schema.json)            |             |
| [config.schema.json](config.schema.json)                              |             |
| [holiday.schema.json](holiday.schema.json)                            |             |
| [holiday_matrix.schema.json](holiday_matrix.schema.json)              |             |
| [locale.schema.json](locale.schema.json)                              |             |
| [services.schema.json](services.schema.json)                          |             |
| [settings.schema.json](settings.schema.json)                          |             |
| [slot_matrix.schema.json](slot_matrix.schema.json)                    |             |
| [tokens.schema.json](tokens.schema.json)                              |             |
| [databaseschema.mysql.sql](databaseschema.mysql.sql)                  |             |
| [databaseschema.sqlite.sql](databaseschema.sqlite.sql)                |             |
| [databaseschema.sql](databaseschema.sql)                              |             |



| File                                                                  | Description |
| --------------------------------------------------------------------- | ----------- |
| data/booking/availability.json                                        |             |
| data/audit.json                                                       |             |
| data/config.json                                                      |             |
| data/credentials.json                                                 |             |
| data/openhours.json                                                   |             |
| data/services.json                                                    |             |
| data/settings.json                                                    |             |




| File                                                                  | Description                 |
| --------------------------------------------------------------------- | --------------------------- |
| holidays/se.json                                                      | Swedish Holiday information |


| File                                                                  | Description                 |
| --------------------------------------------------------------------- | --------------------------- |
| language/de.json                                                      | German       |
| language/el.json                                                      | Greek     |
| language/en.json                                                      | English    |
| language/es.json                                                      | Spanish     |
| language/fr.json                                                      | French    |
| language/it.json                                                      | Italian   |
| language/ja.json                                                      | Japanese     |
| language/ko.json                                                      | Korean   |
| language/pt.json                                                      | Portugese   |
| language/sv.json                                                      | Swedish    |
| language/th.json                                                      | Thai    |
| language/tl.json                                                      | Tagalog   |
| language/vi.json                                                      | Vietnamese    |
| language/zh-CN.json                                                   | Mandarin   |

