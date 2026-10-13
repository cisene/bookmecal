<?php
/**
 * src/admin/services.php
 * Administrative panel for managing services and SKUs (`data/services.json`),
 * incorporating strict NULL fallback evaluation and thread-safe file operations.
 */
require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/classes/ResourceProvider.php';

ResourceProvider::initTimezone();

$servicesFile = dirname(__DIR__) . '/data/services.json';
$successMessage = '';$errorMessage = '';

$services = null;
if (file_exists($servicesFile)) {
    $content = file_get_contents($servicesFile);
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $content = substr($content, 3);
    }
    $decoded = json_decode($content, true);
    if (is_array($decoded)) {
        $services =$decoded;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '',$_POST['csrf_token'])) {
        http_response_code(403);
        exit('CSRF verification failed.');
    }

    $action =$_POST['action'] ?? '';

    try {
        if ($action === 'save_service') {
            $serviceId = trim($_POST['service_id'] ?? '');
            if ($serviceId === '') {$serviceId = 'srv_' . uniqid();
            }

            $name = trim($_POST['name'] ?? '');
            $sku = trim($_POST['sku'] ?? '');
            $durationRaw = trim($_POST['duration_minutes'] ?? '');
            $priceRaw = trim($_POST['price'] ?? '');
            $description = trim($_POST['description'] ?? '');

            $durationMinutes = ($durationRaw !== '') ? (int)$durationRaw : null;
            $price = ($priceRaw !== '') ? (float)$priceRaw : null;

            if (!is_array($services)) {$services = [];
            }

            $services[$serviceId] = [
                'id' => $serviceId,
                'name' => $name !== '' ?$name : null,
                'sku' => $sku !== '' ?$sku : null,
                'duration_minutes' => $durationMinutes,
                'price' => $price,
                'description' => $description !== '' ?$description : null,
                'updated_at' => (new DateTime('now'))->format('Y-m-d\TH:i:sP')
            ];

            $jsonEncoded = json_encode($services, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            $dir = dirname($servicesFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            $fp = @fopen($servicesFile, 'c+b');
            if (!$fp \vert{}\vert{} !@flock($fp, LOCK_EX)) {
                throw new Exception('Failed to acquire file lock for services.json.');
            }

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp,$jsonEncoded);
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);

            if (function_exists('log_audit_action')) {
                log_audit_action($currentAdmin ?? 'admin', 'SERVICE_SAVE', "Saved service record: {$serviceId}");
            }

            $successMessage = 'Service successfully saved!';
        } elseif ($action === 'delete_service') {
            $serviceId = trim($_POST['service_id'] ?? '');
            if ($serviceId !== '' && isset($services[$serviceId])) {
                unset($services[$serviceId]);
                
                $jsonEncoded = json_encode($services, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $fp = @fopen($servicesFile, 'c+b');
                if ($fp && @flock($fp, LOCK_EX)) {
                    ftruncate($fp, 0);
                    rewind($fp);
                    fwrite($fp,$jsonEncoded);
                    fflush($fp);
                    flock($fp, LOCK_UN);
                    fclose($fp);
                }
                $successMessage = 'Service successfully deleted.';
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
    <title>Services Management - Admin</title>
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
        .form-group input[type="text"], .form-group input[type="number"], .form-group textarea { width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box; }
        textarea { resize: vertical; height: 80px; }
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
        <h2>Services & SKU Management</h2>
        <p style="color: #64748b; font-size: 0.9rem; margin-top: -10px; margin-bottom: 20px;">
            Configure bookable services, durations, pricing rules, and SKUs with strict NULL fallbacks.
        </p>

        <?php if (!empty($successMessage)): ?><div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div><?php endif; ?>
        <?php if (!empty($errorMessage)): ?><div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div><?php endif; ?>

        <!-- Service Form -->
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <input type="hidden" name="action" value="save_service">
            <input type="hidden" name="service_id" id="service_id" value="">

            <div class="form-grid">
                <div class="form-group">
                    <label>Service Name</label>
                    <input type="text" name="name" id="field_name" required placeholder="e.g. Initial Consultation">
                </div>
                <div class="form-group">
                    <label>SKU Code</label>
                    <input type="text" name="sku" id="field_sku" placeholder="CONS-01">
                </div>
                <div class="form-group">
                    <label>Duration (Minutes)</label>
                    <input type="number" name="duration_minutes" id="field_duration" min="1" placeholder="60">
                </div>
                <div class="form-group">
                    <label>Price</label>
                    <input type="number" step="0.01" name="price" id="field_price" placeholder="500.00">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label>Description</label>
                    <textarea name="description" id="field_description" placeholder="Detailed service description..."></textarea>
                </div>
            </div>

            <button type="submit" class="btn">Save Service</button>
        </form>
    </div>

    <!-- Service List Table -->
    <div class="card">
        <h2>Existing Services</h2>
        <?php if (empty($services)): ?>
            <p class="null-val">No service records found (NULL).</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Name / SKU</th>
                        <th>Duration</th>
                        <th>Price</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $id =>$srv): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($srv['name'] ?? 'NULL'); ?></strong><br>
                                <small style="color: #64748b;">SKU: <?php echo htmlspecialchars($srv['sku'] ?? 'NULL'); ?></small>
                            </td>
                            <td><?php echo isset($srv['duration_minutes']) ? htmlspecialchars($srv['duration_minutes']) . ' min' : '<span class="null-val">NULL</span>'; ?></td>
                            <td><?php echo isset($srv['price']) ? htmlspecialchars(number_format($srv['price'], 2)) : '<span class="null-val">NULL</span>'; ?></td>
                            <td><?php echo htmlspecialchars($srv['description'] ?? 'NULL'); ?></td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this service?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                    <input type="hidden" name="action" value="delete_service">
                                    <input type="hidden" name="service_id" value="<?php echo htmlspecialchars($id); ?>">
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