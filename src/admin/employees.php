<?php
/**
 * src/admin/employees.php
 * Create, edit and delete employee profiles (data/employees/*.json), including which treatments
 * each employee offers (none ticked = all) and everything a contact card (vCard, ContactCard) can
 * carry. Weekly hours are set on the availability page.
 */

require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/classes/EmployeeRepository.php';
require_once dirname(__DIR__) . '/classes/Staff.php';

$repository = new EmployeeRepository();
// Single treatments only: an employee can do a set when they offer all of its treatments
$services = array_filter(ResourceProvider::getServices() ?? [], fn ($s) => is_array($s) && ResourceProvider::serviceItems($s) === []);
$flash = [];

/** Longest accepted text field, qualification list, URL and presentation, in characters. */
const EMPLOYEE_TEXT_MAX = 100;
const EMPLOYEE_QUALIFICATIONS_MAX = 500;
const EMPLOYEE_URL_MAX = 500;
const EMPLOYEE_BIO_MAX = 1000;
/** Most languages and social profile links per employee. */
const EMPLOYEE_LIST_MAX = 10;

/** Text fields of the employee form, as named in specs/employee.schema.json. */
$textFields = ['givenName', 'familyName', 'additionalName', 'honorificPrefix', 'honorificSuffix', 'nickname',
    'email', 'phoneNumber', 'mobileNumber', 'jobTitle', 'role', 'website', 'avatar', 'organisationNumber', 'swishPaymentNumber'];

/** Splits a posted list on commas and line breaks; null if an item fails $check or there are too many. */
function employee_list(string $raw, callable $check): ?array {
    $items = array_values(array_filter(array_map('trim', preg_split('/[,\r\n]+/', $raw) ?: [])));
    if (count($items) > EMPLOYEE_LIST_MAX) {
        return null;
    }
    foreach ($items as $item) {
        if ($check($item) === null) {
            return null;
        }
    }
    return $items;
}

if (is_post()) {
    require_csrf();
    $action = request_string($_POST, 'action');
    $employeeId = request_string($_POST, 'id');

    if ($action === 'save_employee') {
        $fields = [];
        $invalid = [];
        foreach ($textFields as $field) {
            $raw = request_string($_POST, $field);
            $fields[$field] = $raw === '' ? '' : match ($field) {
                'email' => Input::email($raw),
                'phoneNumber', 'mobileNumber', 'swishPaymentNumber' => Input::phone($raw),
                'website', 'avatar' => Input::url($raw, EMPLOYEE_URL_MAX),
                default => Input::text($raw, EMPLOYEE_TEXT_MAX),
            };
            if ($fields[$field] === null) {
                $invalid[] = $field;
            }
        }
        $qualifications = Input::text(request_string($_POST, 'qualifications'), EMPLOYEE_QUALIFICATIONS_MAX);
        $fields['qualifications'] = array_values(array_filter(array_map('trim', explode(',', (string)$qualifications))));
        // Only treatments that exist; an empty list means the employee offers all of them
        $fields['services'] = array_values(array_intersect(array_map('strval', array_keys($services)), array_filter(request_array($_POST, 'services'), 'is_string')));
        $fields['isBookable'] = request_string($_POST, 'isBookable') === '1';
        $fields['inactive'] = request_string($_POST, 'inactive') === '1';
        // The employee's contact card goes to customers only with the employee's consent
        $fields['shareContact'] = request_string($_POST, 'shareContact') === '1';
        $fields['languages'] = employee_list(request_string($_POST, 'languages'), fn ($v) => Input::text($v, 30));
        $fields['socialProfiles'] = employee_list(request_string($_POST, 'socialProfiles'), fn ($v) => Input::url($v, EMPLOYEE_URL_MAX));
        $fields['bio'] = Input::text(request_string($_POST, 'bio'), EMPLOYEE_BIO_MAX, true);
        $department = Input::text(request_string($_POST, 'department'), EMPLOYEE_TEXT_MAX);
        foreach (['qualifications' => $qualifications, 'languages' => $fields['languages'], 'socialProfiles' => $fields['socialProfiles'], 'bio' => $fields['bio'], 'department' => $department] as $field => $value) {
            if ($value === null) {
                $invalid[] = $field;
            }
        }
        // Department and photo choice live in businessAttributes; keep its other entries
        $stored = $employeeId !== '' ? ($repository->getAll()[$employeeId] ?? []) : [];
        $fields['businessAttributes'] = array_merge(is_array($stored['businessAttributes'] ?? null) ? $stored['businessAttributes'] : [], [
            'department' => (string)$department,
            'showPhoto' => request_string($_POST, 'showPhoto') === '1',
        ]);

        if ($invalid) {
            $flash[] = flash_error('Invalid value in: ' . implode(', ', $invalid) . '. Emails must be valid, phone numbers may hold digits, spaces and + - ( ), web addresses must start with http:// or https://, text at most ' . EMPLOYEE_TEXT_MAX . ' characters, at most ' . EMPLOYEE_LIST_MAX . ' languages or links.');
        } elseif ($fields['givenName'] === '' && $fields['familyName'] === '') {
            $flash[] = flash_error('Enter a given name or a family name.');
        } else {
            $employeeId = $employeeId !== '' ? $employeeId : EmployeeRepository::newId();
            if ($repository->save($employeeId, $fields, $currentAdmin)) {
                log_audit_action($currentAdmin, 'EMPLOYEE_SAVE', "Saved employee record: {$employeeId}");
                header('Location: employees.php?done=saved');
                exit;
            }
            $flash[] = flash_error('Could not save the employee. Check the ID and file permissions in data/employees/.');
        }
    } elseif ($action === 'delete_employee') {
        if ($repository->delete($employeeId)) {
            log_audit_action($currentAdmin, 'EMPLOYEE_DELETE', "Deleted employee record: {$employeeId}");
            header('Location: employees.php?done=deleted');
            exit;
        }
        $flash[] = flash_error('Employee not found.');
    }
}

