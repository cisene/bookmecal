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

..........................................................        58 / 58 (100%)

Time: 00:00.072, Memory: 30.05 MB

OK (58 tests, 2346 assertions)
```

## 7.3 What the tests cover

* `BookingServiceTest`: Soft slots every 5 minutes around a booking including cleanup, week plan (Monday to Sunday, closed days, busy intervals), no slots in the past, invalid input.
* `LanguageFilesTest`: Key format, `en`/`sv` complete and identical, no unknown keys or broken placeholders in other languages, every key used in PHP/JS exists in `en.json`, English fallback.
* Remaining test classes cover the repository, resource provider, localization, email notifier and the classes kept for later (`BookingEngine`, `MultiSlotEngine`, `AvailabilityEngine`, `Database`, `GoogleSync`, `TimeSlot`).
