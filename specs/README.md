# Book Me Calendar — System Documentation

A zero-database dependency, file-based PHP booking system featuring multi-user administrative management, rolling audit logging, and secure Google Calendar synchronization.

## 📂 File & Directory Structure

```text
bookmecal/
├── phpunit.xml
├── README.md
├── specs
│   ├── 01-architecture-and-config.md
│   ├── 02-storage-and-repository.md
│   ├── 03-business-logic-and-engine.md
│   ├── 04-api-and-integrations.md
│   ├── 05-todo-and-roadmap.md
│   ├── 06-master-specification-index.md
│   ├── 07-tests-and-validation.md
│   ├── availability_logic_appendix.md
│   ├── booking_request.schema.json
│   ├── config.schema.json
│   ├── databaseschema.mysql.sql
│   ├── databaseschema.sql
│   ├── databaseschema.sqlite.sql
│   ├── employee.schema.json
│   ├── holiday_matrix.schema.json
│   ├── holiday.schema.json
│   ├── locale.schema.json
│   ├── README.md
│   ├── schemas_appendix.md
│   ├── services.schema.json
│   ├── settings.schema.json
│   ├── slot_matrix.schema.json
│   └── tokens.schema.json
├── src
│   ├── admin
│   │   ├── auth.php
│   │   ├── availability.php
│   │   ├── config.php
│   │   ├── employees.php
│   │   ├── google_guide.php
│   │   ├── holidays.php
│   │   ├── index.php
│   │   ├── openhours.php
│   │   ├── pending.php
│   │   ├── services.php
│   │   ├── setup_google.php
│   │   └── users.php
│   ├── api.php
│   ├── bootstrap.php
│   ├── classes
│   │   ├── AvailabilityEngine.php
│   │   ├── BookingEngine.php
│   │   ├── BookingRepository.php
│   │   ├── BookingService.php
│   │   ├── Constants.php
│   │   ├── Database.php
│   │   ├── EmailNotifier.php
│   │   ├── EmployeeRepository.php
│   │   ├── GoogleSync.php
│   │   ├── JsonStore.php
│   │   ├── Languages.php
│   │   ├── Localization.php
│   │   ├── MultiSlotEngine.php
│   │   ├── ResourceProvider.php
│   │   ├── TimeSlot.php
│   │   └── View.php
│   ├── css
│   │   ├── admin.css
│   │   ├── booking.css
│   │   ├── booking_modal.css
│   │   └── calendar_style.css
│   ├── data
│   │   ├── audit.json
│   │   ├── booking
│   │   │   └── availability.json
│   │   ├── config.json
│   │   ├── credentials.json
│   │   ├── openhours.json
│   │   ├── services.json
│   │   └── settings.json
│   ├── holidays
│   │   ├── ax.json
│   │   ├── dk.json
│   │   ├── ee.json
│   │   ├── fi.json
│   │   ├── lt.json
│   │   ├── lv.json
│   │   ├── no.json
│   │   └── se.json
│   ├── index.php
│   ├── js
│   │   ├── admin.js
│   │   └── booking.js
│   ├── language
│   │   ├── de.json
│   │   ├── el.json
│   │   ├── en.json
│   │   ├── es.json
│   │   ├── fr.json
│   │   ├── it.json
│   │   ├── ja.json
│   │   ├── ko.json
│   │   ├── pt.json
│   │   ├── sv.json
│   │   ├── th.json
│   │   ├── tl.json
│   │   ├── vi.json
│   │   └── zh-CN.json
│   ├── locale
│   │   └── se.json
│   ├── send_reminders.php
│   └── templates
│       ├── admin
│       │   ├── availability.php
│       │   ├── config.php
│       │   ├── dashboard.php
│       │   ├── employees.php
│       │   ├── google_guide.php
│       │   ├── holidays.php
│       │   ├── openhours.php
│       │   ├── pending.php
│       │   ├── services.php
│       │   ├── setup_google.php
│       │   └── users.php
│       ├── layout
│       │   ├── admin.php
│       │   └── public.php
│       ├── partials
│       │   └── csrf.php
│       └── public
│           └── booking.php
└── tests
    ├── AvailabilityEngineTest.php
    ├── BookingEngineTest.php
    ├── BookingRepositoryTest.php
    ├── BookingServiceTest.php
    ├── DatabaseTest.php
    ├── EmailNotifierTest.php
    ├── GoogleSyncTest.php
    ├── LanguageFilesTest.php
    ├── LocalizationTest.php
    ├── MultiSlotEngineTest.php
    ├── ResourceProviderTest.php
    └── TimeSlotTest.php
```


