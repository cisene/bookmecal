<?php
/**
 * src/admin/availability.php
 * Hourly availability matrix (data/booking/availability.json).
 * Note: the public booking page currently uses opening hours (openhours.php), not this matrix.
 */

require_once __DIR__ . '/auth.php';

const MATRIX_DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

$availabilityFile = ResourceProvider::dataPath('booking/availability.json');
$hours = [];
for ($i = 0; $i < 24; $i++) {
    $hours[] = sprintf('%02d:00', $i);
}
$flash = [];

if (is_post()) {
    require_csrf();
    $posted = request_array($_POST, 'slots');
    $matrix = [];
    foreach (MATRIX_DAYS as $index => $day) {
        $daySlots = [];
        foreach ($hours as $hour) {
            $daySlots[$hour] = isset($posted[$day][$hour]) ? 1 : 0;
        }
        $matrix[$day] = ['day_index' => $index + 1, 'slots' => $daySlots, 'updated_by' => $currentAdmin];
    }

    if (JsonStore::write($availabilityFile, $matrix)) {
        log_audit_action($currentAdmin, 'AVAILABILITY_UPDATE', 'Updated weekly availability matrix.');
        header('Location: availability.php?done=saved');
        exit;
    }
    $flash[] = flash_error('Could not write availability.json. Check file permissions.');
}

if (request_string($_GET, 'done') === 'saved') {
    $flash[] = flash_success('admin.slots.done.saved');
}

$stored = JsonStore::read($availabilityFile) ?? [];
$open = [];
foreach (MATRIX_DAYS as $day) {
    foreach ($hours as $hour) {
        $open[$day][$hour] = (int)($stored[$day]['slots'][$hour] ?? 0) === 1;
    }
}

render_admin('availability', [
    'title' => 'admin.slots.title',
    'flash' => $flash,
    'days' => MATRIX_DAYS,
    'hours' => $hours,
    'open' => $open,
]);
