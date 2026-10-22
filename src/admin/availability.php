<?php
/**
 * src/admin/availability.php
 * Weekly bookable hours per employee (the "schedule" in data/employees/<id>.json). Opening hours
 * are the outer limit: hours outside them are shown but never bookable. An employee without a
 * saved schedule follows the opening hours.
 */

require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/classes/EmployeeRepository.php';
require_once dirname(__DIR__) . '/classes/Staff.php';

$repository = new EmployeeRepository();
$employees = $repository->getAll();
$hours = [];
for ($i = 0; $i < 24; $i++) {
    $hours[] = sprintf('%02d:00', $i);
}
$flash = [];

$selected = request_string(is_post() ? $_POST : $_GET, 'employee');
if (!isset($employees[$selected])) {
    $selected = (string)array_key_first($employees);
}

if (is_post() && $selected !== '') {
    require_csrf();
    $action = request_string($_POST, 'action');
    if ($action === 'save_schedule' || $action === 'reset_schedule') {
        $schedule = $action === 'save_schedule' ? Staff::scheduleFromPost(request_array($_POST, 'slots')) : null;
        if ($repository->save($selected, ['schedule' => $schedule], $currentAdmin)) {
            log_audit_action($currentAdmin, 'AVAILABILITY_UPDATE', ($schedule === null ? 'Reset weekly hours to opening hours for: ' : 'Updated weekly hours for: ') . $selected);
            header('Location: availability.php?employee=' . rawurlencode($selected) . '&done=' . ($schedule === null ? 'reset' : 'saved'));
            exit;
        }
        $flash[] = flash_error('Could not save the schedule. Check file permissions in data/employees/.');
    }
}

$done = request_string($_GET, 'done');
if (in_array($done, ['saved', 'reset'], true)) {
    $flash[] = flash_success('admin.slots.done.' . $done);
}

// Opening hours per day in minutes, to grey out hours outside them
$openHours = ResourceProvider::getOpenHours() ?? [];
$openRange = [];
foreach (Staff::DAYS as $day) {
    $d = $openHours[$day] ?? [];
    $from = !empty($d['is_open']) ? DateTime::createFromFormat('H:i', (string)($d['open_time'] ?? '')) : false;
    $to = !empty($d['is_open']) ? DateTime::createFromFormat('H:i', (string)($d['close_time'] ?? '')) : false;
    $openRange[$day] = $from && $to
        ? [(int)$from->format('G') * 60 + (int)$from->format('i'), (int)$to->format('G') * 60 + (int)$to->format('i')]
        : [0, 0];
}

$employee = $employees[$selected] ?? null;
$followsOpening = $employee !== null && !is_array($employee['schedule'] ?? null);
$open = [];
$outside = [];
foreach (Staff::DAYS as $day) {
    [$from, $to] = $openRange[$day];
    $worked = $employee === null ? [] : Staff::hours($employee, $day);
    foreach ($hours as $index => $hour) {
        $outside[$day][$hour] = $index * 60 + 60 <= $from || $index * 60 >= $to;
        // Without a schedule, show the hours the opening hours cover
        $open[$day][$hour] = $worked === null ? !$outside[$day][$hour] : in_array($index, $worked, true);
    }
}

$names = [];
foreach ($employees as $id => $record) {
    $names[$id] = Staff::name($record) . (Staff::isBookable($record) ? '' : ' (' . __t('admin.slots.notBookable') . ')');
}

render_admin('availability', [
    'title' => 'admin.slots.title',
    'flash' => $flash,
    'names' => $names,
    'selected' => $selected,
    'followsOpening' => $followsOpening,
    'days' => Staff::DAYS,
    'hours' => $hours,
    'open' => $open,
    'outside' => $outside,
]);
