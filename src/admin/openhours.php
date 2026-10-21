<?php
/**
 * src/admin/openhours.php
 * Weekly opening hours (data/openhours.json), used by the public booking page.
 */

require_once __DIR__ . '/auth.php';

const WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

$flash = [];

if (is_post()) {
    require_csrf();
    $posted = request_array($_POST, 'hours');
    $hours = [];
    $invalidDays = [];

    foreach (WEEKDAYS as $day) {
        $input = is_array($posted[$day] ?? null) ? $posted[$day] : [];
        $open = Input::time(request_string($input, 'open_time'));
        $close = Input::time(request_string($input, 'close_time'));
        $isOpen = request_string($input, 'is_open') === '1';

        if ($isOpen && ($open === null || $close === null || $open >= $close)) {
            $invalidDays[] = $day;
        }
        $hours[$day] = ['is_open' => $isOpen, 'open_time' => $open, 'close_time' => $close];
    }

    if ($invalidDays) {
        $flash[] = flash_error('Open days need a valid opening time before the closing time (HH:MM): ' . implode(', ', $invalidDays));
    } elseif (JsonStore::write(ResourceProvider::dataPath('openhours.json'), $hours)) {
        log_audit_action($currentAdmin, 'OPENHOURS_UPDATE', 'Updated weekly venue opening hours.');
        header('Location: openhours.php?done=saved');
        exit;
    } else {
        $flash[] = flash_error('Could not write openhours.json. Check file permissions.');
    }
}

if (request_string($_GET, 'done') === 'saved') {
    $flash[] = flash_success('admin.hours.done.saved');
}

$stored = ResourceProvider::getOpenHours() ?? [];
$days = [];
foreach (WEEKDAYS as $day) {
    $days[$day] = [
        'is_open' => !empty($stored[$day]['is_open']),
        'open_time' => (string)($stored[$day]['open_time'] ?? ''),
        'close_time' => (string)($stored[$day]['close_time'] ?? ''),
    ];
}

render_admin('openhours', [
    'title' => 'admin.hours.title',
    'flash' => $flash,
    'days' => $days,
]);
