<?php
// booking.php - Interactive JS-Driven Booking System
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/i18n.php';

// 1. Fetch active treatment services for the selector
$servicesStmt = $pdo->query("SELECT id, name, price, duration_minutes FROM services ORDER BY name ASC");
$services = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Fetch existing bookings for the next 28 days to mark slots as taken
$startDate = new DateTime('today');
$endDate   = (clone $startDate)->modify('+28 days');

$bookingsStmt = $pdo->prepare("
    SELECT start_datetime 
    FROM booking_requests 
    WHERE appointment_status IN ('approved', 'scheduled') 
      AND start_datetime BETWEEN ? AND ?
");
$bookingsStmt->execute([$startDate->format('Y-m-d 00:00:00'), $endDate->format('Y-m-d 23:59:59')]);
$existingBookings = $bookingsStmt->fetchAll(PDO::FETCH_COLUMN);

// Quick lookup array: ['YYYY-MM-DD HH:MM' => true]
$bookedLookup = [];
foreach ($existingBookings as $dt) {
    $bookedLookup[(new DateTime($dt))->format('Y-m-d H:i')] = true;
}

// Slot Schedule Configuration
$openingHour   = 8;   // 08:00
$closingHour   = 17;  // 17:00
$slotDuration  = 30;  // 30 min increments
$lunchStart    = '12:00';
$lunchDuration = 30;  // 30 mins lunch

function isLunchSlot(DateTime $slotStart, string $lunchStartStr, int $lunchDurationMin): bool {
    $lunchStart = DateTime::createFromFormat('H:i', $lunchStartStr);
    $lunchStart->setDate((int)$slotStart->format('Y'), (int)$slotStart->format('m'), (int)$slotStart->format('d'));
    
    $lunchEnd = (clone $lunchStart)->modify("+{$lunchDurationMin} minutes");
    $slotEnd  = (clone $slotStart)->modify("+30 minutes");

    return ($slotStart < $lunchEnd && $slotEnd > $lunchStart);
}
?>

<link rel="stylesheet" href="booking_modal.css">

<div class="calendar-container">
    <?php for ($w = 0; $w < 4; $w++): 
        $weekStart  = (clone $startDate)->modify("+$w weeks")->modify('monday this week');
        $weekNumber = $weekStart->format('W');
    ?>
        <div class="week-section">
            <div class="week-header">
                <h3>Vecka <?= $weekNumber ?></h3>
            </div>
            
            <div class="week-grid">
                <?php for ($d = 0; $d < 5; $d++): 
                    $currentDay   = (clone $weekStart)->modify("+$d days");
                    $dayFormatted = $currentDay->format('Y-m-d');
                ?>
                    <div class="day-column">
                        <div class="day-header">
                            <span class="day-name"><?= $currentDay->format('D') ?></span>
                            <span class="day-date"><?= $currentDay->format('d/m') ?></span>
                        </div>

                        <div class="slots-list">
                            <?php 
                            $slot   = clone $currentDay;
                            $slot->setTime($openingHour, 0);
                            $dayEnd = (clone $currentDay)->setTime($closingHour, 0);

                            while ($slot < $dayEnd):
                                $timeKey     = $slot->format('Y-m-d H:i');
                                $timeDisplay = $slot->format('H:i');
                                $isBooked    = isset($bookedLookup[$timeKey]);
                                $isLunch     = isLunchSlot($slot, $lunchStart, $lunchDuration);

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
                                    <button type="button" 
                                            class="slot slot-open js-slot-btn" 
                                            data-date="<?= $dayFormatted ?>" 
                                            data-time="<?= $timeDisplay ?>" 
                                            data-full-datetime="<?= $timeKey ?>">
                                        <span><?= $timeDisplay ?></span>
                                        <small>Ledig</small>
                                    </button>
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

<!-- Modal Dialog for Booking Form -->
<div id="bookingModal" class="modal-backdrop" style="display: none;">
    <div class="modal-card">
        <button type="button" class="modal-close" id="closeModalBtn">&times;</button>
        
        <h2><?= htmlspecialchars(__t('heading')) ?></h2>
        
        <!-- Slot Confirmation Banner -->
        <div class="selected-time-banner">
            <span id="displaySelectedDate"></span> kl. <strong id="displaySelectedTime"></strong>
        </div>

        <form id="bookingForm" action="submit_booking.php" method="POST">
            <!-- Hidden timestamp sent to server -->
            <input type="hidden" name="start_datetime" id="inputStartDatetime">

            <div class="form-group">
                <label for="service_id"><?= htmlspecialchars(__t('service')) ?></label>
                <select name="service_id" id="service_id" required>
                    <option value=""><?= htmlspecialchars(__t('service')) ?>...</option>
                    <?php foreach ($services as $srv): ?>
                        <option value="<?= $srv['id'] ?>">
                            <?= htmlspecialchars($srv['name']) ?> (<?= $srv['duration_minutes'] ?> min - <?= $srv['price'] ?> SEK)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="client_name"><?= htmlspecialchars(__t('full_name')) ?></label>
                <input type="text" id="client_name" name="client_name" required placeholder="Anna Andersson">
            </div>

            <div class="form-group">
                <label for="client_email"><?= htmlspecialchars(__t('email')) ?></label>
                <input type="email" id="client_email" name="client_email" required placeholder="anna@example.com">
            </div>

            <div class="form-group">
                <label for="client_phone"><?= htmlspecialchars(__t('phone')) ?></label>
                <input type="tel" id="client_phone" name="client_phone" required placeholder="070-123 45 67">
            </div>

            <div class="form-policy">
                <label class="checkbox-container">
                    <input type="checkbox" name="policy_accepted" value="1" required>
                    <span><?= htmlspecialchars(__t('policy_agree')) ?></span>
                </label>
            </div>

            <button type="submit" class="btn-submit"><?= htmlspecialchars(__t('submit_btn')) ?></button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal          = document.getElementById('bookingModal');
    const closeModalBtn  = document.getElementById('closeModalBtn');
    const displayDate    = document.getElementById('displaySelectedDate');
    const displayTime    = document.getElementById('displaySelectedTime');
    const hiddenDatetime = document.getElementById('inputStartDatetime');

    // Attach click listeners to all available slots
    document.querySelectorAll('.js-slot-btn').forEach(button => {
        button.addEventListener('click', () => {
            const dateStr = button.getAttribute('data-date');
            const timeStr = button.getAttribute('data-time');
            const fullDt  = button.getAttribute('data-full-datetime');

            displayDate.textContent = dateStr;
            displayTime.textContent = timeStr;
            hiddenDatetime.value    = fullDt;

            modal.style.display = 'flex';
        });
    });

    // Close modal handlers
    const closeModal = () => { modal.style.display = 'none'; };
    closeModalBtn.addEventListener('click', closeModal);
    
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
});
</script>