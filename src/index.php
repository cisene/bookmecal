<?php
/**
 * src/index.php
 * Public booking page: pick a service, then a free start time in the week view, then send a booking request.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/classes/FormToken.php';

$localization = Localization::getInstance();

View::render('public/booking', [
    'lang' => $localization->getCurrentLang(),
    'languages' => $localization->availableLanguages(),
    'today' => (new DateTime('today', ResourceProvider::getTimezone()))->format('Y-m-d'),
    'clientConfig' => [
        'apiUrl' => 'api.php',
        'locale' => $localization->getCurrentLang(),
        'formToken' => FormToken::issue(),
        'country' => ResourceProvider::getSettings()['default_country'] ?? 'se',
        'texts' => $localization->exportPrefix('booking.'),
    ],
], 'public');
