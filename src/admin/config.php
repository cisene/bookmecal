<?php
/**
 * src/admin/config.php
 * System settings (data/settings.json): timezone, default country, past-slot visibility,
 * how long unanswered booking requests keep their time, and the privacy settings
 * (business name and contact for the privacy notice, how long bookings are kept),
 * email (config.json smtp: on/off, sender, alert address, SMTP server; the SMTP password in
 * secrets.json; settings.json public_url for links) with a test email,
 * the venue's contact details sent as a contact card (settings.json venue, vcard; ContactCard),
 * and the calendar subscription link (CalendarFeed).
 */

require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/classes/CalendarFeed.php';
require_once dirname(__DIR__) . '/classes/BookingEmails.php';
require_once dirname(__DIR__) . '/classes/ContactCard.php';

const SUPPORTED_TIMEZONES = ['Europe/Stockholm', 'Europe/Oslo', 'Europe/Helsinki', 'Europe/Tallinn', 'Europe/Riga', 'Europe/Vilnius'];
const SUPPORTED_COUNTRIES = ['se', 'no', 'fi', 'ee', 'lv', 'lt'];
const MAX_PENDING_EXPIRY_HOURS = 720;
const MAX_RETENTION_DAYS = 3650;
const MAX_CANCEL_CUTOFF_HOURS = 720;
const MAX_BUSINESS_NAME = 100;
const MAX_SENDER_NAME = 100;
const MAX_SMTP_PASSWORD = 200;

$settingsFile = ResourceProvider::dataPath('settings.json');
$configFile = ResourceProvider::dataPath('config.json');
$feed = new CalendarFeed();
$flash = [];

/**
 * Validated venue contact details from the posted form; returns [venue, error message or null].
 */
function venue_from_post(array $post): array {
    $optional = fn (string $key, callable $check) => request_string($post, $key) === '' ? '' : $check(request_string($post, $key));
    $venue = [
        'website' => $optional('website', fn ($v) => BookingEmails::isValidSiteUrl($v) ? $v : null),
        'phone' => $optional('phone', fn ($v) => Input::phone($v)),
        'email' => $optional('email', fn ($v) => Input::email($v)),
        'street' => Input::text(request_string($post, 'street'), 100),
        'postal_code' => Input::text(request_string($post, 'postal_code'), 20),
        'city' => Input::text(request_string($post, 'city'), 100),
        'country' => Input::text(request_string($post, 'country'), 60),
        'latitude' => $optional('latitude', fn ($v) => ContactCard::coordinate($v, 90)),
        'longitude' => $optional('longitude', fn ($v) => ContactCard::coordinate($v, 180)),
        'plus_code' => $optional('plus_code', fn ($v) => ContactCard::plusCode($v)),
    ];
    $invalid = array_keys(array_filter($venue, fn ($v) => $v === null));
    if ($invalid) {
        return [$venue, 'Invalid value in: ' . implode(', ', $invalid) . '. The website must start with http:// or https:// (no query), latitude is -90 to 90 and longitude -180 to 180 in decimal degrees (WGS-84), a plus code looks like 9FFW83PH+X9 or 83PH+X9 Stockholm.'];
    }
    if (($venue['latitude'] === '') !== ($venue['longitude'] === '')) {
        return [$venue, 'Enter both latitude and longitude, or neither.'];
    }
    return [$venue, null];
}

