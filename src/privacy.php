<?php
/**
 * src/privacy.php
 * Public privacy notice: what the booking stores, why, for how long and how to ask for it to be deleted.
 * Business name, contact and retention come from settings.json (admin Config page).
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/classes/CalendarFeed.php';

$localization = Localization::getInstance();
$settings = ResourceProvider::getSettings() ?? [];
$businessName = trim((string)($settings['business_name'] ?? ''));
$contact = trim((string)($settings['privacy_contact'] ?? ''));

View::render('public/privacy', [
    'lang' => $localization->getCurrentLang(),
    'languages' => $localization->availableLanguages(),
    'business' => $businessName !== '' ? $businessName : __t('privacy.businessFallback'),
    'contact' => Input::email($contact),
    'retentionDays' => ResourceProvider::bookingRetentionDays(),
    // With the calendar subscription on, the calendar provider keeps a copy of the bookings
    'calendarFeed' => (new CalendarFeed())->token() !== null,
], 'public');
