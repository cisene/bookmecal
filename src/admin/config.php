<?php
/**
 * src/admin/config.php
 * System settings (data/settings.json): timezone, default country, past-slot visibility and
 * how long unanswered booking requests keep their time.
 */

require_once __DIR__ . '/auth.php';

const SUPPORTED_TIMEZONES = ['Europe/Stockholm', 'Europe/Oslo', 'Europe/Helsinki', 'Europe/Tallinn', 'Europe/Riga', 'Europe/Vilnius'];
const SUPPORTED_COUNTRIES = ['se', 'no', 'fi', 'ee', 'lv', 'lt'];
const MAX_PENDING_EXPIRY_HOURS = 720;

$settingsFile = ResourceProvider::dataPath('settings.json');
$flash = [];

if (is_post()) {
    require_csrf();
    $timezone = request_string($_POST, 'timezone');
    $country = strtolower(request_string($_POST, 'default_country'));
    $expiryHours = filter_var(request_string($_POST, 'pending_expiry_hours'), FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 0, 'max_range' => MAX_PENDING_EXPIRY_HOURS],
    ]);

    if (!in_array($timezone, SUPPORTED_TIMEZONES, true)) {
        $flash[] = flash_error('Selected timezone is not supported.');
    } elseif (!in_array($country, SUPPORTED_COUNTRIES, true)) {
        $flash[] = flash_error('Selected country is not supported.');
    } elseif ($expiryHours === false) {
        $flash[] = flash_error('Expiry must be a whole number of hours between 0 and ' . MAX_PENDING_EXPIRY_HOURS . '.');
    } else {
        $saved = JsonStore::update($settingsFile, function (array $settings) use ($timezone, $country, $expiryHours) {
            return array_merge($settings, [
                'timezone' => $timezone,
                'default_country' => $country,
                'supported_timezones' => SUPPORTED_TIMEZONES,
                'show_past_slots' => request_string($_POST, 'show_past_slots') === '1',
                'pending_expiry_hours' => $expiryHours,
            ]);
        });
        if ($saved) {
            log_audit_action($currentAdmin, 'CONFIG_UPDATE', "Updated system configuration (Timezone: {$timezone})");
            header('Location: config.php?done=saved');
            exit;
        }
        $flash[] = flash_error('Could not write settings.json. Check file permissions.');
    }
}

if (request_string($_GET, 'done') === 'saved') {
    $flash[] = flash_success('admin.config.done.saved');
}

$settings = ResourceProvider::getSettings() ?? [];

render_admin('config', [
    'title' => 'admin.config.title',
    'flash' => $flash,
    'timezones' => SUPPORTED_TIMEZONES,
    'countries' => SUPPORTED_COUNTRIES,
    'timezone' => $settings['timezone'] ?? 'Europe/Stockholm',
    'country' => $settings['default_country'] ?? 'se',
    'showPastSlots' => !empty($settings['show_past_slots']),
    'pendingExpiryHours' => ResourceProvider::pendingExpiryHours(),
    'maxPendingExpiryHours' => MAX_PENDING_EXPIRY_HOURS,
]);
