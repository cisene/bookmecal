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

...............................................................  63 / 175 ( 36%)
............................................................... 126 / 175 ( 72%)
.................................................               175 / 175 (100%)

Time: 00:02.390, Memory: 32.05 MB

OK (175 tests, 4157 assertions)
```

## 7.3 What the tests cover

* `BookingServiceTest`: Soft slots every 5 minutes around a booking including cleanup, week plan (Monday to Sunday, closed days, busy intervals), no slots in the past, invalid input. With staff: free starts combine the employees' hours and bookings, one employee's plan shows `booked` and `off` time, employees who do not offer the treatment or are inactive are refused, `assign()` picks a free employee (the least booked one when several are free), bookings without an employee block everyone, and without bookable staff the venue is booked. Exclusive bookings block everyone and an exclusive treatment needs the venue to itself; a set runs back to back with one employee offering all its treatments, a broken set cannot be booked. With `avoid_small_gaps`, only starts leaving no short gap are offered and assigned (worked example from §3.1); date exceptions replace the weekly hours; a booking moves only to a free employee offering the treatment, never blocked by itself.
* `ContactCardTest`: Venue card with address, `GEO` and plus code; no card without a name or contact details; employee card only with consent, every profile field mapped (payment and registration numbers left out, photo only when shown); the setting decides which cards are sent; customer text cannot add properties; coordinates and plus codes validated; location for the calendar file.
* `StaffTest`: Bookable and treatment rules, hours from the schedule (none saved = opening hours), hours joined into periods and cut to the opening hours, posted schedules keep only real hours, date exceptions win over the weekly hours, exception ranges are set, removed, joined and pruned.
* `RateLimiterTest`: Limits per client and bucket, sliding window, no IP addresses on disk, IPv6 /64 grouping, counting and clearing failures for the sign-in lockout.
* `FormTokenTest`: Valid, too fast, expired, tampered, foreign key and future tokens.
* `BookingRepositoryTest`: Includes expiry of stale pending requests, the pending count per email, deletion after the retention period, deleting a booking and finding one customer's bookings by email, customer cancellation by status and cutoff (a cancelled booking frees its time).
* `JsonStoreTest`: Read, write, update and delete; a reader running while another process rewrites the file never sees a broken document (needs `pcntl`).
* `InputTest`: Real dates and times, http(s) URLs, email limits, text length in characters, control characters and broken UTF-8 rejected, multiline normalization, strict integers and decimals, phone numbers, IDs that cannot leave a directory.
* `AdminAccountsTest`: `$apr1$` hashes match Apache's `htpasswd`, SHA-crypt and bcrypt verification, upgrade to bcrypt at sign-in, unknown users, username rules, fingerprint changes with the password, password policy.
* `CustomerMessagesTest`: Recipient groups (upcoming from today, by end date, employee and treatment; all; one known address only), one entry per address in any case with the latest name and language, one email per customer with `{name}` filled in and the footer in the customer's language, failures counted, the message history capped at 50.
* `SmtpTransportTest`: The SMTP conversation against a scripted server on a socket pair: sign-in, 8BITMIME, dot doubling, added headers, folded subjects kept and other line breaks removed, refusals reported with the server's answer, credentials never in error messages, STARTTLS required when chosen, no password sent unencrypted to another machine, silent server.
* `EmailNotifierTest`: Long subjects split into encoded words of at most 75 characters. Nothing sent while email is off, bad addresses rejected, header values cannot add headers, receipt in the booking's language with the privacy link, approval as multipart with the `.ics` attachment, rejection without, admin alert and alert address.
* `BookingEmailsTest`: Receipt plus alert on a request (alert in the admin language, links from `public_url`), no alert without an address, the approval's `.ics` holds the booking, nothing sent while off, `public_url` validation, cancellation link in receipt, approval and reminder (not in rejection or admin alerts), cancellation alert, the employee named with the treatment (left out when deleted), approval with both `booking.ics` (with location) and `contact.vcf`.
* `SetupCheckTest`: A fresh install lists every item, a ready setup none; email details only checked while email is on; inactive treatments and closed days do not count.
* `CancelLinkTest`: Token tied to booking and key, strict format, link only with a site address.
* `SecretsTest`: Keys created once and kept, separate per name, too-short keys replaced, other entries kept.
* `CalendarFeedTest`: Feed off without a token, renewing invalidates the old link and keeps other secrets, only blocking statuses included with UTC times and DST, old and malformed bookings left out, escaping keeps customer text from adding properties, line folding at 75 octets without splitting UTF-8 characters, single-event file for the customer without contact details, employee name in the summary.
* `SecurityLogTest`: One JSON line per event, newest first, context values cannot break the line format.
* `LanguageFilesTest`: Key format, `en`/`sv` complete and identical, no unknown keys or broken placeholders in other languages, every key used in PHP/JS exists in `en.json`, English fallback.
* `ResourceProviderTest`: Includes a set's duration, cleanup and exclusivity from its treatments; nested or incomplete sets have no duration.
* Remaining test classes cover the repository, resource provider, localization, email notifier and the classes kept for later (`BookingEngine`, `MultiSlotEngine`, `AvailabilityEngine`, `Database`, `GoogleSync`, `TimeSlot`).
