<?php
/**
 * BookingService.php
 * Orchestrates booking validation, repository interaction, Google Calendar synchronization, 
 * and email notifications.
 */

require_once __DIR__ . '/BookingRepository.php';
require_once __DIR__ . '/AvailabilityEngine.php';
require_once __DIR__ . '/GoogleSync.php';
require_once __DIR__ . '/EmailNotifier.php';

class BookingService {
    private BookingRepository $repository;

    public function __construct(BookingRepository $repository) {
        $this->repository = $repository;
    }

    public function processBooking(array $data): array {
        $country = $data['country'] ?? 'se';
        $serviceId = $data['service_id'] ?? '';
        $date = $data['date'] ?? '';
        $time = $data['time'] ?? '';
        
        $clientName = trim($data['name'] ?? ($data['customer_name'] ?? ''));
        $clientEmail = trim($data['email'] ?? ($data['customer_email'] ?? ''));
        $clientPhone = trim($data['phone'] ?? ($data['customer_phone'] ?? ''));

        if (empty($clientName) || empty($clientEmail) || empty($serviceId) || empty($date) || empty($time)) {
            return [
                'success' => false,
                'error' => 'Missing required booking parameters.'
            ];
        }

        $service = $this->repository->getServiceById($serviceId);
        if (!$service) {
            return [
                'success' => false,
                'error' => 'Specified service not found.'
            ];
        }

        $engine = new AvailabilityEngine($this->repository, $country);
        $availableSlots = $engine->getAvailableSlots($date, $serviceId);

        if (!is_array($availableSlots) || !in_array($time, $availableSlots)) {
            return [
                'success' => false,
                'error' => 'The selected time slot is no longer available.'
            ];
        }

        $duration = (int)($service['duration'] ?? ($service['duration_minutes'] ?? 45));
        $cleanup = (int)($service['cleanup'] ?? 0);
        
        $startDateTime = new DateTime("$date $time");
        $endDateTime = clone $startDateTime;
        $endDateTime->modify("+{$duration} minutes");

        $bookingRecord = [
            'service_id' => $serviceId,
            'service_name' => $service['name'] ?? 'Treatment',
            'client_name' => $clientName,
            'client_email' => $clientEmail,
            'client_phone' => $clientPhone,
            'date' => $date,
            'time' => $time,
            'duration_minutes' => $duration,
            'cleanup_minutes' => $cleanup,
            'start_datetime' => $startDateTime->format('Y-m-d H:i:s'),
            'end_datetime' => $endDateTime->format('Y-m-d H:i:s'),
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];

        $bookingId = $this->repository->createPendingRequest($bookingRecord);

        // Sync to Google Calendar if configured
        try {
            if (class_exists('GoogleSync')) {
                $gSync = new GoogleSync();
                if (method_exists($gSync, 'createEvent')) {
                    $gSync->createEvent(array_merge($bookingRecord, ['id' => $bookingId]));
                }
            }
        } catch (Throwable $e) {
            error_log("[GoogleSync Error] Failed to sync booking {$bookingId}: " . $e->getMessage());
        }

        // Send Email Confirmation
        try {
            if (class_exists('EmailNotifier')) {
                EmailNotifier::sendBookingConfirmation(array_merge($bookingRecord, ['id' => $bookingId]));
            }
        } catch (Throwable $e) {
            error_log("[Email Error] Failed to send confirmation for booking {$bookingId}: " . $e->getMessage());
        }

        return [
            'success' => true,
            'message' => 'Booking request submitted successfully!',
            'booking_id' => $bookingId
        ];
    }
}