if (is_post() && request_string($_POST, 'test_action') === 'send') {
    require_csrf();
    $to = Input::email(request_string($_POST, 'test_to'));
    $notifier = new EmailNotifier(ResourceProvider::getConfig() ?? []);
    if ($to === null || $to === '') {
        $flash[] = flash_error('Enter a valid email address for the test email.');
    } elseif (!$notifier->isEnabled()) {
        $flash[] = flash_error('Turn on "Send emails" and save before sending a test email.');
    } elseif ($notifier->sendTest($to)) {
        log_audit_action($currentAdmin, 'EMAIL_TEST', 'Sent a test email');
        header('Location: config.php?done=testSent#email');
        exit;
    } else {
        $error = $notifier->lastError();
        $flash[] = flash_error('The test email could not be sent' . ($error !== '' ? ': ' . $error : '. Check the server\'s mail setup (PHP mail()).'));
    }
} elseif (is_post() && request_string($_POST, 'venue_action') === 'save') {
    require_csrf();
    [$venue, $error] = venue_from_post($_POST);
    $vcardMode = Input::oneOf(request_string($_POST, 'vcard'), ContactCard::MODES);
    if ($error !== null || $vcardMode === null) {
        $flash[] = flash_error($error ?? 'Unknown contact card setting.');
    } elseif (JsonStore::update($settingsFile, fn (array $settings) => array_merge($settings, ['venue' => $venue, 'vcard' => $vcardMode]))) {
        log_audit_action($currentAdmin, 'CONFIG_UPDATE', 'Updated venue contact details');
        header('Location: config.php?done=saved#venue');
        exit;
    } else {
        $flash[] = flash_error('Could not write settings.json. Check file permissions.');
    }
} elseif (is_post() && request_string($_POST, 'feed_action') !== '') {
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
    $cancelCutoff = Input::int(request_string($_POST, 'cancel_cutoff_hours'), 0, MAX_CANCEL_CUTOFF_HOURS);
    $businessName = Input::text(request_string($_POST, 'business_name'), MAX_BUSINESS_NAME);
    $privacyContact = request_string($_POST, 'privacy_contact');
    $privacyContact = $privacyContact === '' ? '' : Input::email($privacyContact);
    $emailEnabled = request_string($_POST, 'email_enabled') === '1';
    $fromEmail = Input::email(request_string($_POST, 'from_email'));
    $fromName = Input::text(request_string($_POST, 'from_name'), MAX_SENDER_NAME);
    $notificationEmail = request_string($_POST, 'notification_email');
    $notificationEmail = $notificationEmail === '' ? '' : Input::email($notificationEmail);
    $publicUrl = request_string($_POST, 'public_url');
    $publicUrl = $publicUrl === '' || BookingEmails::isValidSiteUrl($publicUrl) ? $publicUrl : null;
    // SMTP server; empty host = PHP mail(). An empty password field keeps the saved password
    $smtpHost = strtolower(request_string($_POST, 'smtp_host'));
    $smtpHost = $smtpHost === '' || (strlen($smtpHost) <= 253 && preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$|^\[?[0-9a-f:.]+\]?$/', $smtpHost)) ? $smtpHost : null;
    $smtpEncryption = Input::oneOf(request_string($_POST, 'smtp_encryption'), SmtpTransport::ENCRYPTIONS);
    $smtpPort = request_string($_POST, 'smtp_port') === '' ? ($smtpEncryption === 'ssl' ? 465 : 587) : Input::int(request_string($_POST, 'smtp_port'), 1, 65535);
    $smtpUsername = Input::text(request_string($_POST, 'smtp_username'), Input::EMAIL_MAX);
    $smtpPassword = (string)($_POST['smtp_password'] ?? '');
    $smtpPasswordOk = strlen($smtpPassword) <= MAX_SMTP_PASSWORD && !preg_match('/[\x00-\x1F\x7F]/', $smtpPassword);

    if ($timezone === null) {
        $flash[] = flash_error('Selected timezone is not supported.');
    } elseif ($country === null) {
        $flash[] = flash_error('Selected country is not supported.');
    } elseif ($expiryHours === null) {
        $flash[] = flash_error('Expiry must be a whole number of hours between 0 and ' . MAX_PENDING_EXPIRY_HOURS . '.');
    } elseif ($cancelCutoff === null) {
        $flash[] = flash_error('The cancellation limit must be a whole number of hours between 0 and ' . MAX_CANCEL_CUTOFF_HOURS . '.');
    } elseif ($retentionDays === null) {
        $flash[] = flash_error('Retention must be a whole number of days between 0 and ' . MAX_RETENTION_DAYS . '.');
    } elseif ($businessName === null) {
        $flash[] = flash_error('The business name may have at most ' . MAX_BUSINESS_NAME . ' characters and no control characters.');
    } elseif ($privacyContact === null) {
        $flash[] = flash_error('The privacy contact must be a valid email address or empty.');
    } elseif ($fromEmail === null) {
        $flash[] = flash_error('The sender address must be a valid email address.');
    } elseif ($fromName === null || $fromName === '') {
        $flash[] = flash_error('The sender name is required and may have at most ' . MAX_SENDER_NAME . ' characters.');
    } elseif ($notificationEmail === null) {
        $flash[] = flash_error('The address for new booking alerts must be a valid email address or empty.');
    } elseif ($publicUrl === null) {
        $flash[] = flash_error('The booking site address must start with http:// or https:// and have no query or fragment.');
    } elseif ($smtpHost === null || $smtpEncryption === null || $smtpPort === null || $smtpUsername === null || !$smtpPasswordOk) {
        $flash[] = flash_error('Check the SMTP settings: the server is a host name such as smtp.example.com, the port is 1-65535, the user name and password have no line breaks (password at most ' . MAX_SMTP_PASSWORD . ' characters).');
    } elseif ($smtpHost !== '' && $smtpEncryption === 'none' && $smtpUsername !== '' && !SmtpTransport::isLocalHost($smtpHost)) {
        $flash[] = flash_error('A password is never sent unencrypted: choose STARTTLS or SSL/TLS for this SMTP server.');
    } else {
        $saved = JsonStore::update($settingsFile, function (array $settings) use ($timezone, $country, $expiryHours, $retentionDays, $businessName, $privacyContact, $publicUrl, $cancelCutoff) {
            return array_merge($settings, [
                'timezone' => $timezone,
                'default_country' => $country,
                'supported_timezones' => SUPPORTED_TIMEZONES,
                'show_past_slots' => request_string($_POST, 'show_past_slots') === '1',
                'pending_expiry_hours' => $expiryHours,
                'cancel_cutoff_hours' => $cancelCutoff,
                'booking_retention_days' => $retentionDays,
                'business_name' => $businessName,
                'privacy_contact' => $privacyContact,
                'public_url' => $publicUrl,
            ]);
        });
        $saved = $saved && JsonStore::update($configFile, function (array $config) use ($emailEnabled, $fromEmail, $fromName, $notificationEmail, $smtpHost, $smtpPort, $smtpEncryption, $smtpUsername) {
            $config['smtp'] = array_merge($config['smtp'] ?? [], [
                'enabled' => $emailEnabled,
                'from_email' => $fromEmail,
                'from_name' => $fromName,
                'notification_email' => $notificationEmail,
                'host' => $smtpHost,
                'port' => $smtpPort,
                'encryption' => $smtpEncryption,
                'username' => $smtpUsername,
            ]);
            return $config;
        });
        // The password stays out of config.json, which is tracked in git
        if ($saved && request_string($_POST, 'smtp_password_clear') === '1') {
            $saved = Secrets::store('smtp_password', '');
        } elseif ($saved && $smtpPassword !== '') {
            $saved = Secrets::store('smtp_password', $smtpPassword);
        }
        if ($saved) {
            log_audit_action($currentAdmin, 'CONFIG_UPDATE', "Updated system configuration (Timezone: {$timezone})");
            header('Location: config.php?done=saved');
            exit;
        }
        $flash[] = flash_error('Could not write settings.json or config.json. Check file permissions.');
    }
}

$done = Input::oneOf(request_string($_GET, 'done'), ['saved', 'feedRenewed', 'feedDisabled', 'testSent']);
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
$smtp = (ResourceProvider::getConfig() ?? [])['smtp'] ?? [];

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
    'cancelCutoffHours' => ResourceProvider::cancelCutoffHours(),
    'maxCancelCutoffHours' => MAX_CANCEL_CUTOFF_HOURS,
    'retentionDays' => ResourceProvider::bookingRetentionDays(),
    'maxRetentionDays' => MAX_RETENTION_DAYS,
    'businessName' => (string)($settings['business_name'] ?? ''),
    'privacyContact' => (string)($settings['privacy_contact'] ?? ''),
    'emailEnabled' => ($smtp['enabled'] ?? false) === true,
    'fromEmail' => (string)($smtp['from_email'] ?? ''),
    'fromName' => (string)($smtp['from_name'] ?? ''),
    'notificationEmail' => (string)($smtp['notification_email'] ?? ''),
    'smtpHost' => (string)($smtp['host'] ?? ''),
    'smtpPort' => (int)($smtp['port'] ?? 587),
    'smtpEncryption' => in_array($smtp['encryption'] ?? '', SmtpTransport::ENCRYPTIONS, true) ? $smtp['encryption'] : 'starttls',
    'smtpEncryptions' => SmtpTransport::ENCRYPTIONS,
    'smtpUsername' => (string)($smtp['username'] ?? ''),
    'smtpPasswordSaved' => Secrets::value('smtp_password') !== '',
    'adminEmail' => (string)($smtp['notification_email'] ?? ''),
    'publicUrl' => (string)($settings['public_url'] ?? ''),
    'venue' => array_map('strval', is_array($settings['venue'] ?? null) ? $settings['venue'] : []),
    'vcardMode' => in_array($settings['vcard'] ?? '', ContactCard::MODES, true) ? $settings['vcard'] : 'off',
    'vcardModes' => ContactCard::MODES,
    'feedUrl' => $feedUrl,
    'feedWebcalUrl' => $feedUrl === null ? null : preg_replace('#^https?://#', 'webcal://', $feedUrl),
    'feedPastDays' => CalendarFeed::PAST_DAYS,
]);
