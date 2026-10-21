<?php
/**
 * src/index.php
 * Public booking page: pick a service, a date and a free slot, then send a booking request.
 */

require_once __DIR__ . '/bootstrap.php';

$localization = Localization::getInstance();

View::render('public/booking', [
    'lang' => $localization->getCurrentLang(),
    'languages' => $localization->availableLanguages(),
    'today' => (new DateTime('today', ResourceProvider::getTimezone()))->format('Y-m-d'),
    'clientConfig' => [
        'apiUrl' => 'api.php',
        'country' => ResourceProvider::getSettings()['default_country'] ?? 'se',
        'texts' => $localization->exportPrefix('booking.'),
    ],
], 'public');
