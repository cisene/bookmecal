<?php
/**
 * src/classes/CustomerMessages.php
 * Emails the admin writes to customers: one customer, the customers with upcoming bookings
 * (optionally for some dates, one employee or one treatment), or every customer with a stored
 * booking. Customers are found through their bookings; there is no separate customer register,
 * so only addresses that booked can be written to. Each customer gets an email of their own,
 * never a shared one. Sent messages are kept in data/messages.json (without addresses).
 */

require_once __DIR__ . '/BookingEmails.php';
require_once __DIR__ . '/BookingRepository.php';
require_once __DIR__ . '/EmailNotifier.php';
require_once __DIR__ . '/JsonStore.php';
require_once __DIR__ . '/Localization.php';
require_once __DIR__ . '/ResourceProvider.php';

class CustomerMessages {
    public const GROUPS = ['one', 'upcoming', 'all'];
    public const SUBJECT_MAX = 150;
    public const BODY_MAX = 5000;
    /** More than this in one go is refused: narrow the group instead. */
    public const MAX_RECIPIENTS = 500;
    private const HISTORY_MAX = 50;

    private string $historyPath;

    /** $settings is settings.json (business name, public_url); $historyPath for tests. */
    public function __construct(private EmailNotifier $notifier, private array $settings, ?string $historyPath = null) {
        $this->historyPath = $historyPath ?? ResourceProvider::dataPath('messages.json');
    }

    public function isEnabled(): bool {
        return $this->notifier->isEnabled();
    }

    /**
     * The customers to write to, keyed by lower-case email, as ['email', 'name', 'language'] taken
     * from each customer's latest booking, sorted by name.
     * $criteria: group (GROUPS); email for 'one'; for 'upcoming' optional from and to (dates,
     * from never before $today), employee_id and service_id ('' for any).
     */
    public static function recipients(array $bookings, array $criteria, string $today): array {
        $group = $criteria['group'] ?? '';
        $email = mb_strtolower(trim((string)($criteria['email'] ?? '')));
        $from = max($today, (string)($criteria['from'] ?? ''));
        $to = (string)($criteria['to'] ?? '');
        $employeeId = (string)($criteria['employee_id'] ?? '');
        $serviceId = (string)($criteria['service_id'] ?? '');

        uasort($bookings, fn (array $a, array $b) => strcmp(($a['date'] ?? '') . ' ' . ($a['time'] ?? ''), ($b['date'] ?? '') . ' ' . ($b['time'] ?? '')));
        $recipients = [];
        foreach ($bookings as $booking) {
            $address = trim((string)($booking['customer_email'] ?? ''));
            $key = mb_strtolower($address);
            if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }
            $date = (string)($booking['date'] ?? '');
            $matches = match ($group) {
                'one' => $key === $email,
                'all' => true,
                'upcoming' => in_array($booking['status'] ?? '', BookingRepository::BLOCKING_STATUSES, true)
                    && $date >= $from && ($to === '' || $date <= $to)
                    && ($employeeId === '' || ($booking['employee_id'] ?? '') === $employeeId)
                    && ($serviceId === '' || ($booking['service_id'] ?? '') === $serviceId),
                default => false,
            };
            if ($matches) {
                // Later bookings overwrite earlier ones: the latest name and language win
                $recipients[$key] = [
                    'email' => $address,
                    'name' => trim(str_replace(["\r", "\n"], ' ', (string)($booking['customer_name'] ?? ''))),
                    'language' => is_string($booking['language'] ?? null) && $booking['language'] !== '' ? $booking['language'] : null,
                ];
            }
        }
        uasort($recipients, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']) ?: strcmp($a['email'], $b['email']));
        return $recipients;
    }

    /**
     * The email one customer gets: [subject, body]. {name} in the subject or text becomes the
     * customer's name; a footer in the customer's language says why they get it and links the privacy notice.
     */
    public function compose(array $recipient, string $subject, string $text): array {
        $name = $recipient['name'] !== '' ? $recipient['name'] : $recipient['email'];
        $lang = $recipient['language'] ?? null;
        $business = trim((string)($this->settings['business_name'] ?? ''));
        $footer = __t('email.message.footer', ['business' => $business !== '' ? $business : 'Book Me Calendar'], $lang);
        $siteUrl = BookingEmails::siteUrl($this->settings);
        if ($siteUrl !== '') {
            $footer .= "\n" . __t('email.privacyLink', ['privacyUrl' => $siteUrl . 'privacy.php'], $lang);
        }
        return [
            str_replace('{name}', $name, $subject),
            rtrim(str_replace('{name}', $name, $text)) . "\n\n-- \n" . $footer,
        ];
    }

    /**
     * Sends the message to each recipient on its own. Returns ['sent' => n, 'failed' => n, 'error' => the last
     * server error or ''].
     */
    public function send(array $recipients, string $subject, string $text): array {
        $result = ['sent' => 0, 'failed' => 0, 'error' => ''];
        foreach ($recipients as $recipient) {
            [$personalSubject, $body] = $this->compose($recipient, $subject, $text);
            if ($this->notifier->send($recipient['email'], $personalSubject, $body)) {
                $result['sent']++;
            } else {
                $result['failed']++;
                $result['error'] = $this->notifier->lastError() ?: $result['error'];
            }
        }
        return $result;
    }

    /** Sent messages, newest first. */
    public function history(): array {
        return JsonStore::read($this->historyPath) ?? [];
    }

    /** Keeps a sent message (who, when, which group, the text, how many got it); the newest HISTORY_MAX are kept. */
    public function record(array $entry): bool {
        return JsonStore::update($this->historyPath, function (array $history) use ($entry) {
            array_unshift($history, $entry);
            return array_slice($history, 0, self::HISTORY_MAX);
        });
    }
}
