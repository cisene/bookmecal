<?php
/**
 * src/admin/services.php
 * Create, edit and delete bookable services (data/services.json).
 */

require_once __DIR__ . '/auth.php';

$servicesFile = ResourceProvider::dataPath('services.json');
$flash = [];

/**
 * Validates the posted service form; returns [fields, error message or null].
 */
function service_from_post(array $post): array {
    $fields = [
        'name' => request_string($post, 'name'),
        'sku' => request_string($post, 'sku'),
        'description' => request_string($post, 'description'),
        'duration' => (int)request_string($post, 'duration'),
        'cleanup' => (int)request_string($post, 'cleanup', '0'),
        'price_incl_vat' => (float)str_replace(',', '.', request_string($post, 'price_incl_vat', '0')),
        'vat_rate' => (float)str_replace(',', '.', request_string($post, 'vat_rate', '0')),
        'is_active' => request_string($post, 'is_active') === '1',
    ];

    if ($fields['name'] === '' || mb_strlen($fields['name']) > 255) {
        return [$fields, 'Service name is required (max 255 characters).'];
    }
    if ($fields['duration'] < 5) {
        return [$fields, 'Duration must be at least 5 minutes.'];
    }
    if ($fields['cleanup'] < 0 || $fields['price_incl_vat'] < 0 || $fields['vat_rate'] < 0 || $fields['vat_rate'] > 100) {
        return [$fields, 'Cleanup, price and VAT rate must be zero or positive (VAT at most 100).'];
    }

    $fields['price_excl_vat'] = round($fields['price_incl_vat'] / (1 + $fields['vat_rate'] / 100), 2);
    return [$fields, null];
}

if (is_post()) {
    require_csrf();
    $action = request_string($_POST, 'action');
    $serviceId = request_string($_POST, 'service_id');

    if ($action === 'save_service') {
        [$fields, $error] = service_from_post($_POST);
        if ($error !== null) {
            $flash[] = flash_error($error);
        } else {
            $serviceId = $serviceId !== '' ? $serviceId : 'srv_' . uniqid();
            $saved = JsonStore::update($servicesFile, function (array $services) use ($serviceId, $fields, $currentAdmin) {
                // Merge so fields this form does not edit survive a save
                $existing = is_array($services[$serviceId] ?? null) ? $services[$serviceId] : [];
                $services[$serviceId] = array_merge($existing, $fields, [
                    'updated_at' => date(DATE_ATOM),
                    'updated_by' => $currentAdmin,
                ]);
                return $services;
            });
            if ($saved) {
                log_audit_action($currentAdmin, 'SERVICE_SAVE', "Saved service record: {$serviceId}");
                header('Location: services.php?done=saved');
                exit;
            }
            $flash[] = flash_error('Could not write services.json. Check file permissions.');
        }
    } elseif ($action === 'delete_service' && $serviceId !== '') {
        JsonStore::update($servicesFile, function (array $services) use ($serviceId) {
            unset($services[$serviceId]);
            return $services;
        });
        log_audit_action($currentAdmin, 'SERVICE_DELETE', "Deleted service record: {$serviceId}");
        header('Location: services.php?done=deleted');
        exit;
    }
}

$done = request_string($_GET, 'done');
if (in_array($done, ['saved', 'deleted'], true)) {
    $flash[] = flash_success('admin.services.done.' . $done);
}

$rows = [];
foreach (array_filter(ResourceProvider::getServices() ?? [], 'is_array') as $id => $service) {
    $price = (float)($service['price_incl_vat'] ?? $service['price'] ?? 0);
    $isActive = ResourceProvider::isServiceActive($service);
    $rows[$id] = [
        'name' => $service['name'] ?? '',
        'sku' => $service['sku'] ?? '',
        'duration' => (int)($service['duration'] ?? 0),
        'cleanup' => ResourceProvider::serviceCleanup($service),
        'price' => $price,
        'isActive' => $isActive,
        // Values the Edit button copies into the form (see js/admin.js)
        'editValues' => [
            'service_id' => (string)$id,
            'name' => $service['name'] ?? '',
            'sku' => $service['sku'] ?? '',
            'duration' => $service['duration'] ?? '',
            'cleanup' => $service['cleanup'] ?? 0,
            'price_incl_vat' => $price,
            'vat_rate' => $service['vat_rate'] ?? 0,
            'description' => $service['description'] ?? '',
            'is_active' => $isActive,
        ],
    ];
}

render_admin('services', [
    'title' => 'admin.services.title',
    'flash' => $flash,
    'rows' => $rows,
]);
