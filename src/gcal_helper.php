<?php
// gcal_helper.php - Google Calendar API Integration with Cancellation Support
require_once __DIR__ . '/vendor/autoload.php'; // Composer autoloader for google/apiclient

/**
 * Returns an authenticated Google Calendar API Service client.
 */
function getGoogleCalendarService(): Google\Service\Calendar {
    $client = new Google\Client();
    $client->setApplicationName('Clinic Booking System');
    $client->setScopes([Google\Service\Calendar::CALENDAR]);
    $client->setAuthConfig(__DIR__ . '/credentials.json'); // Service Account credentials
    $client->setAccessType('offline');

    return new Google\Service\Calendar($client);
}

/**
 * Creates or updates a Google Calendar event for an approved booking.
 * If the booking status is cancelled or late_no_show, it redirects to delete the event.
 */
function syncBookingToGCal(int $bookingId, PDO $pdo): bool {
    // 1. Fetch booking with service details
    $stmt = $pdo->prepare("
        SELECT b.*, s.name AS service_name, s.duration_minutes 
        FROM booking_requests b 
        JOIN services s ON b.service_id = s.id 
        WHERE b.id = ?
    ");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        return false;
    }

    // 2. If status is cancelled or late_no_show, remove it from Google Calendar
    if (in_array($booking['appointment_status'], ['cancelled', 'late_no_show'], true)) {
        return removeBookingFromGCal($bookingId, $pdo);
    }

    // 3. Only sync approved/scheduled bookings
    if ($booking['status'] !== 'approved') {
        return false;
    }

    try {
        $service    = getGoogleCalendarService();
        $calendarId = 'primary'; // Primary calendar or specific Calendar ID string

        $startUtc = new DateTime($booking['start_datetime'], new DateTimeZone('UTC'));
        $endUtc   = (clone $startUtc)->modify("+{$booking['duration_minutes']} minutes");

        $eventData = new Google\Service\Calendar\Event([
            'summary'     => $booking['service_name'] . ' - ' . $booking['client_name'],
            'description' => "Client Email: {$booking['client_email']}\n" .
                             "Client Phone: {$booking['client_phone']}\n" .
                             "Notes: " . ($booking['notes'] ?? 'None'),
            'start'       => ['dateTime' => $startUtc->format(DateTime::RFC3339)],
            'end'         => ['dateTime' => $endUtc->format(DateTime::RFC3339)],
        ]);

        // Check if event already exists on GCal
        if (!empty($booking['gcal_event_id'])) {
            $updatedEvent = $service->events->update($calendarId, $booking['gcal_event_id'], $eventData);
            return (bool)$updatedEvent->getId();
        }

        // Insert new event
        $newEvent = $service->events->insert($calendarId, $eventData);
        if ($newEvent->getId()) {
            $updateStmt = $pdo->prepare("UPDATE booking_requests SET gcal_event_id = ? WHERE id = ?");
            $updateStmt->execute([$newEvent->getId(), $bookingId]);
            return true;
        }
    } catch (Exception $e) {
        error_log("Google Calendar Sync Error (Booking #{$bookingId}): " . $e->getMessage());
    }

    return false;
}

/**
 * Deletes an event from Google Calendar when an appointment is cancelled.
 */
function removeBookingFromGCal(int $bookingId, PDO $pdo): bool {
    $stmt = $pdo->prepare("SELECT gcal_event_id FROM booking_requests WHERE id = ?");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (empty($booking['gcal_event_id'])) {
        return true; // Nothing to delete on Google Calendar
    }

    try {
        $service    = getGoogleCalendarService();
        $calendarId = 'primary';

        // Delete event from Google Calendar
        $service->events->delete($calendarId, $booking['gcal_event_id']);

        // Remove stored gcal_event_id from database
        $updateStmt = $pdo->prepare("UPDATE booking_requests SET gcal_event_id = NULL WHERE id = ?");
        $updateStmt->execute([$bookingId]);

        return true;
    } catch (Google\Service\Exception $e) {
        // If event was already deleted on GCal (410 Gone or 404 Not Found), clear DB reference
        if (in_array($e->getCode(), [404, 410], true)) {
            $updateStmt = $pdo->prepare("UPDATE booking_requests SET gcal_event_id = NULL WHERE id = ?");
            $updateStmt->execute([$bookingId]);
            return true;
        }
        error_log("Google Calendar Delete Error (Booking #{$bookingId}): " . $e->getMessage());
    } catch (Exception $e) {
        error_log("Google Calendar Delete Error (Booking #{$bookingId}): " . $e->getMessage());
    }

    return false;
}