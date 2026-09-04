<?php
// tz_helper.php - Time Zone & Localization Utilities

/**
 * Formats a UTC string into the client's local time zone and language format
 */
function formatLocalizedDateTime(string $utcString, string $targetTz = 'Europe/Stockholm', string $lang = 'sv'): string {
    $date = new DateTime($utcString, new DateTimeZone('UTC'));
    
    try {
        $date->setTimezone(new DateTimeZone($targetTz));
    } catch (Exception $e) {
        $date->setTimezone(new DateTimeZone('Europe/Stockholm'));
    }

    $locales = [
        'sv' => 'sv_SE',
        'en' => 'en_US',
        'de' => 'de_DE',
        'fr' => 'fr_FR',
        'es' => 'es_ES',
        'th' => 'th_TH',
        'vi' => 'vi_VN'
    ];

    $locale = $locales[$lang] ?? 'sv_SE';

    if (class_exists('IntlDateFormatter')) {
        $formatter = new IntlDateFormatter(
            $locale,
            IntlDateFormatter::FULL,
            IntlDateFormatter::SHORT,
            $targetTz
        );
        return $formatter->format($date);
    }

    return $date->format('Y-m-d H:i') . ' (' . $targetTz . ')';
}