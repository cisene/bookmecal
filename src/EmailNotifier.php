<?php
// EmailNotifier.php - Handles email notifications for booking confirmations and admin alerts

class EmailNotifier {
    private array $smtpConfig;

    public function __construct(array $config) {
        $this->smtpConfig = $config['smtp'] ?? [];
    }

    /**
     * Sends a booking confirmation email to the client.
     */
    public function sendBookingConfirmation(string $toEmail, string $clientName, string $start, string $end): bool {
        $subject = 'Booking Request Confirmation - Book Me Calendar';
        $message = "Hello {$clientName},\n\n"
            . "We have received your booking request for the following time slot:\n"
            . "Start: {$start}\n"
            . "End: {$end}\n\n"
            . "Your request is currently pending review. We will notify you once it has been approved.\n\n"
            . "Best regards,\nBook Me Calendar Team";

        return $this->sendMail($toEmail, $subject, $message);
    }

    /**
     * Sends a notification alert to the administrator about a new booking request.
     */
    public function sendAdminAlert(string $adminEmail, string $clientName, string $clientEmail, string $start, string $end): bool {
        $subject = 'New Booking Request - Book Me Calendar';
        $message = "A new booking request has been submitted:\n\n"
            . "Client: {$clientName} ({$clientEmail})\n"
            . "Start: {$start}\n"
            . "End: {$end}\n\n"
            . "Please log in to the system to review, approve, or reject this request.";

        return $this->sendMail($adminEmail, $subject, $message);
    }

    /**
     * Core mail dispatch helper supporting native mail or custom headers.
     */
    private function sendMail(string $to, string $subject, string $body): bool {
        $fromEmail = $this->smtpConfig['from_email'] ?? 'noreply@example.com';
        $fromName = $this->smtpConfig['from_name'] ?? 'Book Me Calendar';
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
        $headers .= "Reply-To: {$fromEmail}\r\n";

        // If SMTP is disabled in config, fallback to PHP's native mail function
        if (empty($this->smtpConfig['enabled'])) {
            return @mail($to, $subject, $body, $headers);
        }

        // When SMTP is enabled, mail() can be configured via php.ini or an SMTP transport wrapper.
        return @mail($to, $subject, $body, $headers);
    }
}