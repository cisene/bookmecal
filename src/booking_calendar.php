<?php
// booking_calendar.php - 4-Week Interactive Schedule Grid
require_once __DIR__ . '/i18n.php';

// Configuration Settings
$openingHour     = 8;  // 08:00
$closingHour     = 17; // 17:00
$slotDuration    = 30; // Minutes per slot

// Flexible Lunch Configuration
$lunchStart      = '12:00'; // Lunch starting time (HH:MM)
$lunchDuration   = 30;      // 20, 30, or 60 minutes

// 1. Fetch booked slots from DB for the 4-week window
$startDate = new DateTime('today');
$endDate   = (clone $startDate)->modify('+28 days');

$stmt = $pdo->prepare("
    SELECT start_datetime 
    FROM booking_requests 
    WHERE appointment_status IN ('approved', 'scheduled') 
      AND start_datetime BETWEEN ? AND ?
");
$stmt->execute([$startDate->format('Y-m-d 00:00:00'), $endDate->format('Y-m-d 23:59:59')]);
$existingBookings = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Convert fetched timestamps to a fast lookup array [YYYY-MM-DD HH:MM => true]
$bookedLookup = [];
foreach ($existingBookings as $dt) {
    $bookedLookup[(new DateTime($dt))->format('Y-m-d H:i')] = true;
}

// 2. Helper function to check if a slot falls during lunch
function isLunchSlot(DateTime $slotStart, string $lunchStartStr, int $lunchDurationMin): bool {
    $lunchStart = DateTime::createFromFormat('H:i', $lunchStartStr);
    $lunchStart->setDate((int)$slotStart->format('Y'), (int)$slotStart->format('m'), (int)$slotStart->format('d'));
    
    $lunchEnd = (clone $lunchStart)->modify("+{$lunchDurationMin} minutes");
    $slotEnd = (clone $slotStart)->modify("+30 minutes"); // standard slot step

    return ($slotStart < $lunchEnd && $slotEnd > $lunchStart);
}
?>

<div class="calendar-container">
    <?php 
    // Iterate through 4 weeks
    for ($w = 0; $w < 4; $w++): 
        $weekStart = (clone $startDate)->modify("+$w weeks")->modify('monday this week');
        $weekNumber = $weekStart->format('W');
    ?>
        <div class="week-section">
            <div class="week-header">
                <h3>Vecka <?= $weekNumber ?></h3>
            </div>
            
            <div class="week-grid">
                <?php 
                // Render Monday through Friday (5 workdays)
                for ($d = 0; $d < 5; $d++): 
                    $currentDay = (clone $weekStart)->modify("+$d days");
                    $dayFormatted = $currentDay->format('Y-m-d');
                ?>
                    <div class="day-column">
                        <div class="day-header">
                            <span class="day-name"><?= $currentDay->format('D') ?></span>
                            <span class="day-date"><?= $currentDay->format('d/m') ?></span>
                        </div>

                        <div class="slots-list">
                            <?php 
                            // Render daily time slots from opening to closing
                            $slot = clone $currentDay;
                            $slot->setTime($openingHour, 0);
                            $dayEnd = (clone $currentDay)->setTime($closingHour, 0);

                            while ($slot < $dayEnd):
                                $timeKey = $slot->format('Y-m-d H:i');
                                $timeDisplay = $slot->format('H:i');
                                
                                $isBooked = isset($bookedLookup[$timeKey]);
                                $isLunch  = isLunchSlot($slot, $lunchStart, $lunchDuration);

                                if ($isLunch): ?>
                                    <div class="slot slot-blocked slot-lunch" title="Lunchpaus">
                                        <span><?= $timeDisplay ?></span>
                                        <small>Lunch</small>
                                    </div>
                                <?php elseif ($isBooked): ?>
                                    <div class="slot slot-blocked slot-booked" title="Bokad">
                                        <span><?= $timeDisplay ?></span>
                                        <small>Bokad</small>
                                    </div>
                                <?php else: ?>
                                    <label class="slot slot-open">
                                        <input type="radio" name="selected_slot" value="<?= $timeKey ?>" required>
                                        <span><?= $timeDisplay ?></span>
                                        <small>Ledig</small>
                                    </label>
                                <?php endif; 

                                $slot->modify("+{$slotDuration} minutes");
                            endwhile; 
                            ?>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    <?php endfor; ?>
</div>