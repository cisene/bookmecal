<?php
// gcal_helper.php - Complete Google Calendar Integration Helper

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Initializes and returns an authenticated Google API Client.
 *
 * @return Google\Client
 * @throws Exception If credentials file is missing or invalid.
 */
function getGoogleCalendarClient(): Google\Client {
    $config = require __DIR__ . '/config.php';
    $gconfig = $config['google_calendar'];

    $client = new Google\Client();
    $client->setApplicationName($gconfig['application_name']);
    $client->setScopes($gconfig['scopes']);

    // Load OAuth Client Credentials from configured path
    if (file_exists($gconfig['credentials_file'])) {
        $client->setAuthConfig($gconfig['credentials_file']);
    } else {
        throw new Exception("Google Credentials file not found at: " . $gconfig['credentials_file']);
    }

    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    // Set redirect URI for authorization flow
    if (!empty($gconfig['redirect_uri'])) {
        $client->setRedirectUri($gconfig['redirect_uri']);
    }

    // Load saved access token if present
    if (file_exists($gconfig['token_file'])) {
        $accessToken = json_decode(file_get_contents($gconfig['token_file']), true);
        if ($accessToken) {
            $client->setAccessToken($accessToken);
        }
    }

    // Automatically refresh token if expired and refresh token exists
    if ($client->isAccessTokenExpired()) {
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            
            // Save refreshed token back to disk
            $newToken = $client->getAccessToken();
            file_put_contents($gconfig['token_file'], json_encode($newToken));
        }
    }

    return $client;
}

/**
 * Creates a new appointment event in Google Calendar.
 *
 * @param array $booking Array containing client_name, client_email, client_phone, service_name, start_datetime, duration_minutes.
 * @return string The created Google Calendar Event ID.
 */
function createGoogleCalendarEvent(array $booking): string {
    $config = require __DIR__ . '/config.php';
    $calendarId = $config['google_calendar']['calendar_id'];

    $client = getGoogleCalendarClient();
    $service = new Google\Service\Calendar($client);

    // Calculate end time based on service duration
    $startDT = new DateTime($booking['start_datetime'], new DateTimeZone('Europe/Stockholm'));
    $endDT   = (clone $startDT)->modify("+{$booking['duration_minutes']} minutes");

    $event = new Google\Service\Calendar\Event([
        'summary'     => $booking['service_name'] . ' - ' . $booking['client_name'],
        'description' => "Booking details:\n" .
                         "Client: " . $booking['client_name'] . "\n" .
                         "Email: "  . $booking['client_email'] . "\n" .
                         "Phone: "  . $booking['client_phone'],
        'start' => [
            'dateTime' => $startDT->format(DateTime::RFC3339),
            'timeZone' => 'Europe/Stockholm',
        ],
        'end' => [
            'dateTime' => $endDT->format(DateTime::RFC3339),
            'timeZone' => 'Europe/Stockholm',
        ],
        'attendees' => [
            ['email' => $booking['client_email']]
        ],
        'reminders' => [
            'useDefault' => false,
            'overrides'  => [
                ['method' => 'email', 'minutes' => 24 * 60],
                ['method' => 'popup', 'minutes' => 60],
            ],
        ],
    ]);

    $createdEvent = $service->events->insert($calendarId, $event);
    return $createdEvent->getId();
}

/**
 * Updates an existing Google Calendar event.
 *
 * @param string $eventId The Google Calendar Event ID to update.
 * @param array  $booking Updated booking data.
 * @return bool True on success.
 */
function updateGoogleCalendarEvent(string $eventId, array $booking): bool {
    $config = require __DIR__ . '/config.php';
    $calendarId = $config['google_calendar']['calendar_id'];

    $client = getGoogleCalendarClient();
    $service = new Google\Service\Calendar($client);

    $event = $service->events->get($calendarId, $eventId);

    $startDT = new DateTime($booking['start_datetime'], new DateTimeZone('Europe/Stockholm'));
    $endDT   = (clone $startDT)->modify("+{$booking['duration_minutes']} minutes");

    $event->setSummary($booking['service_name'] . ' - ' . $booking['client_name']);
    $event->setDescription("Booking details:\n" .
                           "Client: " . $booking['client_name'] . "\n" .
                           "Email: "  . $booking['client_email'] . "\n" .
                           "Phone: "  . $booking['client_phone']);
    
    $event->getStart()->setDateTime($startDT->format(DateTime::RFC3339));
    $event->getEnd()->setDateTime($endDT->format(DateTime::RFC3339));

    $service->events->update($calendarId, $eventId, $event);
    return true;
}

/**
 * Deletes an event from Google Calendar (used when a booking is cancelled).
 *
 * @param string $eventId The Google Calendar Event ID.
 * @return bool True on success.
 */
function deleteGoogleCalendarEvent(string $eventId): bool {
    $config = require __DIR__ . '/config.php';
    $calendarId = $config['google_calendar']['calendar_id'];

    $client = getGoogleCalendarClient();
    $service = new Google\Service\Calendar($client);

    try {
        $service->events->delete($calendarId, $eventId);
        return true;
    } catch (Exception $e) {
        // Return false if event was already deleted or not found
        return false;
    }
}