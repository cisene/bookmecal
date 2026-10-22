<?php
/**
 * src/admin/config.php
 * System settings (data/settings.json): timezone, default country, past-slot visibility,
 * how long unanswered booking requests keep their time, and the privacy settings
 * (business name and contact for the privacy notice, how long bookings are kept),
 * and the calendar subscription link (CalendarFeed).
 */

require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/classes/CalendarFeed.php';

const SUPPORTED_TIMEZONES = ['Europe/Stockholm', 'Europe/Oslo', 'Europe/Helsinki', 'Europe/Tallinn', 'Europe/Riga', 'Europe/Vilnius'];
const SUPPORTED_COUNTRIES = ['se', 'no', 'fi', 'ee', 'lv', 'lt'];
const MAX_PENDING_EXPIRY_HOURS = 720;
const MAX_RETENTION_DAYS = 3650;
const MAX_BUSINESS_NAME = 100;

$settingsFile = ResourceProvider::dataPath('settings.json');
$feed = new CalendarFeed();
$flash = [];

if (is_post() && request_string($_POST, 'feed_action') !== '') {
    require_csrf();
    $feedAction = Input::oneOf(request_string($_POST, 'feed_action'), ['renew', 'disable']);
    if ($feedAction === 'renew' && $feed->renewToken() !== null) {
        log_audit_action($currentAdmin, 'FEED_RENEW', 'Created a new calendar subscription link');
        header('Location: config.php?done=feedRenewed#calendar-feed');
        exit;
    }
    if ($feedAction === 'disable' && $feed->disable()) {
        log_audit_action($currentAdmin, 'FEED_DISABLE', 'Turned the calendar subscription off');
        header('Location: config.php?done=feedDisabled#calendar-feed');
        exit;
    }
    $flash[] = flash_error($feedAction === null ? 'Unknown calendar action.' : 'Could not write secrets.json. Check file permissions.');
} elseif (is_post()) {
    require_csrf();
    $timezone = Input::oneOf(request_string($_POST, 'timezone'), SUPPORTED_TIMEZONES);
    $country = Input::oneOf(strtolower(request_string($_POST, 'default_country')), SUPPORTED_COUNTRIES);
    $expiryHours = Input::int(request_string($_POST, 'pending_expiry_hours'), 0, MAX_PENDING_EXPIRY_HOURS);
    $retentionDays = Input::int(request_string($_POST, 'booking_retention_days'), 0, MAX_RETENTION_DAYS);
    $businessName = Input::text(request_string($_POST, 'business_name'), MAX_BUSINESS_NAME);
    $privacyContact = request_string($_POST, 'privacy_contact');
    $privacyContact = $privacyContact === '' ? '' : Input::email($privacyContact);

    if ($timezone === null) {
        $flash[] = flash_error('Selected timezone is not supported.');
    } elseif ($country === null) {
        $flash[] = flash_error('Selected country is not supported.');
    } elseif ($expiryHours === null) {
        $flash[] = flash_error('Expiry must be a whole number of hours between 0 and ' . MAX_PENDING_EXPIRY_HOURS . '.');
    } elseif ($retentionDays === null) {
        $flash[] = flash_error('Retention must be a whole number of days between 0 and ' . MAX_RETENTION_DAYS . '.');
    } elseif ($businessName === null) {
        $flash[] = flash_error('The business name may have at most ' . MAX_BUSINESS_NAME . ' characters and no control characters.');
    } elseif ($privacyContact === null) {
        $flash[] = flash_error('The privacy contact must be a valid email address or empty.');
    } else {
        $saved = JsonStore::update($settingsFile, function (array $settings) use ($timezone, $country, $expiryHours, $retentionDays, $businessName, $privacyContact) {
            return array_merge($settings, [
                'timezone' => $timezone,
                'default_country' => $country,
                'supported_timezones' => SUPPORTED_TIMEZONES,
                'show_past_slots' => request_string($_POST, 'show_past_slots') === '1',
                'pending_expiry_hours' => $expiryHours,
                'booking_retention_days' => $retentionDays,
                'business_name' => $businessName,
                'privacy_contact' => $privacyContact,
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

$done = Input::oneOf(request_string($_GET, 'done'), ['saved', 'feedRenewed', 'feedDisabled']);
if ($done !== null) {
    $flash[] = flash_success('admin.config.done.' . $done);
}

// Subscription link shown to the admin; calendar.php lives one level above admin/
$feedToken = $feed->token();
$feedUrl = null;
if ($feedToken !== null) {
    $basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/config.php')), '/');
    $feedUrl = (SecurityHeaders::isHttps() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        . $basePath . '/calendar.php?token=' . $feedToken;
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
    'retentionDays' => ResourceProvider::bookingRetentionDays(),
    'maxRetentionDays' => MAX_RETENTION_DAYS,
    'businessName' => (string)($settings['business_name'] ?? ''),
    'privacyContact' => (string)($settings['privacy_contact'] ?? ''),
    'feedUrl' => $feedUrl,
    'feedWebcalUrl' => $feedUrl === null ? null : preg_replace('#^https?://#', 'webcal://', $feedUrl),
    'feedPastDays' => CalendarFeed::PAST_DAYS,
]);
