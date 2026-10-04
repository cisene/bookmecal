# Book Me Calendar — Systemdokumentation & Specifikation

En lättviktig, objektorienterad PHP-modul för hantering av onlinebokningar, kalendersynkronisering mot Google Calendar och automatiska e-postpåminnelser.

---

## 📂 Filstruktur

```text
.
├── cache/
│   └── .htaccess             # Nekar direktåtkomst till cachefiler via webben
├── admin.php                 # Administrations konsol.
├── api.php                   # Central REST API-endpoint (JSON)
├── booking.php               # Old file
├── booking_calendar.php      # Old file
├── booking_modal.css         # Old file
├── BookingEngine.php         # Old file
├── BookingRepository.php     # Databaslager (DAL) för bokningar och öppettider
├── BookingService.php        # Affärslogik och validering
├── calendar_style.css        # Old file
├── config.php                # Central konfiguration (DB, SMTP, Google API, TTL)
├── credentials.json          # Old file
├── databaseschema.mysql.sql  # Old file
├── databaseschema.sql        # Old file
├── databaseschema.sqlite.sql # Old file
├── db.php                    # Old file
├── gcal_helper.php           # Old file 
├── i18n.php                  # Old file
├── index.html                # Kund-interface 
├── index.php                 # Old file
├── install.php               # Installations script
├── lang/                     # Språk filer
│   └── de.json               # Tyska
│   └── el.json               # Grekiska
│   └── en.json               # Engelska
│   └── es.json               # Spanska
│   └── fr.json               # Franska
│   └── it.json               # Italienska
│   └── ja.json               # Japanska
│   └── ko.json               # Koreanska
│   └── pt.json               # Portugisiska
│   └── sv.json               # Svenska
│   └── th.json               # Thailändska
│   └── tl.json               # Tagalog
│   └── vi.json               # Vietnamesiska
│   └── zh-CN.json            # Mandarin
├── Database.php              # PDO-anslutning (Singleton)
├── GoogleSync.php            # Google Calendar API-integration med cachning
├── Localization.php          # Flerspråksstöd och i18n
├── send_reminders.php        # Cron/CLI-skript för påminnelser
├── lang_switcher.php         # Old file
├── MultiSlotEngine.php       # Old file
├── submit_booking.php        # Old file  
├── TimeSlot.php              # Old file
├── tz_helper.php             # Old file
└── README.md                 # Projektdokumentation och specifikation
```

---

## 📐 Arkitektur & Komponenter

| Fil / Komponent | Beskrivning | Status |
| :--- | :--- | :---: |
| `api.php` | Central REST-endpoint (JSON) med CORS-stöd för frontend-integration. |  Klar |
| `BookingRepository.php` | Databaslager (DAL) för helgdagar, öppettider, tjänster och dubbelbokningskontroll. |  Klar |
| `BookingService.php` | Affärslogik som orkestrerar validering, databaslagring och kalendersynk. |  Klar |
| `cache/.htaccess` | Skyddad katalog för temporära JSON-cachefiler från Google Calendar. |  Klar |
| `config.php` | Central konfigurationsfil för databas, Google API, SMTP och cachtider. |  Klar |
| `Database.php` | Singleton-klass för hantering av databasanslutning via PDO. |  Klar |
| `GoogleSync.php` | Integrationslager mot Google Calendar API v3 med filbaserad cachning och automatisk invalidering. |  Klar |
| `Localization.php` | Flerspråksstöd för översättning av gränssnitt och e-postmallar. |  Klar |
| `send_reminders.php` | CLI/Cron-skript för automatiska påminnelser via e-post inom 24 timmar. |  Klar |

---

## ⚡ Cache-mekanism (`cache/`)

För att minska antalet API-anrop mot Google Calendar och förhindra strypta kvoter (rate limiting) används ett filbaserat cache-lager:
* **Lagring:** Cache-filer sparas som JSON i katalogen `cache/`.
* **Säkerhet:** Katalogen skyddas mot direktåtkomst via `.htaccess` med `Require all denied`.
* **Livslängd (TTL):** Standardtiden sätts via `cache_ttl_minutes` i `config.php` (t.ex. 60 minuter).
* **Invalidering:** När en ny bokning skapas rensas cachen automatiskt så att nästa anrop hämtar färsk data från Google API.

---

## 📝 Checklista & TODO

###  Färdigställt
- [x] Skapa struktur för `config.php` och PDO-anslutning (`Database.php`).
- [x] Implementera flerspråksstöd (`Localization.php`).
- [x] Utveckla databaslager för öppettider, helgdagar och bokningar (`BookingRepository.php`).
- [x] Utveckla Google Calendar API-integration (`GoogleSync.php`).
- [x] Bygga affärslogik med transaktionssäkerhet (`BookingService.php`).
- [x] Skapa CLI/Cron-skript för påminnelser (`send_reminders.php`).
- [x] Utveckla REST API-endpoint (`api.php`).
- [x] Implementera filbaserad cachning med automatisk invalidering och `.htaccess`-skydd för Google API.
- [x] Dokumentera projektets filstruktur i `README.md`.
---

### ⌛ TODO / Kommande steg
- [ ] **Frontend-gränssnitt:** Utveckla kalendervy och bokningsformulär i HTML/JS (eller React/Vue).
- [ ] **Google OAuth Setup:** Sätta upp `client_secret.json` och generera den första `token.json` via OAuth2.
- [ ] **Cron-konfiguration:** Lägga till `send_reminders.php` i serverns crontab (körs varje timme).
- [ ] **Enhetstester (PHPUnit):** Skriva testerna för `BookingService` och `BookingRepository`.
- [ ] **Avbokningsflöde:** Skapa API-endpunkt och e-postlänk för att tillåta kunder att avboka sin tid.
- [ ] Rensa upp gamla filer i projektet.
- [ ] Dokumentation på Svenska för test-installationer.