$done = request_string($_GET, 'done');
if (in_array($done, ['saved', 'deleted'], true)) {
    $flash[] = flash_success('admin.employees.done.' . $done);
}

$rows = [];
foreach ($repository->getAll() as $id => $employee) {
    $editValues = ['id' => $id];
    foreach ($textFields as $field) {
        $editValues[$field] = (string)($employee[$field] ?? '');
    }
    $editValues['qualifications'] = implode(', ', (array)($employee['qualifications'] ?? []));
    $editValues['services'] = array_values(array_map('strval', (array)($employee['services'] ?? [])));
    $editValues['isBookable'] = (bool)($employee['isBookable'] ?? true);
    $editValues['inactive'] = (bool)($employee['inactive'] ?? false);
    $editValues['shareContact'] = ($employee['shareContact'] ?? false) === true;
    $editValues['languages'] = implode(', ', array_map('strval', (array)($employee['languages'] ?? [])));
    $editValues['socialProfiles'] = implode("\n", array_map('strval', (array)($employee['socialProfiles'] ?? [])));
    $editValues['bio'] = (string)($employee['bio'] ?? '');
    $editValues['department'] = (string)($employee['businessAttributes']['department'] ?? '');
    $editValues['showPhoto'] = ($employee['businessAttributes']['showPhoto'] ?? false) === true;

    $rows[$id] = [
        'name' => trim(($employee['givenName'] ?? '') . ' ' . ($employee['familyName'] ?? '')),
        'email' => $employee['email'] ?? '',
        'jobTitle' => $employee['jobTitle'] ?? '',
        'qualifications' => $editValues['qualifications'],
        'services' => array_map(fn ($key) => (string)($services[$key]['name'] ?? $key), $editValues['services']),
        'isBookable' => $editValues['isBookable'],
        'inactive' => $editValues['inactive'],
        'editValues' => $editValues,
    ];
}

render_admin('employees', [
    'title' => 'admin.employees.title',
    'flash' => $flash,
    'rows' => $rows,
    'services' => array_map(fn ($service) => (string)($service['name'] ?? ''), $services),
]);
