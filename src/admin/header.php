<?php
// src/admin/header.php
require_once __DIR__ . '/auth.php';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h2 style="margin: 0 0 10px 0;">Admin Dashboard</h2>
        <div class="nav">
            <a href="pending.php" style="margin-right: 15px; text-decoration: none; font-weight: bold; color: <?php echo $currentPage === 'pending.php' ? '#495057' : '#007bff'; ?>;">Pending Bookings</a>
            <a href="config.php" style="margin-right: 15px; text-decoration: none; font-weight: bold; color: <?php echo $currentPage === 'config.php' ? '#495057' : '#007bff'; ?>;">Configuration</a>
            <a href="availability.php" style="text-decoration: none; font-weight: bold; color: <?php echo $currentPage === 'availability.php' ? '#495057' : '#007bff'; ?>;">Availability Slots</a>
        </div>
    </div>
    <div style="text-align: right; color: #666; font-size: 14px;">
        Logged in as: <strong><?php echo htmlspecialchars($currentAdmin); ?></strong>
    </div>
</div>