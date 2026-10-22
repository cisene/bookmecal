# Book Me Calendar — System Documentation

A zero-database dependency, file-based PHP booking system featuring multi-user administrative management, rolling audit logging, and secure Google Calendar synchronization.

## 📂 File & Directory Structure

```text
bookmecal/
├── deploy
│   ├── cron
│   │   └── bookmecal
│   └── fail2ban
│       ├── bookmecal.conf
│       └── bookmecal.local
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
│   ├── 08-deployment.md
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
│   │   ├── login.php
│   │   ├── logout.php
│   │   ├── openhours.php
│   │   ├── pending.php
│   │   ├── services.php
│   │   ├── session.php
│   │   ├── setup_google.php
│   │   └── users.php
│   ├── api.php
│   ├── bootstrap.php
│   ├── calendar.php
│   ├── cancel.php
│   ├── classes
│   │   ├── AdminAccounts.php
│   │   ├── AvailabilityEngine.php
│   │   ├── BookingEmails.php
│   │   ├── BookingEngine.php
│   │   ├── BookingRepository.php
│   │   ├── BookingService.php
│   │   ├── CalendarFeed.php
│   │   ├── CancelLink.php
│   │   ├── Constants.php
│   │   ├── Database.php
│   │   ├── EmailNotifier.php
│   │   ├── EmployeeRepository.php
│   │   ├── FormToken.php
│   │   ├── GoogleSync.php
│   │   ├── Input.php
│   │   ├── JsonStore.php
│   │   ├── Languages.php
│   │   ├── Localization.php
│   │   ├── MultiSlotEngine.php
│   │   ├── RateLimiter.php
│   │   ├── ResourceProvider.php
│   │   ├── Secrets.php
│   │   ├── SecurityHeaders.php
│   │   ├── SecurityLog.php
│   │   ├── TimeSlot.php
│   │   └── View.php
│   ├── css
│   │   ├── admin.css
│   │   ├── booking.css
│   │   ├── booking_modal.css
│   │   └── calendar_style.css
│   ├── data
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
│   │   ├── booking.js
│   │   └── public.js
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
│   ├── privacy.php
│   ├── send_reminders.php
│   └── templates
│       ├── admin
│       │   ├── availability.php
│       │   ├── config.php
│       │   ├── dashboard.php
│       │   ├── employees.php
│       │   ├── google_guide.php
│       │   ├── holidays.php
│       │   ├── login.php
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
│           ├── booking.php
│           ├── cancel.php
│           └── privacy.php
└── tests
    ├── AdminAccountsTest.php
    ├── AvailabilityEngineTest.php
    ├── BookingEmailsTest.php
    ├── BookingEngineTest.php
    ├── BookingRepositoryTest.php
    ├── BookingServiceTest.php
    ├── CalendarFeedTest.php
    ├── CancelLinkTest.php
    ├── DatabaseTest.php
    ├── EmailNotifierTest.php
    ├── FormTokenTest.php
    ├── GoogleSyncTest.php
    ├── InputTest.php
    ├── JsonStoreTest.php
    ├── LanguageFilesTest.php
    ├── LocalizationTest.php
    ├── MultiSlotEngineTest.php
    ├── RateLimiterTest.php
    ├── ResourceProviderTest.php
    ├── SecretsTest.php
    ├── SecurityLogTest.php
    └── TimeSlotTest.php
```


## Specifications

| File                                                                  | Description |
| --------------------------------------------------------------------- | ----------- |
| [01-architecture-and-config.md](01-architecture-and-config.md)        | Request flow, core classes, localization, admin sign-in, security headers, input validation, security log, personal data |
| [02-storage-and-repository.md](02-storage-and-repository.md)          | Data files, locking and BookingRepository |
| [03-business-logic-and-engine.md](03-business-logic-and-engine.md)    | BookingService soft slots and other engines |
| [04-api-and-integrations.md](04-api-and-integrations.md)              | API actions, week plan format, booking page behaviour, booking emails, customer cancellation, calendar feed |
| [05-todo-and-roadmap.md](05-todo-and-roadmap.md)                      | Todo list and revision history |
| [06-master-specification-index.md](06-master-specification-index.md)  | Overview of all specifications |
| [07-tests-and-validation.md](07-tests-and-validation.md)              | Date formats and unit test status |
| [08-deployment.md](08-deployment.md)                                  | Server setup for the trial: permissions, HTTPS, email, cron, fail2ban, backups, updates |
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
| data/settings.json                                                    | Timezone, default country, pending expiry, allowed origins, HTTPS-only admin, retention, business name and privacy contact |
| data/secrets.json                                                     | Form token signing key (generated, not in git) |
| admin/.htpasswd                                                       | Admin accounts (not in git) |
| log/security.log                                                      | Security events, JSON lines (not in git) |



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

