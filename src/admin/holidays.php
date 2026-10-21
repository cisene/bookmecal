<?php
/**
 * src/admin/holidays.php
 * Holiday dates per country (holidays/{country}.json); bookings are closed on these dates.
 */

require_once __DIR__ . '/auth.php';

const HOLIDAY_COUNTRIES = ['se', 'no', 'fi', 'ee', 'lv', 'lt'];

$country = ResourceProvider::sanitizeCountryCode(request_string($_GET, 'country', ResourceProvider::getSettings()['default_country'] ?? 'se'));
if (!in_array($country, HOLIDAY_COUNTRIES, true)) {
    $country = 'se';
}
$holidayFile = ResourceProvider::holidayPath($country);
$flash = [];

/**
 * Applies $change to the holiday list and stores it sorted by date.
 */
function update_holidays(string $file, string $country, callable $change): bool {
    return JsonStore::update($file, function (array $data) use ($country, $change) {
        $holidays = $change(is_array($data['holidays'] ?? null) ? $data['holidays'] : []);
        usort($holidays, function (array $a, array $b) {
            return strcmp($a['date'] ?? '', $b['date'] ?? '');
        });
        return ['country' => $country, 'holidays' => array_values($holidays)];
    });
}

if (is_post()) {
    require_csrf();
    $action = request_string($_POST, 'action');
    $date = request_string($_POST, 'date');
    $parsed = DateTime::createFromFormat('Y-m-d', $date);

    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        $flash[] = flash_error('Invalid date format. Use YYYY-MM-DD.');
    } elseif ($action === 'add_holiday') {
        $title = request_string($_POST, 'title');
        update_holidays($holidayFile, $country, function (array $holidays) use ($date, $title) {
            // Replace an existing entry for the same date instead of adding a duplicate
            $holidays = array_filter($holidays, function ($h) use ($date) {
                return ($h['date'] ?? '') !== $date;
            });
            $holidays[] = ['date' => $date, 'title' => $title];
            return $holidays;
        });
        log_audit_action($currentAdmin, 'HOLIDAY_ADD', "Added holiday {$date} ({$country})");
        header('Location: holidays.php?country=' . $country . '&done=added');
        exit;
    } elseif ($action === 'delete_holiday') {
        update_holidays($holidayFile, $country, function (array $holidays) use ($date) {
            return array_filter($holidays, function ($h) use ($date) {
                return ($h['date'] ?? '') !== $date;
            });
        });
        log_audit_action($currentAdmin, 'HOLIDAY_DELETE', "Removed holiday {$date} ({$country})");
        header('Location: holidays.php?country=' . $country . '&done=deleted');
        exit;
    }
}

$done = request_string($_GET, 'done');
if (in_array($done, ['added', 'deleted'], true)) {
    $flash[] = flash_success('admin.holidays.done.' . $done);
}

render_admin('holidays', [
    'title' => 'admin.holidays.title',
    'flash' => $flash,
    'country' => $country,
    'countries' => HOLIDAY_COUNTRIES,
    'holidays' => ResourceProvider::getHolidays($country) ?? [],
]);
