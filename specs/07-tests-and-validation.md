# 7. Tests and validation


## 7.1 Dates

* Dates should all be ISO-8601 such as '2026-10-13'
* Times should all be ISO-8601 such as '21:40:33+0100'
* Timestamps should all be ISO-8601 such as '2026-10-13T21:40:33+0100'




## 7.2 PHP Class Unit tests

Run `phpunit` from the repository root.

```
PHPUnit 12.5.39 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /media/chrise/work/repos/github/cisene/bookmecal/phpunit.xml

................................................................. 65 / 95 ( 68%)
..............................                                    95 / 95 (100%)

Time: 00:02.250, Memory: 30.05 MB

OK (95 tests, 2835 assertions)
```

## 7.3 What the tests cover

* `BookingServiceTest`: Soft slots every 5 minutes around a booking including cleanup, week plan (Monday to Sunday, closed days, busy intervals), no slots in the past, invalid input.
* `RateLimiterTest`: Limits per client and bucket, sliding window, no IP addresses on disk, IPv6 /64 grouping, counting and clearing failures for the sign-in lockout.
* `FormTokenTest`: Valid, too fast, expired, tampered, foreign key and future tokens.
* `BookingRepositoryTest`: Includes expiry of stale pending requests, the pending count per email, deletion after the retention period, deleting a booking and finding one customer's bookings by email.
* `JsonStoreTest`: Read, write, update and delete; a reader running while another process rewrites the file never sees a broken document (needs `pcntl`).
* `InputTest`: Real dates and times, email limits, text length in characters, control characters and broken UTF-8 rejected, multiline normalization, strict integers and decimals, phone numbers, IDs that cannot leave a directory.
* `AdminAccountsTest`: `$apr1$` hashes match Apache's `htpasswd`, SHA-crypt and bcrypt verification, upgrade to bcrypt at sign-in, unknown users, username rules, fingerprint changes with the password, password policy.
* `SecurityLogTest`: One JSON line per event, newest first, context values cannot break the line format.
* `LanguageFilesTest`: Key format, `en`/`sv` complete and identical, no unknown keys or broken placeholders in other languages, every key used in PHP/JS exists in `en.json`, English fallback.
* Remaining test classes cover the repository, resource provider, localization, email notifier and the classes kept for later (`BookingEngine`, `MultiSlotEngine`, `AvailabilityEngine`, `Database`, `GoogleSync`, `TimeSlot`).
