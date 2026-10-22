<?php
/**
 * src/admin/services.php
 * Create, edit and delete bookable services (data/services.json): single treatments, which may be
 * exclusive (nothing else runs at the same time), and sets of treatments done back to back by one
 * employee, with their own price and the summed duration.
 */

require_once __DIR__ . '/auth.php';

$servicesFile = ResourceProvider::dataPath('services.json');
$flash = [];

/** Field limits for the service form. */
const SERVICE_LIMITS = ['name' => 255, 'sku' => 64, 'description' => 2000, 'duration' => 720, 'cleanup' => 240, 'price' => 1000000];

/**
 * Validates the posted service form; returns [fields, error message or null].
 * $services: all services, to check the treatments of a set; $serviceId: the edited one ('' = new).
 */
function service_from_post(array $post, array $services, string $serviceId): array {
    // Ticked treatments, in the order of the service list; only single treatments, never the set itself
    $posted = array_filter(request_array($post, 'items'), 'is_string');
    $items = [];
    foreach ($services as $key => $service) {
        $key = (string)$key;
        if ($key !== $serviceId && in_array($key, $posted, true) && ResourceProvider::serviceItems($service) === []) {
            $items[] = $key;
        }
    }

    $fields = [
        'name' => Input::text(request_string($post, 'name'), SERVICE_LIMITS['name']),
        'sku' => Input::text(request_string($post, 'sku'), SERVICE_LIMITS['sku']),
        'description' => Input::text(request_string($post, 'description'), SERVICE_LIMITS['description'], true),
        'duration' => Input::int(request_string($post, 'duration'), 5, SERVICE_LIMITS['duration']),
        'cleanup' => Input::int(request_string($post, 'cleanup') ?: '0', 0, SERVICE_LIMITS['cleanup']),
        'price_incl_vat' => Input::decimal(request_string($post, 'price_incl_vat') ?: '0', 0, SERVICE_LIMITS['price']),
        'vat_rate' => Input::decimal(request_string($post, 'vat_rate') ?: '0', 0, 100),
        'is_active' => request_string($post, 'is_active') === '1',
        'exclusive' => request_string($post, 'exclusive') === '1',
        'items' => $items,
    ];
    if ($items !== []) {
        // A set's duration and cleanup come from its treatments
        $fields['duration'] = null;
        $fields['cleanup'] = null;
    }

    if ($fields['name'] === null || $fields['name'] === '') {
        return [$fields, 'Service name is required (max ' . SERVICE_LIMITS['name'] . ' characters, no control characters).'];
    }
    if ($fields['sku'] === null || $fields['description'] === null) {
        return [$fields, 'SKU (max ' . SERVICE_LIMITS['sku'] . ') or description (max ' . SERVICE_LIMITS['description'] . ' characters) is too long or contains control characters.'];
    }
    if (count($items) === 1) {
        return [$fields, 'A set needs at least two treatments. Untick all of them for a single treatment.'];
    }
    if ($items === [] && $fields['duration'] === null) {
        return [$fields, 'Duration must be a whole number of minutes between 5 and ' . SERVICE_LIMITS['duration'] . '.'];
    }
    if ($items !== [] && ResourceProvider::serviceDuration($fields, $services) === null) {
        return [$fields, 'Every treatment in a set needs a duration.'];
    }
    if (($items === [] && $fields['cleanup'] === null) || $fields['price_incl_vat'] === null || $fields['vat_rate'] === null) {
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
        [$fields, $error] = service_from_post($_POST, array_filter(ResourceProvider::getServices() ?? [], 'is_array'), $serviceId);
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
        // A treatment used in a set stays until the set no longer includes it
        $usedIn = [];
        foreach (array_filter(ResourceProvider::getServices() ?? [], 'is_array') as $service) {
            if (in_array($serviceId, ResourceProvider::serviceItems($service), true)) {
                $usedIn[] = (string)($service['name'] ?? '');
            }
        }
        if ($usedIn) {
            $flash[] = ['type' => 'error', 'text' => __t('admin.services.error.inSet', ['sets' => implode(', ', $usedIn)])];
        } else {
            JsonStore::update($servicesFile, function (array $services) use ($serviceId) {
                unset($services[$serviceId]);
                return $services;
            });
            log_audit_action($currentAdmin, 'SERVICE_DELETE', "Deleted service record: {$serviceId}");
            header('Location: services.php?done=deleted');
            exit;
        }
    }
}

$done = request_string($_GET, 'done');
if (in_array($done, ['saved', 'deleted'], true)) {
    $flash[] = flash_success('admin.services.done.' . $done);
}

$services = array_filter(ResourceProvider::getServices() ?? [], 'is_array');
$rows = [];
foreach ($services as $id => $service) {
    $price = (float)($service['price_incl_vat'] ?? $service['price'] ?? 0);
    $isActive = ResourceProvider::isServiceActive($service);
    $items = ResourceProvider::serviceItems($service);
    $rows[$id] = [
        'name' => $service['name'] ?? '',
        'sku' => $service['sku'] ?? '',
        // null for a set whose treatments are missing: it cannot be booked
        'duration' => ResourceProvider::serviceDuration($service, $services),
        'cleanup' => ResourceProvider::serviceCleanup($service, $services),
        'price' => $price,
        'isActive' => $isActive,
        'exclusive' => ResourceProvider::isServiceExclusive($service, $services),
        'items' => array_map(fn ($key) => (string)($services[$key]['name'] ?? $key), $items),
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
            'exclusive' => ($service['exclusive'] ?? false) === true,
            'items' => $items,
        ],
    ];
}

render_admin('services', [
    'title' => 'admin.services.title',
    'flash' => $flash,
    'rows' => $rows,
    // Single treatments that can go into a set
    'treatments' => array_map(fn ($s) => (string)($s['name'] ?? ''), array_filter($services, fn ($s) => ResourceProvider::serviceItems($s) === [])),
]);
