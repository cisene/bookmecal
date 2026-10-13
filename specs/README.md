# Book Me Calendar — System Documentation

A zero-database dependency, file-based PHP booking system featuring multi-user administrative management, rolling audit logging, and secure Google Calendar synchronization.

## 📂 File & Directory Structure

```text
bookmecal/
.
├── phpunit.xml
├── README.md
├── specs
│   ├── 01-architecture-and-config.md
│   ├── 02-storage-and-repository.md
│   ├── 03-business-logic-and-engine.md
│   ├── 04-api-and-integrations.md
│   ├── 05-todo-and-roadmap.md
│   ├── 06-master-specification-index.md
│   ├── 07-tests-and-validation.md
│   ├── availability_logic_appendix.md
│   ├── booking_request.schema.json
│   ├── config.schema.json
│   ├── databaseschema.mysql.sql
│   ├── databaseschema.sql
│   ├── databaseschema.sqlite.sql
│   ├── employee.schema.json
│   ├── holiday_matrix.schema.json
│   ├── holiday.schema.json
│   ├── locale.schema.json
│   ├── README.md
│   ├── schemas_appendix.md
│   ├── services.schema.json
│   ├── settings.schema.json
│   ├── slot_matrix.schema.json
│   └── tokens.schema.json
├── src
│   ├── admin
│   │   ├── auth.php
│   │   ├── availability.php
│   │   ├── config.php
│   │   ├── employees.php
│   │   ├── google_guide.php
│   │   ├── header.php
│   │   ├── holidays.php
│   │   ├── index.php
│   │   ├── openhours.php
│   │   ├── pending.php
│   │   ├── services.php
│   │   ├── setup_google.php
│   │   └── users.php
│   ├── api.php
│   ├── cache
│   ├── classes
│   │   ├── AvailabilityEngine.php
│   │   ├── BookingEngine.php
│   │   ├── BookingRepository.php
│   │   ├── BookingService.php
│   │   ├── Constants.php
│   │   ├── Database.php
│   │   ├── EmailNotifier.php
│   │   ├── GoogleSync.php
│   │   ├── Languages.php
│   │   ├── Localization.php
│   │   ├── MultiSlotEngine.php
│   │   ├── ResourceProvider.php
│   │   └── TimeSlot.php
│   ├── css
│   │   ├── booking_modal.css
│   │   └── calendar_style.css
│   ├── data
│   │   ├── audit.json
│   │   ├── booking
│   │   │   └── availability.json
│   │   ├── config.json
│   │   ├── credentials.json
│   │   ├── employees
│   │   ├── openhours.json
│   │   ├── pending
│   │   ├── services.json
│   │   └── settings.json
│   ├── holidays
│   │   ├── ax.json
│   │   ├── dk.json
│   │   ├── ee.json
│   │   ├── fi.json
│   │   ├── lt.json
│   │   ├── lv.json
│   │   ├── no.json
│   │   └── se.json
│   ├── index.php
│   ├── language
│   │   ├── de.json
│   │   ├── el.json
│   │   ├── en.json
│   │   ├── es.json
│   │   ├── fr.json
│   │   ├── it.json
│   │   ├── ja.json
│   │   ├── ko.json
│   │   ├── pt.json
│   │   ├── sv.json
│   │   ├── th.json
│   │   ├── tl.json
│   │   ├── vi.json
│   │   └── zh-CN.json
│   ├── locale
│   │   └── se.json
│   └── send_reminders.php
└── tests
    ├── AvailabilityEngineTest.php
    ├── BookingEngineTest.php
    ├── BookingRepositoryTest.php
    ├── BookingServiceTest.php
    ├── DatabaseTest.php
    ├── EmailNotifierTest.php
    ├── GoogleSyncTest.php
    ├── LocalizationTest.php
    ├── MultiSlotEngineTest.php
    ├── ResourceProviderTest.php
    └── TimeSlotTest.php


```


## Specifications

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


## Configuration and variable files

| File                                                                  | Description |
| --------------------------------------------------------------------- | ----------- |
| data/booking/availability.json                                        |             |
| data/audit.json                                                       |             |
| data/config.json                                                      |             |
| data/credentials.json                                                 |             |
| data/openhours.json                                                   |             |
| data/services.json                                                    |             |
| data/settings.json                                                    |             |



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
| language/pt.json                                                      | Portugese   |
| language/sv.json                                                      | Swedish    |
| language/th.json                                                      | Thai    |
| language/tl.json                                                      | Tagalog   |
| language/vi.json                                                      | Vietnamese    |
| language/zh-CN.json                                                   | Mandarin   |

