<h1><?= e(__t('booking.page.heading')) ?></h1>

<div id="alertBox" class="alert" role="status" hidden></div>

<form id="bookingForm" novalidate>
    <div class="form-group">
        <label for="serviceSelect"><?= e(__t('booking.service.label')) ?></label>
        <select id="serviceSelect" required>
            <option value=""><?= e(__t('booking.service.loading')) ?></option>
        </select>
    </div>

    <div class="form-group">
        <span class="label"><?= e(__t('booking.slots.title')) ?></span>
        <p class="muted"><?= e(__t('booking.slots.instructions')) ?></p>
        <div class="week-nav">
            <button type="button" id="prevWeek" class="nav-btn" aria-label="<?= e(__t('booking.week.previous')) ?>">&lsaquo;</button>
            <span id="weekLabel" class="week-label"></span>
            <button type="button" id="nextWeek" class="nav-btn" aria-label="<?= e(__t('booking.week.next')) ?>">&rsaquo;</button>
            <input type="date" id="dateInput" value="<?= e($today) ?>" min="<?= e($today) ?>" aria-label="<?= e(__t('booking.week.jump')) ?>">
        </div>
        <div id="slotsContainer" class="week-view"><span class="muted"><?= e(__t('booking.slots.selectFirst')) ?></span></div>
        <div class="legend">
            <span><i class="swatch swatch-free"></i><?= e(__t('booking.timeline.free')) ?></span>
            <span><i class="swatch swatch-booked"></i><?= e(__t('booking.timeline.booked')) ?></span>
            <span><i class="swatch swatch-past"></i><?= e(__t('booking.timeline.past')) ?></span>
        </div>
        <div class="selection-bar">
            <button type="button" id="earlierBtn" class="nav-btn" disabled><?= e(__t('booking.timeline.earlier')) ?></button>
            <output id="selectionText" class="selection-text" aria-live="polite"></output>
            <button type="button" id="laterBtn" class="nav-btn" disabled><?= e(__t('booking.timeline.later')) ?></button>
        </div>
    </div>

    <h2><?= e(__t('booking.customer.heading')) ?></h2>
    <div class="form-group">
        <label for="customerName"><?= e(__t('booking.customer.fullName')) ?></label>
        <input type="text" id="customerName" required maxlength="100" autocomplete="name">
    </div>
    <div class="form-group">
        <label for="customerEmail"><?= e(__t('booking.customer.email')) ?></label>
        <input type="email" id="customerEmail" required maxlength="254" autocomplete="email">
    </div>

    <div class="hp-field" aria-hidden="true">
        <label for="contactWebsite"><?= e(__t('booking.form.honeypot')) ?></label>
        <input type="text" id="contactWebsite" name="website" tabindex="-1" autocomplete="off">
    </div>

    <p class="privacy-notice"><?= e(__t('booking.form.privacyNotice')) ?> <a href="privacy.php"><?= e(__t('booking.form.privacyLink')) ?></a></p>

    <button type="submit" class="btn"><?= e(__t('booking.form.submit')) ?></button>
</form>

<script type="application/json" id="booking-config"><?= json_encode($clientConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="js/booking.js"></script>
