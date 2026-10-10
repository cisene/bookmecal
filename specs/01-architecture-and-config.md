# 1. System Architecture & Configuration

## Overview
Book Me Calendar is a lightweight, object-oriented booking system built for PHP, specifically designed for hosting environments lacking traditional SQL databases by relying on secure, flat-file JSON storage.

## Core Components
- `config.php`: Central configuration for storage directories, timezones, SMTP, and slot durations.
- `Localization.php`: Object-oriented multi-language support (defaulting to Swedish and English) with a backward-compatible global wrapper function `__t()`.
- `cache/.htaccess`: Secures the cache directory against direct web access.
- `data/.htaccess`: Secures JSON data files against direct web access.