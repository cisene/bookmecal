<?php
/**
 * src/admin/services.php
 * Create, edit and delete bookable services (data/services.json).
 */

require_once __DIR__ . '/auth.php';

$servicesFile = ResourceProvider::dataPath('services.json');
$flash = [];

/** Field limits for the service form. */
const SERVICE_LIMITS = ['name' => 255, 'sku' => 64, 'description' => 2000, 'duration' => 720, 'cleanup' => 240, 'price' => 1000000];

/**
 * Validates the posted service form; returns [fields, error message or null].
 */
function service_from_post(array $post): array {
    $fields = [
        'name' => Input::text(request_string($post, 'name'), SERVICE_LIMITS['name']),
        'sku' => Input::text(request_string($post, 'sku'), SERVICE_LIMITS['sku']),
        'description' => Input::text(request_string($post, 'description'), SERVICE_LIMITS['description'], true),
        'duration' => Input::int(request_string($post, 'duration'), 5, SERVICE_LIMITS['duration']),
        'cleanup' => Input::int(request_string($post, 'cleanup') ?: '0', 0, SERVICE_LIMITS['cleanup']),
        'price_incl_vat' => Input::decimal(request_string($post, 'price_incl_vat') ?: '0', 0, SERVICE_LIMITS['price']),
        'vat_rate' => Input::decimal(request_string($post, 'vat_rate') ?: '0', 0, 100),
        'is_active' => request_string($post, 'is_active') === '1',
    ];

    if ($fields['name'] === null || $fields['name'] === '') {
        return [$fields, 'Service name is required (max ' . SERVICE_LIMITS['name'] . ' characters, no control characters).'];
    }
    if ($fields['sku'] === null || $fields['description'] === null) {
        return [$fields, 'SKU (max ' . SERVICE_LIMITS['sku'] . ') or description (max ' . SERVICE_LIMITS['description'] . ' characters) is too long or contains control characters.'];
    }
    if ($fields['duration'] === null) {
        return [$fields, 'Duration must be a whole number of minutes between 5 and ' . SERVICE_LIMITS['duration'] . '.'];
    }
    if ($fields['cleanup'] === null || $fields['price_incl_vat'] === null || $fields['vat_rate'] === null) {
        return [$fields, 'Cleanup (0-' . SERVICE_LIMITS['cleanup'] . ' minutes), price and VAT rate (0-100) must be valid numbers.'];
    }

    $fields['price_excl_vat'] = round($fields['price_incl_vat'] / (1 + $fields['vat_rate'] / 100), 2);
    return [$fields, null];
}

if (is_post()) {
    require_csrf();
    $action = request_string($_POST, 'action');
    $serviceId = request_string($_POST, 'service_id');

    if (Input::text($serviceId, 100) === null) {
        $flash[] = flash_error('Invalid service ID.');
    } elseif ($action === 'save_service') {
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
