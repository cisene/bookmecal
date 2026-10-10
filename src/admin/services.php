<?php
// src/admin/services.php - Services management with editable SKUs, active/deactivated states, audit logging, and sorting
require_once __DIR__ . '/auth.php';

$dataDir = dirname(__DIR__) . '/data';
$servicesFile = $dataDir . '/services.json';

if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0775, true);
}

$services = array();
if (file_exists($servicesFile)) {
    $decoded = json_decode(file_get_contents($servicesFile), true);
    if (is_array($decoded)) { $services = $decoded; }
}

$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        exit('CSRF verification failed.');
    }

    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'save_service') {
        $originalSku = trim(isset($_POST['original_sku']) ? $_POST['original_sku'] : '');
        $newSku = trim(isset($_POST['sku']) ? $_POST['sku'] : '');
        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
        $description = trim(isset($_POST['description']) ? $_POST['description'] : '');
        $duration = max(5, (int)(isset($_POST['duration']) ? $_POST['duration'] : 30));
        $vatRate = max(0.0, (float)(isset($_POST['vat_rate']) ? $_POST['vat_rate'] : 25.0));
        
        $priceExclVat = max(0.0, (float)(isset($_POST['price_excl_vat']) ? $_POST['price_excl_vat'] : 0.0));
        $priceInclVat = max(0.0, (float)(isset($_POST['price_incl_vat']) ? $_POST['price_incl_vat'] : 0.0));

        if ($priceExclVat > 0 && $priceInclVat === 0.0) {
            $priceInclVat = round($priceExclVat * (1 + ($vatRate / 100)), 2);
        } elseif ($priceInclVat > 0 && $priceExclVat === 0.0) {
            $priceExclVat = round($priceInclVat / (1 + ($vatRate / 100)), 2);
        }

        if ($newSku === '') {
            $errorMsg = 'Service SKU / Code is required.';
        } elseif ($name === '') {
            $errorMsg = 'Service name is required.';
        } else {
            // Check if SKU changed and already exists
            if ($originalSku !== $newSku && isset($services[$newSku])) {
                $errorMsg = 'A service with SKU "' . htmlspecialchars($newSku) . '" already exists.';
            } else {
                // Determine active state (preserve if editing, default true if new)
                $isActive = true;
                if ($originalSku !== '' && isset($services[$originalSku])) {
                    $isActive = isset($services[$originalSku]['is_active']) ? $services[$originalSku]['is_active'] : true;
                    // If SKU was changed, remove the old key entry
                    if ($originalSku !== $newSku) {
                        unset($services[$originalSku]);
                    }
                }

                $serviceData = array(
                    'sku' => $newSku,
                    'name' => $name,
                    'description' => $description,
                    'duration' => $duration,
                    'price_excl_vat' => $priceExclVat,
                    'price_incl_vat' => $priceInclVat,
                    'vat_rate' => $vatRate,
                    'is_active' => $isActive,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'updated_by' => $currentAdmin
                );

                $services[$newSku] = $serviceData;

                $fp = @fopen($servicesFile, 'c+b');
                if ($fp && @flock($fp, LOCK_EX)) {
                    ftruncate($fp, 0);
                    rewind($fp);
                    fwrite($fp, json_encode($services, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    fflush($fp);
                    flock($fp, LOCK_UN);
                    fclose($fp);
                }

                $logMsg = ($originalSku !== '' && $originalSku !== $newSku) 
                    ? "Renamed service SKU from '{$originalSku}' to '{$newSku}'" 
                    : "Added or updated service SKU: {$newSku}";
                
                log_audit_action($currentAdmin, 'SERVICE_SAVE', $logMsg);
                $successMsg = 'Service "' . htmlspecialchars($name) . '" saved successfully!';
                
                // Clear edit GET state after successful save
                $editSku = '';
            }
        }
    } elseif ($action === 'toggle_status') {
        $sku = trim(isset($_POST['sku']) ? $_POST['sku'] : '');
        if ($sku !== '' && isset($services[$sku])) {
            $currentState = isset($services[$sku]['is_active']) ? $services[$sku]['is_active'] : true;
            $newState = !$currentState;
            $services[$sku]['is_active'] = $newState;
            $services[$sku]['updated_at'] = date('Y-m-d H:i:s');
            $services[$sku]['updated_by'] = $currentAdmin;

            $fp = @fopen($servicesFile, 'c+b');
            if ($fp && @flock($fp, LOCK_EX)) {
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode($services, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                fflush($fp);
                flock($fp, LOCK_UN);
                fclose($fp);
            }

            $logAction = $newState ? 'SERVICE_REACTIVATE' : 'SERVICE_DEACTIVATE';
            log_audit_action($currentAdmin, $logAction, 'Changed status for service SKU: ' . $sku . ' to ' . ($newState ? 'Active' : 'Deactivated'));
            $successMsg = 'Service status updated successfully.';
        }
    } elseif ($action === 'delete_service') {
        $sku = trim(isset($_POST['sku']) ? $_POST['sku'] : '');
        if ($sku !== '' && isset($services[$sku])) {
            $isActive = isset($services[$sku]['is_active']) ? $services[$sku]['is_active'] : true;
            if ($isActive) {
                $errorMsg = 'Active services cannot be deleted. Please deactivate the service first.';
            } else {
                unset($services[$sku]);

                $fp = @fopen($servicesFile, 'c+b');
                if ($fp && @flock($fp, LOCK_EX)) {
                    ftruncate($fp, 0);
                    rewind($fp);
                    fwrite($fp, json_encode($services, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    fflush($fp);
                    flock($fp, LOCK_UN);
                    fclose($fp);
                }

                log_audit_action($currentAdmin, 'SERVICE_DELETE', 'Permanently deleted deactivated service SKU: ' . $sku);
                $successMsg = 'Deactivated service permanently deleted.';
            }
        }
    }
}

// Sort services: Active services at the top, deactivated at the bottom
uasort($services, function($a, $b) {
    $aActive = isset($a['is_active']) ? (int)$a['is_active'] : 1;
    $bActive = isset($b['is_active']) ? (int)$b['is_active'] : 1;
    if ($aActive === $bActive) {
        $aKey = isset($a['sku']) ? (string)$a['sku'] : '';
        $bKey = isset($b['sku']) ? (string)$b['sku'] : '';
        return strcmp($aKey, $bKey);
    }
    return $bActive - $aActive;
});

$editSku = isset($_GET['edit']) ? trim($_GET['edit']) : '';
$editService = ($editSku !== '' && isset($services[$editSku])) ? $services[$editSku] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Services Management - Admin</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 1100px; margin: 0 auto; }
        .card { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; background: #fff; }
        th, td { padding: 10px 12px; border: 1px solid #dee2e6; text-align: left; font-size: 14px; }
        th { background: #f8f9fa; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
        .form-group input, .form-group textarea { width: 100%; max-width: 500px; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .form-row { display: flex; gap: 20px; max-width: 500px; }
        .form-row .form-group { flex: 1; }
        .btn { background: #007bff; color: #fff; padding: 8px 14px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 13px; text-decoration: none; display: inline-block; }
        .btn-secondary { background: #6c757d; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        .badge-active { background: #d4edda; color: #155724; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; }
        .badge-inactive { background: #f8d7da; color: #721c24; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; }
        .alert { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    </style>
    <script>
        function calculateVat(source) {
            const vatRateInput = document.querySelector('input[name="vat_rate"]');
            const exclInput = document.querySelector('input[name="price_excl_vat"]');
            const inclInput = document.querySelector('input[name="price_incl_vat"]');

            const vatRate = parseFloat(vatRateInput.value) || 0;

            if (source === 'excl') {
                const excl = parseFloat(exclInput.value) || 0;
                const incl = excl * (1 + (vatRate / 100));
                inclInput.value = incl.toFixed(2);
            } else if (source === 'incl') {
                const incl = parseFloat(inclInput.value) || 0;
                const excl = vatRate >= 0 ? incl / (1 + (vatRate / 100)) : incl;
                exclInput.value = excl.toFixed(2);
            } else if (source === 'rate') {
                const excl = parseFloat(exclInput.value) || 0;
                const incl = excl * (1 + (vatRate / 100));
                inclInput.value = incl.toFixed(2);
            }
        }
    </script>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/header.php'; ?>

    <div class="card">
        <h2>Services Management</h2>
        <p>Define bookable services, unique SKUs/codes, durations, and pricing. Active services appear at the top.</p>

        <?php if (!empty($successMsg)): ?><div class="alert"><?php echo htmlspecialchars($successMsg); ?></div><?php endif; ?>
        <?php if (!empty($errorMsg)): ?><div class="error"><?php echo htmlspecialchars($errorMsg); ?></div><?php endif; ?>

        <h3>Active Services Catalog</h3>
        <?php if (empty($services)): ?>
            <p>No services defined yet. Create your first service below.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>SKU / Code</th>
                    <th>Service Name</th>
                    <th>Status</th>
                    <th>Duration</th>
                    <th>Price (Excl. VAT)</th>
                    <th>VAT</th>
                    <th>Price (Incl. VAT)</th>
                    <th>Actions</th>
                </tr>
                <?php foreach ($services as $skuKey => $srv): ?>
                    <?php 
                    $displaySku = isset($srv['sku']) ? (string)$srv['sku'] : (string)$skuKey;
                    $isActive = isset($srv['is_active']) ? $srv['is_active'] : true; 
                    ?>
                    <tr style="<?php echo $isActive ? '' : 'background: #fdfdfe; color: #6c757d;'; ?>">
                        <td><code><?php echo htmlspecialchars($displaySku); ?></code></td>
                        <td>
                            <strong><?php echo htmlspecialchars(isset($srv['name']) ? $srv['name'] : ''); ?></strong><br>
                            <small style="color: #666;"><?php echo htmlspecialchars(isset($srv['description']) ? $srv['description'] : ''); ?></small>
                        </td>
                        <td>
                            <?php if ($isActive): ?>
                                <span class="badge-active">Active</span>
                            <?php else: ?>
                                <span class="badge-inactive">Deactivated</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo (int)(isset($srv['duration']) ? $srv['duration'] : 30); ?> min</td>
                        <td><?php echo number_format((float)(isset($srv['price_excl_vat']) ? $srv['price_excl_vat'] : 0), 2); ?> kr</td>
                        <td><?php echo number_format((float)(isset($srv['vat_rate']) ? $srv['vat_rate'] : 25), 1); ?>%</td>
                        <td><strong><?php echo number_format((float)(isset($srv['price_incl_vat']) ? $srv['price_incl_vat'] : 0), 2); ?> kr</strong></td>
                        <td>
                            <div style="display: flex; gap: 5px; align-items: center; flex-wrap: wrap;">
                                <a href="services.php?edit=<?php echo urlencode($displaySku); ?>" class="btn btn-secondary">Edit</a>
                                
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="sku" value="<?php echo htmlspecialchars($displaySku); ?>">
                                    <?php if ($isActive): ?>
                                        <button type="submit" class="btn btn-warning" onclick="return confirm('Deactivate service <?php echo htmlspecialchars($displaySku); ?>?');">Deactivate</button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-success" onclick="return confirm('Reactivate service <?php echo htmlspecialchars($displaySku); ?>?');">Reactivate</button>
                                    <?php endif; ?>
                                </form>

                                <?php if (!$isActive): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Permanently delete deactivated service <?php echo htmlspecialchars($displaySku); ?>?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="action" value="delete_service">
                                        <input type="hidden" name="sku" value="<?php echo htmlspecialchars($displaySku); ?>">
                                        <button type="submit" class="btn btn-danger">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <h3 style="margin-top: 30px;"><?php echo ($editService !== null) ? 'Edit Service: ' . htmlspecialchars($editSku) : 'Add New Service'; ?></h3>
        <?php if ($editService !== null): ?>
            <p><a href="services.php" class="btn btn-secondary" style="margin-bottom: 15px;">&larr; Cancel Edit & Create New</a></p>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="save_service">
            <input type="hidden" name="original_sku" value="<?php echo htmlspecialchars($editSku); ?>">
            
            <div class="form-group">
                <label>Service SKU / Code</label>
                <input type="text" name="sku" required placeholder="e.g. 0 or SRV-CONSULT-01" value="<?php echo htmlspecialchars(($editService !== null) ? (isset($editService['sku']) ? $editService['sku'] : $editSku) : ''); ?>">
                <small style="color:#666;">You can edit the SKU code. Changing it will renumber or rename the service identifier.</small>
            </div>
            <div class="form-group">
                <label>Service Name</label>
                <input type="text" name="name" required placeholder="e.g. Initial Consultation" value="<?php echo htmlspecialchars(($editService !== null) ? (isset($editService['name']) ? $editService['name'] : '') : ''); ?>">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="2" placeholder="Brief description of the service..."><?php echo htmlspecialchars(($editService !== null) ? (isset($editService['description']) ? $editService['description'] : '') : ''); ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Duration (minutes)</label>
                    <input type="number" name="duration" value="<?php echo (int)(isset($editService['duration']) ? $editService['duration'] : 30); ?>" min="5" step="5" required>
                </div>
                <div class="form-group">
                    <label>VAT Rate (%)</label>
                    <input type="number" name="vat_rate" value="<?php echo (float)(isset($editService['vat_rate']) ? $editService['vat_rate'] : 25.0); ?>" min="0" step="0.1" required oninput="calculateVat('rate')">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Price Excl. VAT (kr)</label>
                    <input type="number" name="price_excl_vat" value="<?php echo (float)(isset($editService['price_excl_vat']) ? $editService['price_excl_vat'] : 0.00); ?>" min="0" step="0.01" oninput="calculateVat('excl')">
                </div>
                <div class="form-group">
                    <label>Price Incl. VAT (kr)</label>
                    <input type="number" name="price_incl_vat" value="<?php echo (float)(isset($editService['price_incl_vat']) ? $editService['price_incl_vat'] : 0.00); ?>" min="0" step="0.01" oninput="calculateVat('incl')">
                </div>
            </div>
            <small style="color: #666; display: block; margin-bottom: 15px;">* Typing in either Excl. or Incl. VAT price will automatically calculate the other value in real time.</small>

            <button type="submit" class="btn"><?php echo ($editService !== null) ? 'Update Service' : 'Save Service'; ?></button>
        </form>
    </div>
</div>
</body>
</html>