<?php
require_once 'db.php';
require_once 'i18n.php';
require_once 'tz_helper.php';

// ... booking insertion logic ...

// Send notification to the practitioner/admin using the clinic's preferred language
$adminLang = 'sv'; // Set preferred language for admin alerts

// Force load dictionary for admin language if different from current session
$langFilePath = __DIR__ . "/lang/{$adminLang}.json";
$adminTranslations = json_decode(file_exists($langFilePath) ? file_get_contents($langFilePath) : '{}', true);

/**
 * Helper to translate admin strings specifically
 */
function translateAdmin(string $key, array $replacements = []): string {
    global $adminTranslations;
    $text = $adminTranslations[$key] ?? $key;
    foreach ($replacements as $placeholder => $value) {
        $text = str_replace('{' . $placeholder . '}', $value, $text);
    }
    return $text;
}

// Format booking time for admin display
$formattedTime = formatLocalizedDateTime($utcStart->format('Y-m-d H:i:s'), 'Europe/Stockholm', $adminLang);

// Construct subjects and body using new JSON keys
$subject = translateAdmin('admin_new_booking_subject', [
    'service' => $service['name'] ?? 'Service',
    'name'    => $clientName
]);

$body = translateAdmin('admin_new_booking_body', [
    'name'  => $clientName,
    'email' => $clientEmail,
    'phone' => $clientPhone,
    'time'  => $formattedTime
]);

$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-type: text/plain; charset=UTF-8\r\n";
$headers .= "From: Clinic Booking <noreply@clinic.com>\r\n";

mail('admin@clinic.com', $subject, $body, $headers);