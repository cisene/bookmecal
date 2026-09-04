<?php
// booking.php - Clean UI Component with isolated DB calls & error feedback

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/gcal_helper.php';

// Date range calculation (4 weeks ahead)
$startDate = new DateTime('today');
$endDate   = (clone $startDate)->modify('+28 days');

// 1. Fetch data through DB abstraction layer in db.php
$services   = getActiveServices($pdo);
$dbBookings = getBookedSlotsInRange($pdo, $startDate, $endDate);

// Convert DB dates to timestamps
$dbBusyTimestamps = array_map(function($dt) {
    return (new DateTime($dt))->getTimestamp();
}, $dbBookings);

// 2. Fetch external Google Calendar busy intervals
$gcalBusySlots = getGoogleCalendarBusySlots($startDate, $endDate);

// Schedule Configuration
$openingHour   = 8;
$closingHour   = 17;
$slotDuration  = 30;
$lunchStart    = '12:00';
$lunchDuration = 30;

function isLunchSlot(DateTime $slotStart, string $lunchStartStr, int $lunchDurationMin): bool {
    $lunchStart = DateTime::createFromFormat('H:i', $lunchStartStr);
    $lunchStart->setDate((int)$slotStart->format('Y'), (int)$slotStart->format('m'), (int)$slotStart->format('d'));
    
    $lunchEnd = (clone $lunchStart)->modify("+{$lunchDurationMin} minutes");
    $slotEnd  = (clone $slotStart)->modify("+30 minutes");

    return ($slotStart < $lunchEnd && $slotEnd > $lunchStart);
}

function isSlotUnavailable(DateTime $slotStart, int $durationMinutes, array $dbTimestamps, array $gcalBusySlots): bool {
    $slotStartTS = $slotStart->getTimestamp();
    $slotEndTS   = $slotStartTS + ($durationMinutes * 60);

    if (in_array($slotStartTS, $dbTimestamps, true)) {
        return true;
    }

    foreach ($gcalBusySlots as $busy) {
        if ($slotStartTS < $busy['end'] && $slotEndTS > $busy['start']) {
            return true;
        }
    }

    return false;
}

// Retrieve any flash error messages or previous input from session
$bookingErrors = $_SESSION['booking_errors'] ?? [];
$formData      = $_SESSION['form_data'] ?? [];
unset($_SESSION['booking_errors'], $_SESSION['form_data']);
?>

<link rel="stylesheet" href="booking_modal.css">

<!-- Validation Error Banner -->
<?php if (!empty($bookingErrors)): ?>
    <div class="error-banner">
        <ul>
            <?php foreach ($bookingErrors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

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
                                $timeKey       = $slot->format('Y-m-d H:i');
                                $timeDisplay   = $slot->format('H:i');
                                $isLunch       = isLunchSlot($slot, $lunchStart, $lunchDuration);
                                $isUnavailable = isSlotUnavailable($slot, $slotDuration, $dbBusyTimestamps, $gcalBusySlots);

                                if ($isLunch): ?>
                                    <div class="slot slot-blocked slot-lunch" title="Lunchpaus">
                                        <span><?= $timeDisplay ?></span>
                                        <small>Lunch</small>
                                    </div>
                                <?php elseif ($isUnavailable): ?>
                                    <div class="slot slot-blocked" title="Ej valbar">
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

<!-- Modal Form -->
<div id="bookingModal" class="modal-backdrop" style="display: none;">
    <div class="modal-card">
        <button type="button" class="modal-close" id="closeModalBtn">&times;</button>
        <h2><?= htmlspecialchars(__t('heading')) ?></h2>
        
        <div class="selected-time-banner">
            <span id="displaySelectedDate"></span> kl. <strong id="displaySelectedTime"></strong>
        </div>

        <form id="bookingForm" action="submit_booking.php" method="POST">
            <!-- CSRF Protection Field -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
            
            <input type="hidden" name="start_datetime" id="inputStartDatetime">

            <div class="form-group">
                <label for="service_id"><?= htmlspecialchars(__t('service')) ?></label>
                <select name="service_id" id="service_id" required>
                    <option value=""><?= htmlspecialchars(__t('service')) ?>...</option>
                    <?php foreach ($services as $srv): ?>
                        <option value="<?= $srv['id'] ?>" <?= (isset($formData['service_id']) && $formData['service_id'] == $srv['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($srv['name']) ?> (<?= $srv['duration_minutes'] ?> min - <?= $srv['price'] ?> SEK)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="client_name"><?= htmlspecialchars(__t('full_name')) ?></label>
                <input type="text" id="client_name" name="client_name" required placeholder="Anna Andersson" value="<?= htmlspecialchars($formData['client_name'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="client_email"><?= htmlspecialchars(__t('email')) ?></label>
                <input type="email" id="client_email" name="client_email" required placeholder="anna@example.com" value="<?= htmlspecialchars($formData['client_email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="client_phone"><?= htmlspecialchars(__t('phone')) ?></label>
                <input type="tel" id="client_phone" name="client_phone" required placeholder="070-123 45 67" value="<?= htmlspecialchars($formData['client_phone'] ?? '') ?>">
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

    document.querySelectorAll('.js-slot-btn').forEach(button => {
        button.addEventListener('click', () => {
            displayDate.textContent = button.getAttribute('data-date');
            displayTime.textContent = button.getAttribute('data-time');
            hiddenDatetime.value    = button.getAttribute('data-full-datetime');
            modal.style.display     = 'flex';
        });
    });

    const closeModal = () => { modal.style.display = 'none'; };
    closeModalBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
});
</script>