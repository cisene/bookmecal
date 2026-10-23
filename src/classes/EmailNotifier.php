<?php
/**
 * src/classes/EmailNotifier.php
 * Plain-text notification emails. All texts come from the language files (email.* keys).
 * Nothing is sent unless config.json smtp.enabled is true. With smtp.host set, mail goes through
 * that SMTP server (SmtpTransport; the password is kept in data/secrets.json as smtp_password),
 * otherwise through PHP mail().
 */

require_once __DIR__ . '/Localization.php';
require_once __DIR__ . '/Secrets.php';
require_once __DIR__ . '/SmtpTransport.php';

class EmailNotifier {
    private array $smtpConfig;
    /** @var callable(string $to, string $subject, string $body, string $headers): bool */
    private $transport;

    /**
     * $config is the whole config.json; $transport replaces PHP mail() (tests).
     */
    public function __construct(array $config, ?callable $transport = null) {
        $this->smtpConfig = $config['smtp'] ?? [];
        $this->transport = $transport ?? self::smtpTransport($this->smtpConfig) ?? function (string $to, string $subject, string $body, string $headers): bool {
            // Without an SMTP server: php.ini sendmail_path / SMTP decide
            return @mail($to, $subject, $body, $headers);
        };
    }

    /** The SMTP transport for config.json smtp, or null when no server is set (PHP mail()). */
    public static function smtpTransport(array $smtp): ?SmtpTransport {
        $host = trim((string)($smtp['host'] ?? ''));
        if ($host === '') {
            return null;
        }
        $encryption = (string)($smtp['encryption'] ?? 'starttls');
        return new SmtpTransport(
            $host,
            (int)($smtp['port'] ?? ($encryption === 'ssl' ? 465 : 587)),
            $encryption,
            (string)($smtp['username'] ?? ''),
            Secrets::value('smtp_password'),
            (string)($smtp['from_email'] ?? ''),
        );
    }

    /** Why the last mail could not be handed to the SMTP server, or '' (also with PHP mail()). */
    public function lastError(): string {
        return $this->transport instanceof SmtpTransport ? $this->transport->lastError() : '';
    }

    /** A short test mail to check the settings. */
    public function sendTest(string $toEmail, ?string $lang = null): bool {
        return $this->send($toEmail, __t('email.test.subject', [], $lang), __t('email.test.body', [], $lang));
    }

    public function isEnabled(): bool {
        return ($this->smtpConfig['enabled'] ?? false) === true;
    }

