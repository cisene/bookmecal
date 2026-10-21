<?php
/**
 * src/admin/employees.php
 * Create, edit and delete employee profiles (data/employees/*.json).
 */

require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/classes/EmployeeRepository.php';

$repository = new EmployeeRepository();
$flash = [];

/** Text fields of the employee form, as named in specs/employee.schema.json. */
$textFields = ['givenName', 'familyName', 'email', 'phoneNumber', 'mobileNumber', 'jobTitle', 'organisationNumber', 'swishPaymentNumber'];

if (is_post()) {
    require_csrf();
    $action = request_string($_POST, 'action');
    $employeeId = request_string($_POST, 'id');

    if ($action === 'save_employee') {
        $fields = [];
        foreach ($textFields as $field) {
            $fields[$field] = request_string($_POST, $field);
        }
        $fields['qualifications'] = array_values(array_filter(array_map('trim', explode(',', request_string($_POST, 'qualifications')))));
        $fields['isBookable'] = request_string($_POST, 'isBookable') === '1';
        $fields['inactive'] = request_string($_POST, 'inactive') === '1';

        if ($fields['givenName'] === '' && $fields['familyName'] === '') {
            $flash[] = flash_error('Enter a given name or a family name.');
        } elseif ($fields['email'] !== '' && filter_var($fields['email'], FILTER_VALIDATE_EMAIL) === false) {
            $flash[] = flash_error('Invalid email address.');
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
    $editValues['isBookable'] = (bool)($employee['isBookable'] ?? true);
    $editValues['inactive'] = (bool)($employee['inactive'] ?? false);

    $rows[$id] = [
        'name' => trim(($employee['givenName'] ?? '') . ' ' . ($employee['familyName'] ?? '')),
        'email' => $employee['email'] ?? '',
        'jobTitle' => $employee['jobTitle'] ?? '',
        'qualifications' => $editValues['qualifications'],
        'isBookable' => $editValues['isBookable'],
        'inactive' => $editValues['inactive'],
        'editValues' => $editValues,
    ];
}

render_admin('employees', [
    'title' => 'admin.employees.title',
    'flash' => $flash,
    'rows' => $rows,
]);
