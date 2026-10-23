<?php
/**
 * src/admin/messages.php
 * Write an email to customers (CustomerMessages): one customer, the customers with upcoming
 * bookings (optionally for some dates, one employee or one treatment), or all customers with a
 * stored booking. The admin always sees the recipients and a sample first, then confirms.
 * Sent messages are listed below the form.
 */

require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/classes/CustomerMessages.php';
require_once dirname(__DIR__) . '/classes/EmployeeRepository.php';
require_once dirname(__DIR__) . '/classes/Staff.php';

$repository = new BookingRepository();
$repository->housekeeping();
$settings = ResourceProvider::getSettings() ?? [];
$messages = new CustomerMessages(new EmailNotifier(ResourceProvider::getConfig() ?? []), $settings);
$today = (new DateTimeImmutable('now', ResourceProvider::getTimezone()))->format('Y-m-d');
$flash = [];

$employees = [];
foreach ((new EmployeeRepository())->getAll() as $id => $employee) {
    $employees[$id] = Staff::name($employee);
}
$services = [];
foreach (ResourceProvider::getServices() ?? [] as $service) {
    if (isset($service['id'])) {
        $services[(string)$service['id']] = (string)($service['name'] ?? $service['id']);
    }
}

/**
 * The message from the posted form: [criteria, subject, text, error message or null].
 */
function message_from_post(array $post, array $employees, array $services): array {
    $criteria = [
        'group' => Input::oneOf(request_string($post, 'group'), CustomerMessages::GROUPS) ?? '',
        'email' => request_string($post, 'email'),
        'from' => request_string($post, 'from'),
        'to' => request_string($post, 'to'),
        'employee_id' => request_string($post, 'employee_id'),
        'service_id' => request_string($post, 'service_id'),
    ];
    $subject = Input::text(request_string($post, 'subject'), CustomerMessages::SUBJECT_MAX);
    $text = Input::text(request_string($post, 'text'), CustomerMessages::BODY_MAX, true);

    $error = match (true) {
        $criteria['group'] === '' => 'Choose who gets the message.',
        $criteria['group'] === 'one' && Input::email($criteria['email']) === null => 'Enter a valid email address.',
        ($criteria['from'] !== '' && Input::date($criteria['from']) === null) || ($criteria['to'] !== '' && Input::date($criteria['to']) === null) => 'Enter valid dates.',
        $criteria['from'] !== '' && $criteria['to'] !== '' && $criteria['to'] < $criteria['from'] => 'The last date is before the first.',
        $criteria['employee_id'] !== '' && !isset($employees[$criteria['employee_id']]) => 'Unknown employee.',
        $criteria['service_id'] !== '' && !isset($services[$criteria['service_id']]) => 'Unknown treatment.',
        $subject === null || $subject === '' => 'Write a subject (at most ' . CustomerMessages::SUBJECT_MAX . ' characters).',
        $text === null || trim($text) === '' => 'Write a message (at most ' . CustomerMessages::BODY_MAX . ' characters).',
        default => null,
    };
    // Only the fields of the chosen group count
    if ($criteria['group'] !== 'one') {
        $criteria['email'] = '';
    }
    if ($criteria['group'] !== 'upcoming') {
        $criteria = array_merge($criteria, ['from' => '', 'to' => '', 'employee_id' => '', 'service_id' => '']);
    }
    return [$criteria, (string)$subject, (string)$text, $error];
}

// A link from the bookings page opens the form for one customer
$form = ['group' => 'upcoming', 'email' => '', 'from' => '', 'to' => '', 'employee_id' => '', 'service_id' => '', 'subject' => '', 'text' => ''];
$prefill = Input::email(request_string($_GET, 'email'));
if ($prefill !== null) {
    $form = array_merge($form, ['group' => 'one', 'email' => $prefill]);
}
$preview = null;

if (is_post()) {
    require_csrf();
    $action = request_string($_POST, 'action');
    [$criteria, $subject, $text, $error] = message_from_post($_POST, $employees, $services);
    $form = array_merge($form, $criteria, ['subject' => $subject, 'text' => $text]);
    $recipients = $error === null ? CustomerMessages::recipients($repository->getBookings(), $criteria, $today) : [];

    if ($error !== null) {
        $flash[] = flash_error($error);
    } elseif ($recipients === []) {
        $flash[] = ['type' => 'error', 'text' => __t('admin.messages.noRecipients')];
    } elseif (count($recipients) > CustomerMessages::MAX_RECIPIENTS) {
        $flash[] = ['type' => 'error', 'text' => __t('admin.messages.tooMany', ['count' => count($recipients), 'max' => CustomerMessages::MAX_RECIPIENTS])];
    } elseif ($action === 'send' && $messages->isEnabled()) {
        // Keep going if the browser gives up waiting: a half-sent message is worse than a slow page
        ignore_user_abort(true);
        set_time_limit(0);
        $result = $messages->send($recipients, $subject, $text);
        $messages->record([
            'at' => date(DATE_ATOM),
            'by' => $currentAdmin,
            // Without the address: the list must not outlive an erasure request
            'criteria' => array_diff_key($criteria, ['email' => true]),
            'subject' => $subject,
            'text' => $text,
            'recipients' => count($recipients),
            'sent' => $result['sent'],
            'failed' => $result['failed'],
        ]);
        log_audit_action($currentAdmin, 'MESSAGE_SEND', "Message to {$criteria['group']}: {$result['sent']} sent, {$result['failed']} failed");
        if ($result['failed'] === 0) {
            header('Location: messages.php?done=sent&sent=' . $result['sent']);
            exit;
        }
        $flash[] = ['type' => 'error', 'text' => __t('admin.messages.failed', ['sent' => $result['sent'], 'failed' => $result['failed']])
            . ($result['error'] !== '' ? ' ' . $result['error'] : '')];
    } else {
        // Preview: the recipients and the email the first of them would get
        $first = reset($recipients);
        [$sampleSubject, $sampleBody] = $messages->compose($first, $subject, $text);
        $preview = ['recipients' => $recipients, 'subject' => $sampleSubject, 'body' => $sampleBody];
    }
}

if (request_string($_GET, 'done') === 'sent') {
    $flash[] = flash_success('admin.messages.done.sent', ['sent' => (int)request_string($_GET, 'sent')]);
}

render_admin('messages', [
    'title' => 'admin.messages.title',
    'flash' => $flash,
    'form' => $form,
    'preview' => $preview,
    'enabled' => $messages->isEnabled(),
    'employees' => $employees,
    'services' => $services,
    'today' => $today,
    'history' => $messages->history(),
    'subjectMax' => CustomerMessages::SUBJECT_MAX,
    'bodyMax' => CustomerMessages::BODY_MAX,
]);
