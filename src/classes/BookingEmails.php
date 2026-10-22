<?php
/**
 * src/classes/BookingEmails.php
 * The emails around one booking: receipt to the customer and alert to the business when a request
 * arrives, and the answer (with an .ics file on approval) when the admin decides.
 * Builds the text variables from the booking, services and settings; EmailNotifier sends.
 */

require_once __DIR__ . '/EmailNotifier.php';
require_once __DIR__ . '/CalendarFeed.php';
require_once __DIR__ . '/ResourceProvider.php';

class BookingEmails {
    private EmailNotifier $notifier;
    private array $settings;
    private string $adminLang;

    public function __construct(?EmailNotifier $notifier = null, ?array $settings = null, ?string $adminLang = null) {
        $config = ResourceProvider::getConfig() ?? [];
        $this->notifier = $notifier ?? new EmailNotifier($config);
        $this->settings = $settings ?? ResourceProvider::getSettings() ?? [];
        $this->adminLang = $adminLang ?? (string)($config['app']['language'] ?? 'sv');
    }

    public function isEnabled(): bool {
        return $this->notifier->isEnabled();
    }

    /**
     * A new request was saved: receipt to the customer, alert to the business (if an address is set).
     * Returns whether the customer receipt was sent.
     */
    public function requestReceived(array $booking): bool {
        $vars = $this->vars($booking);
        $notify = $this->notifier->notificationEmail();
        if ($notify !== null) {
            $adminVars = $vars + ['email' => (string)($booking['customer_email'] ?? '')];
            if (($vars['siteUrl'] ?? '') !== '') {
                $adminVars['adminUrl'] = $vars['siteUrl'] . 'admin/pending.php';
            }
            $this->notifier->sendAdminAlert($notify, $adminVars, $this->adminLang);
        }
        return $this->notifier->sendBookingReceived((string)($booking['customer_email'] ?? ''), $vars, $this->lang($booking));
    }

    /**
     * The admin approved or rejected the request. Returns whether the email was sent.
     */
    public function decided(array $booking, bool $approved): bool {
        $vars = $this->vars($booking);
        $ics = $approved
            ? CalendarFeed::single($booking, $vars['service'] . ' – ' . $vars['business'], ResourceProvider::getTimezone())
            : null;
        return $this->notifier->sendBookingDecision((string)($booking['customer_email'] ?? ''), $approved, $vars, $this->lang($booking), $ics);
    }

    /**
     * Base URL of the booking site with a trailing slash (settings.json public_url), or '' when not set.
     */
    public static function siteUrl(array $settings): string {
        $url = trim((string)($settings['public_url'] ?? ''));
        return self::isValidSiteUrl($url) ? rtrim($url, '/') . '/' : '';
    }

    /** An http(s) URL without credentials, query or fragment. */
    public static function isValidSiteUrl(string $url): bool {
        if (strlen($url) > 200 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $parts = parse_url($url);
        return in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
            && !isset($parts['user'])
            && !isset($parts['query']) && !isset($parts['fragment']);
    }

    private function vars(array $booking): array {
        $serviceId = (string)($booking['service_id'] ?? '');
        $service = ResourceProvider::getServiceById($serviceId) ?? [];
        $business = trim((string)($this->settings['business_name'] ?? ''));
        $siteUrl = self::siteUrl($this->settings);
        // Names are single-line already (Input::text); this keeps the email layout intact for older records
        $oneLine = fn ($value) => trim(str_replace(["\r", "\n"], ' ', (string)$value));

        return [
            'name' => $oneLine($booking['customer_name'] ?? ''),
            'service' => $oneLine($service['name'] ?? $serviceId),
            'date' => (string)($booking['date'] ?? ''),
            'time' => (string)($booking['time'] ?? ''),
            'end' => (string)($booking['end_time'] ?? ''),
            'business' => $business !== '' ? $business : 'Book Me Calendar',
            'siteUrl' => $siteUrl,
            'privacyUrl' => $siteUrl !== '' ? $siteUrl . 'privacy.php' : '',
        ];
    }

    private function lang(array $booking): ?string {
        $lang = $booking['language'] ?? null;
        return is_string($lang) && $lang !== '' ? $lang : null;
    }
}
