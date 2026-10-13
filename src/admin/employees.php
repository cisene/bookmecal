<?php
/**
 * src/admin/employees.php
 * Administrative panel for managing practitioner/employee profiles and business attributes 
 * backed by flat-file JSON storage and employee.schema.json validation.
 */
require_once __DIR__ . '/auth.php';

$dataDir = dirname(__DIR__) . '/data';
$employeesDir = $dataDir . '/employees';

if (!is_dir($employeesDir)) {
    @mkdir($employeesDir, 0775, true);
}

// Load all employee files
$employees = array();
$files = glob($employeesDir . '/emp_*.json');
if ($files) {
    foreach ($files as $file) {
        $content = file_get_contents($file);
        if ($content !== false) {
            $decoded = json_decode($content, true);
            if (is_array($decoded) && isset($decoded['id'])) {
                $employees[$decoded['id']] = $decoded;
            }
        }
    }
}

$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        exit('CSRF verification failed.');
    }

    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'save_employee') {
        $employeeId = trim(isset($_POST['employee_id']) ? $_POST['employee_id'] : '');
        $givenName = trim(isset($_POST['givenName']) ? $_POST['givenName'] : '');
        $familyName = trim(isset($_POST['familyName']) ? $_POST['familyName'] : '');
        $orgNumber = trim(isset($_POST['organisationNumber']) ? $_POST['organisationNumber'] : '');

        if (empty($givenName) || empty($familyName) || empty($orgNumber)) {
            $errorMsg = 'Given name, family name, and organisation number are required.';
        } else {
            if ($employeeId === '') {
                $employeeId = 'emp_' . time() . '_' . mt_rand(100, 999);
            }

            $employeeData = array(
                'id' => $employeeId,
                'givenName' => $givenName,
                'familyName' => $familyName,
                'avatar' => trim($_POST['avatar'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'phoneNumber' => trim($_POST['phoneNumber'] ?? ''),
                'mobileNumber' => trim($_POST['mobileNumber'] ?? ''),
                'swishPaymentNumber' => trim($_POST['swishPaymentNumber'] ?? ''),
                'inactive' => isset($_POST['inactive']),
                'isBookable' => isset($_POST['isBookable']),
                'jobTitle' => trim($_POST['jobTitle'] ?? ''),
                'organisationNumber' => $orgNumber,
                'qualifications' => array_filter(array_map('trim', explode("\n", $_POST['qualifications'] ?? ''))),
                'businessAttributes' => array(
                    'bookCancel' => (int)($_POST['bookCancel'] ?? 1440),
                    'bookInAdvance' => (int)($_POST['bookInAdvance'] ?? 15),
                    'acceptsValueCard' => isset($_POST['acceptsValueCard']),
                    'allowCustomerMessage' => isset($_POST['allowCustomerMessage']),
                    'blockCancelBooking' => isset($_POST['blockCancelBooking']),
                    'sellsGiftCard' => isset($_POST['sellsGiftCard']),
                    'sellsValueCard' => isset($_POST['sellsValueCard']),
                    'showPhoto' => isset($_POST['showPhoto']),
                    'useCashPayment' => isset($_POST['useCashPayment']),
                    'useCashPartialPayment' => isset($_POST['useCashPartialPayment']),
                    'useInvoicePayment' => isset($_POST['useInvoicePayment']),
                    'useSwishPayment' => isset($_POST['useSwishPayment']),
                    'visibleTimes' => isset($_POST['visibleTimes']),
                    'department' => trim($_POST['department'] ?? ''),
                    'location' => trim($_POST['location'] ?? ''),
                    'customNotes' => trim($_POST['customNotes'] ?? '')
                ),
                'updatedAt' => date('Y-m-d H:i:s'),
                'updatedBy' => $currentAdmin
            );

            $filePath = $employeesDir . '/' . $employeeId . '.json';
            $fp = @fopen($filePath, 'c+b');
            if ($fp && @flock($fp, LOCK_EX)) {
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode($employeeData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                fflush($fp);
                flock($fp, LOCK_UN);
                fclose($fp);
            }

            log_audit_action($currentAdmin, 'EMPLOYEE_SAVE', "Saved employee profile: {$givenName} {$familyName} ({$employeeId})");
            $successMsg = 'Employee profile saved successfully!';
            
            // Reload list
            $employees[$employeeId] = $employeeData;
        }
    } elseif ($action === 'delete_employee') {
        $employeeId = trim(isset($_POST['employee_id']) ? $_POST['employee_id'] : '');
        $filePath = $employeesDir . '/' . $employeeId . '.json';
        if ($employeeId !== '' && file_exists($filePath)) {
            @unlink($filePath);
            unset($employees[$employeeId]);
            log_audit_action($currentAdmin, 'EMPLOYEE_DELETE', "Deleted employee ID: {$employeeId}");
            $successMsg = 'Employee profile deleted successfully.';
        }
    }
}

$editId = isset($_GET['edit']) ? trim($_GET['edit']) : '';
$editEmployee = ($editId !== '' && isset($employees[$editId])) ? $employees[$editId] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employees & Practitioners - Admin</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 1100px; margin: 0 auto; }
        .card { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; background: #fff; }
        th, td { padding: 12px 15px; border: 1px solid #dee2e6; text-align: left; font-size: 14px; }
        th { background: #f8f9fa; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; max-width: 500px; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .form-row { display: flex; gap: 20px; max-width: 500px; }
        .form-row .form-group { flex: 1; }
        .checkbox-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; max-width: 800px; background: #f8f9fa; padding: 15px; border-radius: 6px; border: 1px solid #e9ecef; }
        .checkbox-label { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: normal; cursor: pointer; }
        .btn { background: #007bff; color: #fff; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-block; }
        .btn-secondary { background: #6c757d; }
        .btn-danger { background: #dc3545; padding: 6px 12px; font-size: 13px; }
        .badge { padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; }
        .badge-active { background: #d4edda; color: #155724; }
        .badge-inactive { background: #f8d7da; color: #721c24; }
        .alert { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/header.php'; ?>

    <div class="card">
        <h2>Practitioner & Employee Management</h2>
        <p>Manage staff profiles, Swish payout numbers, qualifications, and operational business attributes.</p>

        <?php if (!empty($successMsg)): ?><div class="alert"><?php echo htmlspecialchars($successMsg); ?></div><?php endif; ?>
        <?php if (!empty($errorMsg)): ?><div class="error"><?php echo htmlspecialchars($errorMsg); ?></div><?php endif; ?>

        <h3>Registered Employees</h3>
        <?php if (empty($employees)): ?>
            <p>No employees registered yet. Add your first practitioner below.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Name</th>
                    <th>Role / Title</th>
                    <th>Contact & Swish</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                <?php foreach ($employees as $emp): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars(($emp['givenName'] ?? '') . ' ' . ($emp['familyName'] ?? '')); ?></strong><br>
                            <small style="color: #666;">Org.nr: <?php echo htmlspecialchars($emp['organisationNumber'] ?? ''); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($emp['jobTitle'] ?? 'N/A'); ?></td>
                        <td>
                            <?php if (!empty($emp['mobileNumber'])): ?>📱 <?php echo htmlspecialchars($emp['mobileNumber']); ?><br><?php endif; ?>
                            <?php if (!empty($emp['swishPaymentNumber'])): ?><span style="color: #007bff; font-weight: bold;">Swish:</span> <?php echo htmlspecialchars($emp['swishPaymentNumber']); ?><?php endif; ?>
                        </td>
                        <td>
                            <?php if (empty($emp['inactive'])): ?>
                                <span class="badge badge-active">Active</span>
                            <?php else: ?>
                                <span class="badge badge-inactive">Inactive</span>
                            <?php endif; ?>
                            <?php if (!empty($emp['isBookable'])): ?><span class="badge" style="background:#e7f5ff; color:#007bff;">Bookable</span><?php endif; ?>
                        </td>
                        <td>
                            <a href="employees.php?edit=<?php echo urlencode($emp['id']); ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 13px;">Edit</a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete employee <?php echo htmlspecialchars($emp['givenName']); ?>?');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="action" value="delete_employee">
                                <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($emp['id']); ?>">
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <h3 style="margin-top: 30px;"><?php echo ($editEmployee !== null) ? 'Edit Employee: ' . htmlspecialchars($editEmployee['givenName'] . ' ' . $editEmployee['familyName']) : 'Add New Employee'; ?></h3>
        <?php if ($editEmployee !== null): ?>
            <p><a href="employees.php" class="btn btn-secondary" style="margin-bottom: 15px; font-size:13px;">&larr; Cancel Edit & Add New</a></p>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="save_employee">
            <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($editEmployee['id'] ?? ''); ?>">

            <div class="form-row">
                <div class="form-group">
                    <label>Given Name *</label>
                    <input type="text" name="givenName" required value="<?php echo htmlspecialchars($editEmployee['givenName'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Family Name *</label>
                    <input type="text" name="familyName" required value="<?php echo htmlspecialchars($editEmployee['familyName'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Organisation Number *</label>
                    <input type="text" name="organisationNumber" required placeholder="e.g. 556123-4567" value="<?php echo htmlspecialchars($editEmployee['organisationNumber'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Job Title</label>
                    <input type="text" name="jobTitle" placeholder="e.g. Medical Foot Care Specialist" value="<?php echo htmlspecialchars($editEmployee['jobTitle'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($editEmployee['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Mobile Number</label>
                    <input type="text" name="mobileNumber" placeholder="070-123 45 67" value="<?php echo htmlspecialchars($editEmployee['mobileNumber'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Swish Payment Number</label>
                    <input type="text" name="swishPaymentNumber" placeholder="123 123 45 67 eller 070..." value="<?php echo htmlspecialchars($editEmployee['swishPaymentNumber'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Avatar URL / Path</label>
                    <input type="text" name="avatar" value="<?php echo htmlspecialchars($editEmployee['avatar'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Qualifications (one per line)</label>
                <textarea name="qualifications" rows="3" placeholder="Licensed Foot Therapist&#10;Medical Pedicure Certified"><?php echo htmlspecialchars(implode("\n", $editEmployee['qualifications'] ?? array())); ?></textarea>
            </div>

            <h4 style="margin-top: 25px; border-bottom: 1px solid #dee2e6; padding-bottom: 6px;">Business & Operational Settings</h4>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Min cancellation time (minutes)</label>
                    <input type="number" name="bookCancel" value="<?php echo (int)($editEmployee['businessAttributes']['bookCancel'] ?? 1440); ?>">
                </div>
                <div class="form-group">
                    <label>Booking window ahead (days)</label>
                    <input type="number" name="bookInAdvance" value="<?php echo (int)($editEmployee['businessAttributes']['bookInAdvance'] ?? 15); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Operational Features & Permissions</label>
                <div class="checkbox-grid">
                    <?php 
                    $biz = $editEmployee['businessAttributes'] ?? array();
                    $checks = array(
                        'inactive' => array('Inactive / Deactivated', empty($editEmployee['inactive']) ? false : true),
                        'isBookable' => array('Bookable online', isset($editEmployee['isBookable']) ? $editEmployee['isBookable'] : true),
                        'useSwishPayment' => array('Accept Swish Payment', !empty($biz['useSwishPayment'])),
                        'useCashPayment' => array('Accept Cash Payment', !empty($biz['useCashPayment'])),
                        'useInvoicePayment' => array('Accept Invoice Payment', !empty($biz['useInvoicePayment'])),
                        'allowCustomerMessage' => array('Allow Customer Message', !empty($biz['allowCustomerMessage'])),
                        'blockCancelBooking' => array('Block Cancellation', !empty($biz['blockCancelBooking'])),
                        'sellsGiftCard' => array('Sells Gift Cards', !empty($biz['sellsGiftCard'])),
                        'sellsValueCard' => array('Sells Value Cards', !empty($biz['sellsValueCard'])),
                        'acceptsValueCard' => array('Accepts Value Cards', !empty($biz['acceptsValueCard'])),
                        'showPhoto' => array('Show Photo in Presentation', !empty($biz['showPhoto'])),
                        'visibleTimes' => array('Visible Times', !empty($biz['visibleTimes']))
                    );
                    foreach ($checks as $name => $info):
                    ?>
                        <label class="checkbox-label">
                            <input type="checkbox" name="<?php echo $name; ?>" value="1" <?php echo $info[1] ? 'checked' : ''; ?>>
                            <?php echo $info[0]; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="btn" style="margin-top: 15px;"><?php echo ($editEmployee !== null) ? 'Update Employee Profile' : 'Save Employee Profile'; ?></button>
        </form>
    </div>
</div>
</body>
</html>