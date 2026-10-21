<?php
/**
 * src/classes/EmailNotifier.php
 * Plain-text notification emails. All texts come from the language files (email.* keys).
 */

require_once __DIR__ . '/Localization.php';

class EmailNotifier {
    private array $smtpConfig;

    public function __construct(array $config) {
        $this->smtpConfig = $config['smtp'] ?? [];
    }

    /**
     * Confirmation to the customer that their request was received.
     */
    public function sendBookingConfirmation(string $toEmail, string $clientName, string $start, string $end, ?string $lang = null): bool {
        $vars = ['name' => $clientName, 'start' => $start, 'end' => $end];
        return $this->send(
            $toEmail,
            __t('email.confirmation.subject', $vars, $lang),
            __t('email.confirmation.body', $vars, $lang)
        );
    }

    /**
     * Alert to the administrator about a new booking request.
     */
    public function sendAdminAlert(string $adminEmail, string $serviceName, string $clientName, string $clientEmail, string $time, ?string $lang = null): bool {
        $vars = ['service' => $serviceName, 'name' => $clientName, 'email' => $clientEmail, 'phone' => '-', 'time' => $time];
        return $this->send(
            $adminEmail,
            __t('email.admin.newBooking.subject', $vars, $lang),
            __t('email.admin.newBooking.body', $vars, $lang)
        );
    }

    /**
     * Reminder sent the day before an approved appointment.
     */
    public function sendReminder(string $toEmail, string $clientName, string $serviceName, string $time, string $price, string $currency, ?string $lang = null): bool {
        $vars = ['name' => $clientName, 'service' => $serviceName, 'time' => $time, 'price' => $price, 'currency' => $currency];
        $body = __t('email.reminder.heading', $vars, $lang) . "\n\n"
            . __t('email.reminder.intro', $vars, $lang) . "\n\n"
            . __t('email.reminder.policyNotice', $vars, $lang);
        return $this->send($toEmail, __t('email.reminder.subject', $vars, $lang), $body);
    }

    /**
     * Sends one plain-text mail. Rejects malformed addresses and strips CR/LF from header
     * values so user-supplied data cannot inject extra mail headers.
     */
    public function send(string $to, string $subject, string $body): bool {
        $fromEmail = $this->smtpConfig['from_email'] ?? 'noreply@example.com';
        $fromName = $this->smtpConfig['from_name'] ?? 'Book Me Calendar';

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false || filter_var($fromEmail, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }
        $subject = str_replace(["\r", "\n"], ' ', $subject);
        $fromName = str_replace(["\r", "\n", '<', '>'], ' ', (string)$fromName);

        $headers = "MIME-Version: 1.0\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "From: {$fromName} <{$fromEmail}>\r\n"
            . "Reply-To: {$fromEmail}\r\n";

        // SMTP transport is configured through php.ini (sendmail_path / SMTP) for now
        return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
    }
}
