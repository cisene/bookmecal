<?php
/**
 * src/admin/config.php
 * System settings (data/settings.json): timezone, default country, past-slot visibility.
 */

require_once __DIR__ . '/auth.php';

const SUPPORTED_TIMEZONES = ['Europe/Stockholm', 'Europe/Oslo', 'Europe/Helsinki', 'Europe/Tallinn', 'Europe/Riga', 'Europe/Vilnius'];
const SUPPORTED_COUNTRIES = ['se', 'no', 'fi', 'ee', 'lv', 'lt'];

$settingsFile = ResourceProvider::dataPath('settings.json');
$flash = [];

if (is_post()) {
    require_csrf();
    $timezone = request_string($_POST, 'timezone');
    $country = strtolower(request_string($_POST, 'default_country'));

    if (!in_array($timezone, SUPPORTED_TIMEZONES, true)) {
        $flash[] = flash_error('Selected timezone is not supported.');
    } elseif (!in_array($country, SUPPORTED_COUNTRIES, true)) {
        $flash[] = flash_error('Selected country is not supported.');
    } else {
        $saved = JsonStore::update($settingsFile, function (array $settings) use ($timezone, $country) {
            return array_merge($settings, [
                'timezone' => $timezone,
                'default_country' => $country,
                'supported_timezones' => SUPPORTED_TIMEZONES,
                'show_past_slots' => request_string($_POST, 'show_past_slots') === '1',
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
]);
