<?php
require_once __DIR__ . '/vendor/autoload.php';

class GoogleCalendarSync {
    private $service;
    private $calendarId;

    public function __construct(string $credentialsJsonPath, string $calendarId) {
        $client = new Google\Client();
        $client->setAuthConfig($credentialsJsonPath);
        $client->addScope(Google\Service\Calendar::CALENDAR);
        
        $this->service = new Google\Service\Calendar($client);
        $this->calendarId = $calendarId;
    }

    public function pushApprovedBooking(array $booking): string {
        $event = new Google\Service\Calendar\Event([
            'summary'     => 'Booking: ' . $booking['client_name'],
            'description' => 'Client Email: ' . $booking['client_email'],
            'start' => [
                'dateTime' => date('c', strtotime($booking['start_datetime'])),
                'timeZone' => 'UTC',
            ],
            'end' => [
                'dateTime' => date('c', strtotime($booking['end_datetime'])),
                'timeZone' => 'UTC',
            ],
        ]);

        $createdEvent = $this->service->events->insert($this->calendarId, $event);
        return $createdEvent->getId(); // Save back to DB to link records
    }
}