# 1. System Architecture & Configuration

## Overview
Book Me Calendar is a lightweight, object-oriented booking system built for PHP, specifically designed for hosting environments lacking traditional SQL databases by relying on secure, flat-file JSON storage.

The code is split into controllers (PHP that handles input and loads data), templates (HTML only) and classes (logic and storage). PHP files contain no user-facing language; all texts come from the language files (see §1.3).

## 1.1 Request flow
- `src/index.php`: Public booking page controller, renders `templates/public/booking.php`.
- `src/api.php`: JSON API used by the booking page (see [04-api-and-integrations.md](04-api-and-integrations.md)).
- `src/admin/*.php`: Admin controllers. Each requires `admin/auth.php`, handles POST (CSRF-checked, Post/Redirect/Get) and renders a template in `templates/admin/` inside `templates/layout/admin.php`.
- `src/send_reminders.php`: CLI-only cron script for 24-hour reminder emails.
- `src/bootstrap.php`: Shared setup: loads `JsonStore`, `ResourceProvider`, `Localization`, `View`, sets the timezone and defines the helpers `e()` (HTML escaping), `request_string()` and `request_array()`.

## 1.2 Core Components
- `ResourceProvider.php`: Central access to data files (`dataPath()`), settings, services, opening hours, holidays and the configured timezone. All data paths go through `dataPath()` so per-customer data folders can be added later.
- `JsonStore.php`: Locked JSON read/write/update (`flock()`), see [02-storage-and-repository.md](02-storage-and-repository.md).
- `View.php`: Renders plain-PHP templates from `src/templates/` with an optional layout.
- `Localization.php`: Language selection and translation with the global `__t()` helper.
- `EmailNotifier.php`: Confirmation, admin alert and reminder emails (header-injection safe).

## 1.3 Localization
- One flat JSON dictionary per language in `src/language/`, keys namespaced as `area.section.camelCase` (e.g. `admin.employees.familyName`, `booking.timeline.selected`). Placeholders use `{name}`.
- Language order: `?lang=` (remembered in a cookie for one year), the cookie, `config.json` `app.language`, then `en`.
- Missing keys fall back to English per key, then to the key itself.
- `en` and `sv` are complete; other languages may be partial but must only contain keys that exist in `en.json` (enforced by `tests/LanguageFilesTest.php`).
- JavaScript receives its texts through a JSON `<script>` tag (`Localization::exportPrefix('booking.')`). API errors carry a stable `code` (e.g. `slot_unavailable`) that the page maps to `booking.error.slotUnavailable`.
- Dates and weekday names on the booking page are formatted by the browser (`Intl.DateTimeFormat`) using the current language.

## 1.4 Web access protection
- `src/.htaccess`: Turns off `display_errors` and forbids `cache/`, `data/`, `log/`, `classes/`, `templates/` and `bootstrap.php`.
- Repository root `.htaccess`: Only `src/` is reachable when the repository root is the document root.
- `src/admin/`: HTTP Basic Auth (`.htpasswd`); `auth.php` fails closed (401) when no authenticated user is present.
