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

...............................................................  63 / 124 ( 50%)
.............................................................   124 / 124 (100%)

Time: 00:02.211, Memory: 30.05 MB

OK (124 tests, 3157 assertions)
```

## 7.3 What the tests cover

* `BookingServiceTest`: Soft slots every 5 minutes around a booking including cleanup, week plan (Monday to Sunday, closed days, busy intervals), no slots in the past, invalid input.
* `RateLimiterTest`: Limits per client and bucket, sliding window, no IP addresses on disk, IPv6 /64 grouping, counting and clearing failures for the sign-in lockout.
* `FormTokenTest`: Valid, too fast, expired, tampered, foreign key and future tokens.
* `BookingRepositoryTest`: Includes expiry of stale pending requests, the pending count per email, deletion after the retention period, deleting a booking and finding one customer's bookings by email, customer cancellation by status and cutoff (a cancelled booking frees its time).
* `JsonStoreTest`: Read, write, update and delete; a reader running while another process rewrites the file never sees a broken document (needs `pcntl`).
* `InputTest`: Real dates and times, email limits, text length in characters, control characters and broken UTF-8 rejected, multiline normalization, strict integers and decimals, phone numbers, IDs that cannot leave a directory.
* `AdminAccountsTest`: `$apr1$` hashes match Apache's `htpasswd`, SHA-crypt and bcrypt verification, upgrade to bcrypt at sign-in, unknown users, username rules, fingerprint changes with the password, password policy.
* `EmailNotifierTest`: Nothing sent while email is off, bad addresses rejected, header values cannot add headers, receipt in the booking's language with the privacy link, approval as multipart with the `.ics` attachment, rejection without, admin alert and alert address.
* `BookingEmailsTest`: Receipt plus alert on a request (alert in the admin language, links from `public_url`), no alert without an address, the approval's `.ics` holds the booking, nothing sent while off, `public_url` validation, cancellation link in receipt, approval and reminder (not in rejection or admin alerts), cancellation alert.
* `SetupCheckTest`: A fresh install lists every item, a ready setup none; email details only checked while email is on; inactive treatments and closed days do not count.
* `CancelLinkTest`: Token tied to booking and key, strict format, link only with a site address.
* `SecretsTest`: Keys created once and kept, separate per name, too-short keys replaced, other entries kept.
* `CalendarFeedTest`: Feed off without a token, renewing invalidates the old link and keeps other secrets, only blocking statuses included with UTC times and DST, old and malformed bookings left out, escaping keeps customer text from adding properties, line folding at 75 octets without splitting UTF-8 characters, single-event file for the customer without contact details.
* `SecurityLogTest`: One JSON line per event, newest first, context values cannot break the line format.
* `LanguageFilesTest`: Key format, `en`/`sv` complete and identical, no unknown keys or broken placeholders in other languages, every key used in PHP/JS exists in `en.json`, English fallback.
* Remaining test classes cover the repository, resource provider, localization, email notifier and the classes kept for later (`BookingEngine`, `MultiSlotEngine`, `AvailabilityEngine`, `Database`, `GoogleSync`, `TimeSlot`).
