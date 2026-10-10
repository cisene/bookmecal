<?php
// src/admin/header.php - Unified administration navigation header
$currentPage = basename($_SERVER['PHP_SELF']);
$currentAdmin = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : 'Administrator';
?>
<div style="background: #fff; padding: 15px 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h1 style="margin: 0; font-size: 20px; color: #333;">Book Me Calendar Admin</h1>
        <small style="color: #666;">Logged in as: <strong><?php echo htmlspecialchars($currentAdmin); ?></strong></small>
    </div>
    <div class="nav" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
        <a href="pending.php" style="text-decoration: none; font-weight: bold; color: <?php echo $currentPage === 'pending.php' ? '#007bff' : '#495057'; ?>; padding: 5px 10px; border-radius: 4px; background: <?php echo $currentPage === 'pending.php' ? '#e7f5ff' : 'transparent'; ?>;">Pending Bookings</a>
        
        <a href="services.php" style="text-decoration: none; font-weight: bold; color: <?php echo $currentPage === 'services.php' ? '#007bff' : '#495057'; ?>; padding: 5px 10px; border-radius: 4px; background: <?php echo $currentPage === 'services.php' ? '#e7f5ff' : 'transparent'; ?>;">Services</a>
        
        <a href="config.php" style="text-decoration: none; font-weight: bold; color: <?php echo in_array($currentPage, array('config.php', 'setup_google.php', 'google_guide.php')) ? '#007bff' : '#495057'; ?>; padding: 5px 10px; border-radius: 4px; background: <?php echo in_array($currentPage, array('config.php', 'setup_google.php', 'google_guide.php')) ? '#e7f5ff' : 'transparent'; ?>;">Configuration</a>
        
        <a href="availability.php" style="text-decoration: none; font-weight: bold; color: <?php echo $currentPage === 'availability.php' ? '#007bff' : '#495057'; ?>; padding: 5px 10px; border-radius: 4px; background: <?php echo $currentPage === 'availability.php' ? '#e7f5ff' : 'transparent'; ?>;">Availability Slots</a>
        
        <a href="users.php" style="text-decoration: none; font-weight: bold; color: <?php echo $currentPage === 'users.php' ? '#007bff' : '#495057'; ?>; padding: 5px 10px; border-radius: 4px; background: <?php echo $currentPage === 'users.php' ? '#e7f5ff' : 'transparent'; ?>;">Users & Audit Log</a>
    </div>
</div>
<hr style="border: 0; border-top: 1px solid #dee2e6; margin-bottom: 25px;">