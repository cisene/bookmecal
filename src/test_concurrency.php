<?php
// test_concurrency.php - Concurrency and race condition verification test suite

if (php_sapi_name() !== 'cli') {
    die("This test script must be run from the command line (CLI).\n");
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/BookingRepository.php';

$dataDir = __DIR__ . '/data';
$bookingsFile = $dataDir . '/bookings.json';

echo "=== Book Me Calendar Concurrency & File-Locking Test ===\n";

// Reset bookings.json for clean test baseline
$initialBookings = [
    [
        'id' => 1,
        'client_name' => 'Base Client',
        'client_email' => 'base@example.com',
        'start_datetime' => '2026-10-15 09:00:00',
        'end_datetime' => '2026-10-15 10:00:00',
        'status' => 'approved'
    ]
];
file_put_contents($bookingsFile, json_encode($initialBookings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$numProcesses = 8;
echo "Spawning {$numProcesses} parallel worker processes to simulate simultaneous writes...\n";

$workers = [];
for ($i = 0; $i < $numProcesses; $i++) {
    $workerScript = __DIR__ . "/worker_{$i}.php";
    $code = sprintf(
        "<?php\n" .
        "require_once %s;\n" .
        "require_once %s;\n" .
        "\$repo = new BookingRepository();\n" .
        "\$repo->createBooking([\n" .
        "    'client_name' => 'Worker %d',\n" .
        "    'client_email' => 'worker%d@example.com',\n" .
        "    'start_datetime' => '2026-10-15 %d:00:00',\n" .
        "    'end_datetime' => '2026-10-15 %d:00:00',\n" .
        "    'status' => 'pending'\n" .
        "]);\n",
        var_export(__DIR__ . '/config.php', true),
        var_export(__DIR__ . '/BookingRepository.php', true),
        $i,
        $i,
        10 + $i,
        11 + $i
    );
    file_put_contents($workerScript, $code);

    // Launch background process
    $cmd = 'php ' . escapeshellarg($workerScript) . ' > /dev/null 2>&1 &';
    $workers[$i] = $workerScript;
    exec($cmd);
}

// Give background workers time to finish executing
sleep(3);

// Cleanup temporary worker scripts
foreach ($workers as $workerScript) {
    if (file_exists($workerScript)) {
        @unlink($workerScript);
    }
}

// Verify results
echo "Verifying integrity of bookings.json...\n";
if (!file_exists($bookingsFile)) {
    echo "[FAIL] bookings.json is missing!\n";
    exit(1);
}

$content = file_get_contents($bookingsFile);
$bookings = json_decode($content, true);

if ($bookings === null) {
    echo "[FAIL] JSON corruption detected! bookings.json is malformed.\n";
    exit(1);
}

$totalBookings = count($bookings);
// Expected: 1 initial booking + $numProcesses worker bookings
$expectedCount = 1 + $numProcesses;

echo "Total records found in bookings.json: {$totalBookings} (Expected: {$expectedCount})\n";

if ($totalBookings === $expectedCount) {
    echo "[PASS] Concurrency test passed successfully! File locking prevented race conditions and data loss.\n";
} else {
    echo "[FAIL] Record count mismatch. Some concurrent writes may have been lost.\n";
    exit(1);
}