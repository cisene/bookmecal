<?php
// send_reminders.php - Automatiserat CLI/Cron-skript för bokningspåminnelser

// Körs skriptet via CLI eller direkt i webbläsaren?
if (php_sapi_name() !== 'cli') {
    // Om det anropas via webben kan du lägga till en säkerhetsnyckel, t.ex. ?token=DIN_HEMLIGA_NYCKEL
    $secretToken = process.env('REMINDER_CRON_TOKEN') ?? 'default_secret_token';
    if (!isset($_GET['token']) || $_GET['token'] !== $secretToken) {
        http_response_code(403);
        die("Åtkomst nekad: Ogiltig säkerhetstoken.
");
    }
}

// 1. Ladda in beroenden och konfiguration
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Localization.php';

$config = require __DIR__ . '/config.php';

try {
    $pdo = Database::getConnection($config['db']);
} catch (Exception $e) {
    die("Kritiskt fel: Kunde inte ansluta till databasen - " . $e->getMessage() . "
");
}

// 2. Initiera spårning och inställningar
$i18n = Localization::getInstance();
$smtpConfig = $config['smtp'] ?? [];
$isSmtpEnabled = !empty($smtpConfig['enabled']);

echo "[" . date('Y-m-d H:i:s') . "] Startar bearbetning av påminnelser...
";

// 3. Hämtning av bokningar som äger rum inom de närmsta 24 timmarna
// och som inte redan har fått en påminnelse skickad
$query = "
    SELECT b.*, s.name as service_name 
    FROM booking_requests b
    LEFT JOIN services s ON b.service_id = s.id
    WHERE b.status = 'approved'
      AND b.reminder_sent = 0
      AND b.start_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // För SQLite eller databaser utan DATE_ADD
    $fallbackQuery = "
        SELECT b.*, s.name as service_name 
        FROM booking_requests b
        LEFT JOIN services s ON b.service_id = s.id
        WHERE b.status = 'approved'
          AND b.reminder_sent = 0
          AND b.start_datetime >= datetime('now')
          AND b.start_datetime <= datetime('now', '+24 hours')
    ";
    $stmt = $pdo->prepare($fallbackQuery);
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (empty($bookings)) {
    echo "[" . date('Y-m-d H:i:s') . "] Inga påminnelser behöver skickas just nu.
";
    exit(0);
}

echo "Hittade " . count($bookings) . " bokning(ar) att skicka påminnelse för.
";

// 4. Loopa igenom och skicka påminnelser
$sentCount = 0;
$failCount = 0;

foreach ($bookings as $booking) {
    $clientEmail = $booking['client_email'];
    $clientName  = $booking['client_name'];
    $bookingTime = $booking['start_datetime'];
    $serviceName = $booking['service_name'] ?? 'Bokat pass';
    $lang        = $booking['preferred_language'] ?? 'sv';

    // Översätt ämne och brödtext baserat på kundens föredragna språk
    $subject = $i18n->translate('reminder_email_subject', ['service' => $serviceName], $lang);
    
    // Fallback om nyckeln saknas i JSON-ordboken
    if ($subject === 'reminder_email_subject') {
        $subject = "Påminnelse: Din bokning imorgon ({$serviceName})";
    }

    $message = sprintf(
        "Hej %s,

Detta är en påminnelse om din bokning för %s imorgon klockan %s.

Varmt välkommen!

Med vänliga hälsningar,
Book Me Calendar",
        $clientName,
        $serviceName,
        date('H:i', strtotime($bookingTime))
    );

    $mailSuccess = false;

    if ($isSmtpEnabled) {
        // Här kan du integrera en PHPMailer/SwiftMailer-instans baserat på $config['smtp']
        // För standardimplementering körs mail() eller anpassad SMTP-sändare
        $headers = [
            'From' => sprintf('%s <%s>', $smtpConfig['from_name'] ?? 'Bokning', $smtpConfig['from_email'] ?? 'noreply@example.com'),
            'Reply-To' => $smtpConfig['from_email'] ?? 'noreply@example.com',
            'X-Mailer' => 'PHP/' . phpversion(),
            'Content-Type' => 'text/plain; charset=UTF-8'
        ];

        $mailSuccess = @mail($clientEmail, $subject, $message, $headers);
    } else {
        // Logga att utskicket simuleras om SMTP är inaktiverat i config
        echo "  [SIMULERING] Påminnelse till {$clientEmail} för bokning #{$booking['id']} ({$bookingTime})
";
        $mailSuccess = true;
    }

    if ($mailSuccess) {
        // Markera bokningen som att påminnelse har skickats
        $updateStmt = $pdo->prepare("UPDATE booking_requests SET reminder_sent = 1 WHERE id = ?");
        $updateStmt->execute([$booking['id']]);
        
        $sentCount++;
        echo "  [OK] Påminnelse skickad till {$clientEmail} (Bokning #{$booking['id']})
";
    } else {
        $failCount++;
        echo "  [FEL] Kunde inte skicka e-post till {$clientEmail} (Bokning #{$booking['id']})
";
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Klart! Skickade: {$sentCount}, Misslyckade: {$failCount}.
";