## Specifications

| File                                                                  | Description |
| --------------------------------------------------------------------- | ----------- |
| [01-architecture-and-config.md](01-architecture-and-config.md)        | Request flow, core classes, localization, web access protection |
| [02-storage-and-repository.md](02-storage-and-repository.md)          | Data files, locking and BookingRepository |
| [03-business-logic-and-engine.md](03-business-logic-and-engine.md)    | BookingService soft slots and other engines |
| [04-api-and-integrations.md](04-api-and-integrations.md)              | API actions, week plan format, booking page behaviour |
| [05-todo-and-roadmap.md](05-todo-and-roadmap.md)                      | Todo list and revision history |
| [06-master-specification-index.md](06-master-specification-index.md)  | Overview of all specifications |
| [07-tests-and-validation.md](07-tests-and-validation.md)              | Date formats and unit test status |
| [availability_logic_appendix.md](availability_logic_appendix.md)      | Availability pipeline (holidays, opening hours, bookings) |
| [schemas_appendix.md](schemas_appendix.md)                            | Holiday and locale schemas |
| [README.md](README.md)                                                | This file |
| [booking_request.schema.json](booking_request.schema.json)            | Booking request (SQL storage model) |
| [config.schema.json](config.schema.json)                              | data/config.json |
| [holiday.schema.json](holiday.schema.json)                            | holidays/<cc>.json |
| [holiday_matrix.schema.json](holiday_matrix.schema.json)              | Holiday matrix (SQL storage model) |
| [locale.schema.json](locale.schema.json)                              | locale/<cc>.json |
| [services.schema.json](services.schema.json)                          | One service in data/services.json |
| [settings.schema.json](settings.schema.json)                          | Settings (SQL storage model) |
| [slot_matrix.schema.json](slot_matrix.schema.json)                    | Slot matrix (SQL storage model) |
| [tokens.schema.json](tokens.schema.json)                              | data/tokens.json (Google OAuth tokens) |
| [databaseschema.mysql.sql](databaseschema.mysql.sql)                  | MySQL schema |
| [databaseschema.sqlite.sql](databaseschema.sqlite.sql)                | SQLite schema |
| [databaseschema.sql](databaseschema.sql)                              | Generic SQL schema |


## Configuration and variable files

| File                                                                  | Description |
| --------------------------------------------------------------------- | ----------- |
| data/booking/availability.json                                        | Admin slot matrix (not used by the booking yet) |
| data/audit.json                                                       | Admin audit log, newest 200 entries |
| data/config.json                                                      | Application configuration |
| data/credentials.json                                                 | Google OAuth client credentials |
| data/openhours.json                                                   | Opening hours per weekday, used by the booking |
| data/services.json                                                    | Treatments keyed by SKU |
| data/settings.json                                                    | Default country and timezones |



## Holiday Calendar files

| File                                                                  | Description                 |
| --------------------------------------------------------------------- | --------------------------- |
| holidays/ax.json                                                      | Åland Holiday Calendar |
| holidays/dk.json                                                      | Denmark Holiday Calendar |
| holidays/ee.json                                                      | Estonia Holiday Calendar |
| holidays/fi.json                                                      | Finland Holiday Calendar |
| holidays/lt.json                                                      | Lithuania Holiday Calendar |
| holidays/lv.json                                                      | Latvia Holiday Calendar |
| holidays/no.json                                                      | Norway Holiday Calendar |
| holidays/se.json                                                      | Swedish Holiday Calendar |



## Language files

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
| language/pt.json                                                      | Portuguese   |
| language/sv.json                                                      | Swedish    |
| language/th.json                                                      | Thai    |
| language/tl.json                                                      | Tagalog   |
| language/vi.json                                                      | Vietnamese    |
| language/zh-CN.json                                                   | Mandarin   |

