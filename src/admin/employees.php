<?php
/**
 * src/admin/employees.php
 * Administrative panel for managing employees and professional profiles (`data/employees.json`),
 * incorporating credentials, payment attributes (Swish/Invoice), and strict NULL fallbacks.
 */
require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/classes/ResourceProvider.php';

ResourceProvider::initTimezone();

$employeesFile = dirname(__DIR__) . '/data/employees.json';
$successMessage = '';$errorMessage = '';

$employees = null;
if (file_exists($employeesFile)) {
    $content = file_get_contents($employeesFile);
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $content = substr($content, 3);
    }
    $decoded = json_decode($content, true);
    if (is_array($decoded)) {
        $employees =$decoded;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '',$_POST['csrf_token'])) {
        http_response_code(403);
        exit('CSRF verification failed.');
    }

    $action =$_POST['action'] ?? '';

    try {
        if ($action === 'save_employee') {
            $empId = trim($_POST['emp_id'] ?? '');
            if ($empId === '') {$empId = 'emp_' . uniqid();
            }

            $name = trim($_POST['name'] ?? '');$email = trim($_POST['email'] ?? '');$title = trim($_POST['title'] ?? '');$swishNumber = trim($_POST['swish_number'] ?? '');$acceptsInvoice = isset($_POST['accepts_invoice']) ? (bool)$_POST['accepts_invoice'] : null;
            $credentialsRaw = trim($_POST['credentials'] ?? '');
            
            // Parse comma-separated credentials into an array or null
            $credentials = null;
            if ($credentialsRaw !== '') {
                $credentials = array_values(array_filter(array_map('trim', explode(',',$credentialsRaw))));
                if (empty($credentials)) {$credentials = null;
                }
            }

            if (!is_array($employees)) {$employees = [];
            }

            $employees[$empId] = [
                'id' => $empId,
                'name' => $name !== '' ?$name : null,
                'email' => $email !== '' ?$email : null,
                'title' => $title !== '' ?$title : null,
                'swish_number' => $swishNumber !== '' ?$swishNumber : null,
                'accepts_invoice' => $acceptsInvoice,
                'credentials' => $credentials,
                'updated_at' => (new DateTime('now'))->format('Y-m-d\TH:i:sP')
            ];

            $jsonEncoded = json_encode($employees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            $dir = dirname($employeesFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            $fp = @fopen($employeesFile, 'c+b');
            if (!$fp \vert{}\vert{} !@flock($fp, LOCK_EX)) {
                throw new Exception('Failed to acquire file lock for employees.json.');
            }

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp,$jsonEncoded);
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);

            if (function_exists('log_audit_action')) {
                log_audit_action($currentAdmin ?? 'admin', 'EMPLOYEE_SAVE', "Saved employee record: {$empId}");
            }

            $successMessage = 'Employee successfully saved!';
        } elseif ($action === 'delete_employee') {
            $empId = trim($_POST['emp_id'] ?? '');
            if ($empId !== '' && isset($employees[$empId])) {
                unset($employees[$empId]);
                
                $jsonEncoded = json_encode($employees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $fp = @fopen($employeesFile, 'c+b');
                if ($fp && @flock($fp, LOCK_EX)) {
                    ftruncate($fp, 0);
                    rewind($fp);
                    fwrite($fp,$jsonEncoded);
                    fflush($fp);
                    flock($fp, LOCK_UN);
                    fclose($fp);
                }
                $successMessage = 'Employee successfully deleted.';
            }
        }
    } catch (Exception $e) {
        $errorMessage = 'An error occurred: ' .$e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management - Admin</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        :root {
            --primary: #0284c7;
            --primary-dark: #0369a1;
            --bg: #f4f7f6;
            --card-bg: #ffffff;
            --text: #1e293b;
            --border: #cbd5e1;
        }
        body { font-family: system-ui, sans-serif; background-color: var(--bg); color: var(--text); margin: 0; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .card { background: var(--card-bg); padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; }
        h2 { font-size: 1.5rem; margin-top: 0; color: var(--primary-dark); margin-bottom: 1.5rem; }
        .alert { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 5px; font-size: 0.9rem; }
        .form-group input[type="text"], .form-group input[type="email"] { width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box; }
        .btn { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; }
        .btn:hover { background: var(--primary-dark); }
        .btn-danger { background: #dc2626; }
        .btn-danger:hover { background: #b91c1c; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #f1f5f9; font-size: 0.95rem; }
        th { background: #f8fafc; font-weight: 600; }
        .null-val { color: #94a3b8; font-style: italic; }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/header.php'; ?>

    <div class="card">
        <h2>Employee & Professional Credentials Management</h2>
        <p style="color: #64748b; font-size: 0.9rem; margin-top: -10px; margin-bottom: 20px;">
            Manage staff profiles, Swish/invoice payment attributes, and credentials with strict NULL fallbacks.
        </p>

        <?php if (!empty($successMessage)): ?><div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div><?php endif; ?>
        <?php if (!empty($errorMessage)): ?><div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div><?php endif; ?>

        <!-- Employee Form -->
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <input type="hidden" name="action" value="save_employee">
            <input type="hidden" name="emp_id" id="emp_id" value="">

            <div class="form-grid">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" id="field_name" required placeholder="e.g. Anna Lindh">
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" id="field_email" placeholder="anna.lindh@example.se">
                </div>
                <div class="form-group">
                    <label>Professional Title</label>
                    <input type="text" name="title" id="field_title" placeholder="Senior Consultant">
                </div>
                <div class="form-group">
                    <label>Swish Number</label>
                    <input type="text" name="swish_number" id="field_swish" placeholder="123 456 78 90">
                </div>
                <div class="form-group">
                    <label>Credentials (comma separated)</label>
                    <input type="text" name="credentials" id="field_credentials" placeholder="MD, PhD, Authorized Advisor">
                </div>
                <div class="form-group" style="display: flex; align-items: center; padding-top: 25px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="accepts_invoice" id="field_invoice" value="1">
                        Accepts Direct Invoice
                    </label>
                </div>
            </div>

            <button type="submit" class="btn">Save Employee Profile</button>
        </form>
    </div>

    <!-- Employee List Table -->
    <div class="card">
        <h2>Existing Staff Profiles</h2>
        <?php if (empty($employees)): ?>
            <p class="null-val">No employee records found (NULL).</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Title</th>
                        <th>Swish</th>
                        <th>Invoice</th>
                        <th>Credentials</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $id =>$emp): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($emp['name'] ?? 'NULL'); ?></strong><br><small><?php echo htmlspecialchars($emp['email'] ?? ''); ?></small></td>
                            <td><?php echo htmlspecialchars($emp['title'] ?? 'NULL'); ?></td>
                            <td><?php echo htmlspecialchars($emp['swish_number'] ?? 'NULL'); ?></td>
                            <td><?php echo isset($emp['accepts_invoice']) ? ($emp['accepts_invoice'] ? 'Yes' : 'No') : '<span class="null-val">NULL</span>'; ?></td>
                            <td>
                                <?php if (!empty($emp['credentials']) && is_array($emp['credentials'])): ?>
                                    <?php echo htmlspecialchars(implode(', ', $emp['credentials'])); ?>
                                <?php else: ?>
                                    <span class="null-val">NULL</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this employee?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                    <input type="hidden" name="action" value="delete_employee">
                                    <input type="hidden" name="emp_id" value="<?php echo htmlspecialchars($id); ?>">
                                    <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 0.85rem;">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>