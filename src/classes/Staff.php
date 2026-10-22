<?php
/**
 * src/classes/Staff.php
 * Who can be booked: employees that are active and bookable, the treatments each one offers and
 * their weekly hours, with per-date exceptions (vacation, sick day, an extra day) taking precedence.
 * Opening hours are the outer limit, an employee's hours the inner one.
 * Pure functions over employee records (see specs/employee.schema.json), so they test without files.
 */

class Staff {
    public const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /** Display name shown to customers and in the admin pages. */
    public static function name(array $employee): string {
        return trim(($employee['givenName'] ?? '') . ' ' . ($employee['familyName'] ?? ''));
    }

    /** Name of employee $id among $employees, '' when the ID is empty or unknown. */
    public static function nameOf(array $employees, mixed $id): string {
        return is_string($id) && is_array($employees[$id] ?? null) ? self::name($employees[$id]) : '';
    }

    public static function isBookable(array $employee): bool {
        return ($employee['isBookable'] ?? true) !== false && empty($employee['inactive']);
    }

    /**
     * Whether the employee offers a treatment. $serviceIds are the names the treatment goes by
     * (services.json key, sku, id). An employee without a treatment list offers all of them.
     */
    public static function offers(array $employee, array $serviceIds): bool {
        $services = $employee['services'] ?? [];
        if (!is_array($services) || $services === []) {
            return true;
        }
        $services = array_map('strval', $services);
        foreach ($serviceIds as $id) {
            if ($id !== '' && in_array((string)$id, $services, true)) {
                return true;
            }
        }
        return false;
    }

    /** Bookable employees offering the treatment, keyed by ID in the given order. */
    public static function forService(array $employees, array $serviceIds): array {
        return array_filter($employees, fn ($e) => is_array($e) && self::isBookable($e) && self::offers($e, $serviceIds));
    }

    /** Longest date range one exception can cover, in days. */
    public const EXCEPTION_MAX_DAYS = 366;

    /**
     * Whole hours (0-23) the employee works on $weekday, or null when no schedule is saved:
     * the employee then follows the opening hours. With $date (Y-m-d), an exception saved for
     * that date replaces the weekly pattern; an exception without hours is a day off.
     */
    public static function hours(array $employee, string $weekday, ?string $date = null): ?array {
        $exception = $date === null ? null : self::exception($employee, $date);
        if ($exception !== null) {
            return self::parseHours($exception['hours']);
        }
        $schedule = $employee['schedule'] ?? null;
        if (!is_array($schedule)) {
            return null;
        }
        return self::parseHours((array)($schedule[$weekday] ?? []));
    }

    /** The exception saved for $date as ['hours' => ['HH:00', ...], 'note' => string], or null. */
    public static function exception(array $employee, string $date): ?array {
        $exception = is_array($employee['exceptions'] ?? null) ? ($employee['exceptions'][$date] ?? null) : null;
        if (!is_array($exception)) {
            return null;
        }
        return ['hours' => array_values(array_filter((array)($exception['hours'] ?? []), 'is_string')), 'note' => (string)($exception['note'] ?? '')];
    }

    /**
     * Exceptions from $from (Y-m-d) on, joined into ranges of consecutive dates with the same
     * hours and note: [['from', 'to', 'hours' => [int, ...], 'note'], ...], oldest first.
     */
    public static function exceptionRanges(array $employee, string $from): array {
        $exceptions = is_array($employee['exceptions'] ?? null) ? $employee['exceptions'] : [];
        ksort($exceptions);
        $ranges = [];
        foreach (array_keys($exceptions) as $date) {
            $exception = self::exception($employee, (string)$date);
            if ($exception === null || !self::isDate((string)$date) || $date < $from) {
                continue;
            }
            $hours = self::parseHours($exception['hours']);
            $last = count($ranges) - 1;
            $next = $last >= 0 ? (new DateTimeImmutable($ranges[$last]['to']))->modify('+1 day')->format('Y-m-d') : null;
            if ($next === $date && $ranges[$last]['hours'] === $hours && $ranges[$last]['note'] === $exception['note']) {
                $ranges[$last]['to'] = $date;
            } else {
                $ranges[] = ['from' => $date, 'to' => $date, 'hours' => $hours, 'note' => $exception['note']];
            }
        }
        return $ranges;
    }

    /**
     * $exceptions with every date from $from to $to (Y-m-d, inclusive) set to $hours ('HH:00'
     * strings; [] = day off) and $note, or removed when $hours is null. Dates before $today are
     * dropped. Returns null for an invalid or too long range.
     */
    public static function setExceptions(array $exceptions, string $from, string $to, ?array $hours, string $note, string $today): ?array {
        if (!self::isDate($from) || !self::isDate($to) || $to < $from) {
            return null;
        }
        $day = new DateTimeImmutable($from);
        $last = new DateTimeImmutable($to);
        if ($day->diff($last)->days >= self::EXCEPTION_MAX_DAYS) {
            return null;
        }
        for (; $day <= $last; $day = $day->modify('+1 day')) {
            $date = $day->format('Y-m-d');
            if ($hours === null) {
                unset($exceptions[$date]);
            } else {
                $exceptions[$date] = ['hours' => array_values($hours), 'note' => $note];
            }
        }
        $exceptions = array_filter($exceptions, fn ($date) => is_string($date) && $date >= $today, ARRAY_FILTER_USE_KEY);
        ksort($exceptions);
        return $exceptions;
    }

    /** Hours 'HH:00' from $from up to (not including) $to, both 0-24; [] when $to <= $from. */
    public static function hourRange(int $from, int $to): array {
        $hours = [];
        for ($h = max(0, $from); $h < min(24, $to); $h++) {
            $hours[] = sprintf('%02d:00', $h);
        }
        return $hours;
    }

    private static function isDate(string $date): bool {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    /** Sorted unique whole hours from 'HH:00' strings, ignoring anything else. */
    private static function parseHours(array $list): array {
        $hours = [];
        foreach ($list as $hour) {
            if (is_string($hour) && preg_match('/^([01]\d|2[0-3]):00$/', $hour)) {
                $hours[] = (int)substr($hour, 0, 2);
            }
        }
        sort($hours);
        return array_values(array_unique($hours));
    }

    /**
     * Working periods as [[from, to], ...] in minutes after midnight, cut to the opening hours
     * $open-$close. Consecutive hours join into one period. $hours null = the whole opening hours.
     */
    public static function windows(?array $hours, int $open, int $close): array {
        if ($hours === null) {
            return $open < $close ? [[$open, $close]] : [];
        }
        $windows = [];
        foreach ($hours as $hour) {
            $from = max($hour * 60, $open);
            $to = min($hour * 60 + 60, $close);
            if ($from >= $to) {
                continue;
            }
            $last = count($windows) - 1;
            if ($last >= 0 && $windows[$last][1] === $from) {
                $windows[$last][1] = $to;
            } else {
                $windows[] = [$from, $to];
            }
        }
        return $windows;
    }

    /** Schedule as stored: weekday => ['08:00', ...] from posted checkboxes [weekday][HH:00]. */
    public static function scheduleFromPost(array $posted): array {
        $schedule = [];
        foreach (self::DAYS as $day) {
            $schedule[$day] = [];
            for ($h = 0; $h < 24; $h++) {
                $hour = sprintf('%02d:00', $h);
                if (isset($posted[$day][$hour])) {
                    $schedule[$day][] = $hour;
                }
            }
        }
        return $schedule;
    }
}
