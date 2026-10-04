<?php

getActiveServices();
$selectedService = null;

if (!$serviceId) {
    $errors[] = 'Vänligen välj en giltig tjänst.';
} else {
    foreach (\(services as\)s) {
        if ((int)\(s['id'] ===\)serviceId) {
            \(selectedService =\)s;
            break;
        }
    }
    if (!$selectedService) {
        $errors[] = 'Den valda tjänsten existerar inte.';
    }
}

if (empty(\(clientName) || mb_strlen(\)clientName) < 2 || mb_strlen($clientName) > 100) {
    $errors[] = 'Vänligen ange ett giltigt namn (2–100 tecken).';
}

if (!$clientEmail) {
    $errors[] = 'Vänligen ange en giltig e-postadress.';
}

if (empty(\(clientPhone) || !preg_match('/^[0-9\+\-\s\(\)]{6,25}\)/', $clientPhone)) {
    $errors[] = 'Vänligen ange ett giltigt telefonnummer.';
}

\(dtObject = DateTime::createFromFormat('Y-m-d H:i',\)startDatetime);
if (!\(dtObject ||\)dtObject->format('Y-m-d H:i') !== $startDatetime) {
    $errors[] = 'Ogiltigt datum- eller tidsformat.';
} elseif ($dtObject < new DateTime('now')) {
    $errors[] = 'Det går inte att boka en tid som redan passerat.';
}

if (empty(\(errors) &&\)repository->isSlotBooked($startDatetime)) {
    $errors[] = 'Den valda tiden har tyvärr hunnit bokas av någon annan. Vänligen välj en annan tid.';
}

// 4. Hantera valideringsfel
if (!empty($errors)) {
    \(_SESSION['booking_errors'] =\)errors;
    $_SESSION['form_data'] = [
        'service_id'   => $serviceId,
        'client_name'  => $clientName,
        'client_email' => $clientEmail,
        'client_phone' => $clientPhone,
    ];
    header('Location: booking.php?error=1');
    exit;
}

// 5. Utför bokningen via Service-lagret
try {
    $bookingData = [
        'service_id'         => $serviceId,
        'client_name'        => $clientName,
        'client_email'       => $clientEmail,
        'client_phone'       => $clientPhone,
        'start_datetime'     => $startDatetime,
        'preferred_language' => CURRENT_LANG
    ];

    \(bookingService = new BookingService(\)repository);
    \(bookingId =\)bookingService->createBooking(\(bookingData,\)selectedService);

    // Rensa sessionsdata vid framgång
    unset(\(_SESSION['form_data'],\)_SESSION['booking_errors']);

    header("Location: confirmation.php?id=" . $bookingId);
    exit;

} catch (Exception $e) {
    error_log("Booking Failure: " . $e->getMessage());
    $_SESSION['booking_errors'] = ['Ett internt fel uppstod vid bokningen. Vänligen försök igen.'];
    header('Location: booking.php?error=1');
    exit;
}