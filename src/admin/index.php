<?php
/**
 * src/admin/index.php
 * Administrative Dual-Timeline Dashboard for resource and employee booking visualization.
 */
require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/classes/ResourceProvider.php';

ResourceProvider::initTimezone();
$settings = ResourceProvider::getSettings();
$currentTz = $settings['timezone'] ?? 'Europe/Stockholm';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dual-Timeline Dashboard - Admin</title>
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
        .container { max-width: 1200px; margin: 0 auto; }
        .card { background: var(--card-bg); padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; }
        h2 { font-size: 1.5rem; margin-top: 0; color: var(--primary-dark); margin-bottom: 1rem; }
        .timeline-grid { display: grid; grid-template-columns: 200px 1fr; border: 1px solid var(--border); border-radius: 6px; overflow: hidden; margin-top: 15px; }
        .timeline-header { background: #f8fafc; padding: 12px; font-weight: bold; border-bottom: 1px solid var(--border); border-right: 1px solid var(--border); }
        .timeline-slots { background: #fff; padding: 12px; border-bottom: 1px solid var(--border); display: flex; gap: 10px; align-items: center; overflow-x: auto; }
        .slot-badge { background: #e0f2fe; color: #0369a1; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem; font-family: monospace; white-space: nowrap; }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/header.php'; ?>

    <div class="card">
        <h2>Dual-Timeline Schedule & Resource Overview</h2>
        <p style="color: #64748b; margin-top: -5px;">Active Timezone: <strong><?php echo htmlspecialchars($currentTz); ?></strong> (24-Hour Strict Format)</p>

        <div class="timeline-grid">
            <div class="timeline-header">Employee / Resource</div>
            <div class="timeline-header">Timeline Schedule (08:00 - 18:00)</div>

            <div class="timeline-slots" style="font-weight: 600;">Primary Venue / Room A</div>
            <div class="timeline-slots">
                <span class="slot-badge">09:00 - 10:00 (Booked)</span>
                <span class="slot-badge">11:00 - 12:00 (Booked)</span>
                <span style="color: #94a3b8; font-size: 0.85rem; font-style: italic;">Available for reservation</span>
            </div>

            <div class="timeline-slots" style="font-weight: 600;">Staff Member 1</div>
            <div class="timeline-slots">
                <span class="slot-badge">08:30 - 11:30 (Shift)</span>
                <span class="slot-badge">13:00 - 17:00 (Shift)</span>
            </div>
        </div>
    </div>
</div>
</body>
</html>