<?php

class BookingService {
    private BookingRepository $repository;
    private ?GoogleSync $googleSync;
    private array $config;

    public function __construct(BookingRepository $repository, ?GoogleSync $googleSync = null, array $config = []) {
        $this->repository = $repository;
        $this->googleSync = $googleSync;
        $this->config = $config;
    }

    /**
     * Hanterar hela bokningsflödet: validering, lagring och eventuell kalendersynk.
     *
     * @param array $data Innehåller service_id, client_name, client_email, client_phone, start_datetime, preferred_language, m.m.
     * @return array Innehåller boknings-ID, status och eventuellt Google Calendar Event-ID.
     */
    public function bookSlot(array $data): array {
        // 1. Validera obligatoriska fält
        if (empty($data['start_datetime']) || empty($data['client_name']) || empty($data['client_email'])) {
            throw new InvalidArgumentException("Alla obligatoriska fält (namn, e-post och starttid) måste fyllas i.");
        }

        $startDatetime = $data['start_datetime'];
        $dateObj = new DateTime($startDatetime);
        $dateStr = $dateObj->format('Y-m-d');

        // 2. Kontrollera om datumet är en helgdag
        if ($this->repository->isHoliday($dateStr)) {
            throw new RuntimeException("Det valda datumet är en helgdag och kan inte bokas.");
        }

        // 3. Kontrollera om tiden redan är bokad
        if ($this->repository->isSlotBooked($startDatetime)) {
            throw new RuntimeException("Den valda tiden är tyvärr redan upptagen.");
        }

        // 4. Spara bokningen i databasen
        $bookingId = $this->repository->createBookingRecord($data);

        $gcalEventId = null;

        // 5. Synkronisera till Google Calendar om det är aktiverat
        if ($this->googleSync !== null && !empty($this->config['google_calendar']['enabled'])) {
            try {
                $gcalEventId = $this->googleSync->createEvent($data);
                if ($gcalEventId) {
                    $this->repository->updateGcalEventId($bookingId, $gcalEventId);
                }
            } catch (Exception $e) {
                // Logga felet men låt bokningen gå igenom i databasen
                error_log("Google Calendar sync failed for booking #{$bookingId}: " . $e->getMessage());
            }
        }

        return [
            'success'       => true,
            'booking_id'    => $bookingId,
            'gcal_event_id' => $gcalEventId,
            'message'       => 'Bokningen har genomförts framgångsrikt.'
        ];
    }

    /**
     * Hämtar en bokning baserat på ID.
     */
    public function getBooking(int $bookingId): ?array {
        return $this->repository->getBookingById($bookingId);
    }
}
