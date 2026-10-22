<?php
/**
 * src/classes/Staff.php
 * Who can be booked: employees that are active and bookable, the treatments each one offers and
 * their weekly hours. Opening hours are the outer limit, an employee's hours the inner one.
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

    /**
     * Whole hours (0-23) the employee works on $weekday, or null when no schedule is saved:
     * the employee then follows the opening hours.
     */
    public static function hours(array $employee, string $weekday): ?array {
        $schedule = $employee['schedule'] ?? null;
        if (!is_array($schedule)) {
            return null;
        }
        $hours = [];
        foreach ((array)($schedule[$weekday] ?? []) as $hour) {
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
