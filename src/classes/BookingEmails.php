<?php
/**
 * src/classes/BookingEmails.php
 * The emails around one booking: receipt to the customer and alert to the business when a request
 * arrives, the answer (with an .ics file on approval) when the admin decides, the reminder, and an
 * alert to the business when the customer cancels. Customer emails carry the cancellation link.
 * Builds the text variables from the booking, services and settings; EmailNotifier sends.
 */

require_once __DIR__ . '/EmailNotifier.php';
require_once __DIR__ . '/CalendarFeed.php';
require_once __DIR__ . '/CancelLink.php';
require_once __DIR__ . '/ContactCard.php';
require_once __DIR__ . '/EmployeeRepository.php';
require_once __DIR__ . '/Staff.php';
require_once __DIR__ . '/ResourceProvider.php';

class BookingEmails {
    private EmailNotifier $notifier;
    private array $settings;
    private string $adminLang;
    private ?array $employees;

    /** $employees: employee records keyed by ID; loaded from data/employees/ when needed and null. */
    public function __construct(?EmailNotifier $notifier = null, ?array $settings = null, ?string $adminLang = null, ?array $employees = null) {
        $config = ResourceProvider::getConfig() ?? [];
        $this->notifier = $notifier ?? new EmailNotifier($config);
        $this->settings = $settings ?? ResourceProvider::getSettings() ?? [];
        $this->adminLang = $adminLang ?? (string)($config['app']['language'] ?? 'sv');
        $this->employees = $employees;
    }

    public function isEnabled(): bool {
        return $this->notifier->isEnabled();
    }

    /**
     * A new request was saved: receipt to the customer, alert to the business (if an address is set).
     * Returns whether the customer receipt was sent.
     */
    public function requestReceived(array $booking): bool {
        $notify = $this->notifier->notificationEmail();
        if ($notify !== null) {
            $this->notifier->sendAdminAlert($notify, $this->adminVars($booking), $this->adminLang);
        }
        return $this->notifier->sendBookingReceived((string)($booking['customer_email'] ?? ''), $this->vars($booking, $this->lang($booking)), $this->lang($booking));
    }

    /**
     * The customer cancelled with their link: alert to the business (if an address is set).
     */
    public function cancelledByCustomer(array $booking): bool {
        $notify = $this->notifier->notificationEmail();
        return $notify !== null && $this->notifier->sendAdminCancelled($notify, $this->adminVars($booking), $this->adminLang);
    }

    /**
     * Reminder before an approved appointment. $price and $currency as shown to the customer.
     */
    public function reminder(array $booking, string $price, string $currency): bool {
        return $this->notifier->sendReminder((string)($booking['customer_email'] ?? ''),
            $this->vars($booking, $this->lang($booking)) + ['price' => $price, 'currency' => $currency], $this->lang($booking));
    }

    /**
     * The admin approved or rejected the request. Returns whether the email was sent.
     */
    public function decided(array $booking, bool $approved): bool {
        $vars = $this->vars($booking, $this->lang($booking));
        if (!$approved) {
            unset($vars['cancelUrl']); // nothing left to cancel
        }
        $ics = $approved
            ? CalendarFeed::single($booking, $vars['service'] . ' – ' . $vars['business'], ResourceProvider::getTimezone(), null, ContactCard::location($this->settings))
            : null;
        // Contact cards for the venue and, if settings and the employee allow, the employee
        $vcard = $approved ? ContactCard::forBooking($this->settings, $this->employee($booking), __t('email.vcardLanguages', [], $this->lang($booking))) : null;
        return $this->notifier->sendBookingDecision((string)($booking['customer_email'] ?? ''), $approved, $vars, $this->lang($booking), $ics, $vcard);
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

    /** Text variables for $lang; 'service' names the employee too when the booking has one. */
    private function vars(array $booking, ?string $lang): array {
        $serviceId = (string)($booking['service_id'] ?? '');
        $service = ResourceProvider::getServiceById($serviceId) ?? [];
        $business = trim((string)($this->settings['business_name'] ?? ''));
        $siteUrl = self::siteUrl($this->settings);
        // Names are single-line already (Input::text); this keeps the email layout intact for older records
        $oneLine = fn ($value) => trim(str_replace(["\r", "\n"], ' ', (string)$value));
        $serviceName = $oneLine($service['name'] ?? $serviceId);
        $employee = $oneLine($this->employeeName($booking));

        return [
            'name' => $oneLine($booking['customer_name'] ?? ''),
            'service' => $employee === '' ? $serviceName : __t('email.serviceWithEmployee', ['service' => $serviceName, 'employee' => $employee], $lang),
            'employee' => $employee,
            'date' => (string)($booking['date'] ?? ''),
            'time' => (string)($booking['time'] ?? ''),
            'end' => (string)($booking['end_time'] ?? ''),
            'business' => $business !== '' ? $business : 'Book Me Calendar',
            'siteUrl' => $siteUrl,
            'privacyUrl' => $siteUrl !== '' ? $siteUrl . 'privacy.php' : '',
            'cancelUrl' => CancelLink::url($siteUrl, $booking),
            'hours' => ResourceProvider::cancelCutoffHours(),
        ];
    }

    /** Variables for alerts to the business: the customer's email and a link to the bookings page. */
    private function adminVars(array $booking): array {
        $vars = $this->vars($booking, $this->adminLang);
        unset($vars['cancelUrl'], $vars['privacyUrl']);
        $vars['email'] = (string)($booking['customer_email'] ?? '');
        if ($vars['siteUrl'] !== '') {
            $vars['adminUrl'] = $vars['siteUrl'] . 'admin/pending.php';
        }
        return $vars;
    }

    /** The employee taking the booking, null when none is assigned or the employee is gone. */
    private function employee(array $booking): ?array {
        $id = $booking['employee_id'] ?? null;
        if (!is_string($id) || $id === '') {
            return null;
        }
        $this->employees ??= (new EmployeeRepository())->getAll();
        return is_array($this->employees[$id] ?? null) ? $this->employees[$id] : null;
    }

    /** Name of the employee taking the booking, '' when none is assigned or the employee is gone. */
    private function employeeName(array $booking): string {
        $employee = $this->employee($booking);
        return $employee === null ? '' : Staff::name($employee);
    }

    private function lang(array $booking): ?string {
        $lang = $booking['language'] ?? null;
        return is_string($lang) && $lang !== '' ? $lang : null;
    }
}