    /** Address for new-request alerts, or null when none is set. */
    public function notificationEmail(): ?string {
        $email = $this->smtpConfig['notification_email'] ?? null;
        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    /**
     * Receipt to the customer: the request arrived and is waiting for an answer.
     * $vars: name, service, date, time, end, business; optional privacyUrl.
     */
    public function sendBookingReceived(string $toEmail, array $vars, ?string $lang = null): bool {
        return $this->send($toEmail, __t('email.received.subject', $vars, $lang), $this->body('email.received.body', $vars, $lang));
    }

    /**
     * Answer to the customer after the admin approved or rejected the request. An approval carries
     * the appointment as booking.ics when $ics is given, and contact cards as contact.vcf when $vcard is.
     */
    public function sendBookingDecision(string $toEmail, bool $approved, array $vars, ?string $lang = null, ?string $ics = null, ?string $vcard = null): bool {
        $attachments = [];
        if ($approved && $ics !== null) {
            $attachments[] = ['name' => 'booking.ics', 'type' => 'text/calendar; method=PUBLISH', 'content' => $ics];
        }
        if ($approved && $vcard !== null) {
            $attachments[] = ['name' => 'contact.vcf', 'type' => 'text/vcard', 'content' => $vcard];
        }
        return $approved
            ? $this->send($toEmail, __t('email.approved.subject', $vars, $lang), $this->body('email.approved.body', $vars, $lang), $attachments)
            : $this->send($toEmail, __t('email.rejected.subject', $vars, $lang), $this->body('email.rejected.body', $vars, $lang));
    }

    /**
     * The customer's approved booking moved to another employee; same time. Carries the updated
     * appointment (booking.ics) and contact cards (contact.vcf) when given.
     * $vars as for the approval, plus previous (the former employee's name, may be '').
     */
    public function sendBookingMoved(string $toEmail, array $vars, ?string $lang = null, ?string $ics = null, ?string $vcard = null): bool {
        $attachments = [];
        if ($ics !== null) {
            $attachments[] = ['name' => 'booking.ics', 'type' => 'text/calendar; method=PUBLISH', 'content' => $ics];
        }
        if ($vcard !== null) {
            $attachments[] = ['name' => 'contact.vcf', 'type' => 'text/vcard', 'content' => $vcard];
        }
        $key = ($vars['previous'] ?? '') !== '' ? 'email.moved.body' : 'email.moved.bodyNoPrevious';
        return $this->send($toEmail, __t('email.moved.subject', $vars, $lang), $this->body($key, $vars, $lang), $attachments);
    }

    /**
     * Alert to the business about a new booking request.
     * $vars: name, email, service, date, time, end; optional adminUrl.
     */
    public function sendAdminAlert(string $adminEmail, array $vars, ?string $lang = null): bool {
        $body = __t('email.admin.newRequest.body', $vars, $lang);
        if (!empty($vars['adminUrl'])) {
            $body .= "\n\n" . __t('email.admin.newRequest.link', $vars, $lang);
        }
        return $this->send($adminEmail, __t('email.admin.newRequest.subject', $vars, $lang), $body);
    }

    /**
     * Alert to the business that a customer cancelled online.
     * $vars: name, email, service, date, time, end; optional adminUrl.
     */
    public function sendAdminCancelled(string $adminEmail, array $vars, ?string $lang = null): bool {
        $body = __t('email.admin.cancelled.body', $vars, $lang);
        if (!empty($vars['adminUrl'])) {
            $body .= "\n\n" . __t('email.admin.cancelled.link', $vars, $lang);
        }
        return $this->send($adminEmail, __t('email.admin.cancelled.subject', $vars, $lang), $body);
    }

    /**
     * Reminder sent the day before an approved appointment.
     * $vars: name, service, date, time, end, price, currency, business; optional cancelUrl, privacyUrl.
     */
    public function sendReminder(string $toEmail, array $vars, ?string $lang = null): bool {
        $vars['time'] = trim(($vars['date'] ?? '') . ' ' . ($vars['time'] ?? ''));
        $text = __t('email.reminder.heading', $vars, $lang) . "\n\n"
            . __t('email.reminder.intro', $vars, $lang) . "\n\n"
            . __t('email.reminder.policyNotice', $vars, $lang);
        return $this->send($toEmail, __t('email.reminder.subject', $vars, $lang), $this->withLinks($text, $vars, $lang));
    }

    /**
     * Sends one plain-text mail, optionally with attachments (list of ['name', 'type', 'content']).
     * Returns false when email is off. Rejects malformed addresses and strips CR/LF from header
     * values so user-supplied data cannot inject extra mail headers.
     */
    public function send(string $to, string $subject, string $body, array $attachments = []): bool {
        $fromEmail = $this->smtpConfig['from_email'] ?? 'noreply@example.com';
        $fromName = $this->smtpConfig['from_name'] ?? 'Book Me Calendar';

        if (!$this->isEnabled() || filter_var($to, FILTER_VALIDATE_EMAIL) === false || filter_var($fromEmail, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }
        $subject = str_replace(["\r", "\n"], ' ', $subject);
        $fromName = trim(str_replace(["\r", "\n", '<', '>', '"'], ' ', (string)$fromName));
        $body = str_replace(["\r\n", "\r"], "\n", $body);

        $headers = "MIME-Version: 1.0\r\n"
            . 'From: ' . self::encodeHeader($fromName) . " <{$fromEmail}>\r\n"
            . "Reply-To: {$fromEmail}\r\n";

        if ($attachments === []) {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n";
        } else {
            $boundary = 'bmc-' . bin2hex(random_bytes(12));
            $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
            $body = "--{$boundary}\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
                . str_replace("\n", "\r\n", $body) . "\r\n";
            foreach ($attachments as $attachment) {
                $name = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$attachment['name']);
                $type = preg_replace('/[^A-Za-z0-9.\/=; -]/', '', (string)$attachment['type']);
                $body .= "--{$boundary}\r\n"
                    . "Content-Type: {$type}; charset=UTF-8; name=\"{$name}\"\r\n"
                    . "Content-Disposition: attachment; filename=\"{$name}\"\r\n"
                    . "Content-Transfer-Encoding: base64\r\n\r\n"
                    . chunk_split(base64_encode((string)$attachment['content']), 76, "\r\n");
            }
            $body .= "--{$boundary}--\r\n";
        }

        return ($this->transport)($to, self::encodeHeader($subject), $body, $headers);
    }

    /**
     * A header value as UTF-8 encoded words of at most 75 characters each (RFC 2047), folded
     * onto continuation lines, never splitting a character.
     */
    public static function encodeHeader(string $text): string {
        $words = [];
        $chunk = '';
        foreach (mb_str_split($text, 1, 'UTF-8') as $char) {
            // 45 bytes encode to 60 base64 characters: 72 with the =?UTF-8?B? ?= wrapping
            if (strlen($chunk . $char) > 45) {
                $words[] = $chunk;
                $chunk = '';
            }
            $chunk .= $char;
        }
        $words[] = $chunk;
        return implode("\r\n ", array_map(fn ($word) => '=?UTF-8?B?' . base64_encode($word) . '?=', $words));
    }

    /** A customer email body with the links (see withLinks()). */
    private function body(string $key, array $vars, ?string $lang): string {
        return $this->withLinks(__t($key, $vars, $lang), $vars, $lang);
    }

    /** Adds the cancellation link and the privacy notice link when their URLs are known. */
    private function withLinks(string $body, array $vars, ?string $lang): string {
        if (!empty($vars['cancelUrl'])) {
            $body .= "\n\n" . __t('email.cancelLink', $vars, $lang);
        }
        if (!empty($vars['privacyUrl'])) {
            $body .= "\n\n" . __t('email.privacyLink', $vars, $lang);
        }
        return $body;
    }
}
