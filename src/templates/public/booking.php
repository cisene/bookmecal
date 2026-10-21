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
        <label for="dateInput"><?= e(__t('booking.slots.preferredTime')) ?></label>
        <input type="date" id="dateInput" min="<?= e($today) ?>" required>
    </div>

    <div class="form-group">
        <span class="label"><?= e(__t('booking.slots.title')) ?></span>
        <p class="muted"><?= e(__t('booking.slots.instructions')) ?></p>
        <div id="slotsContainer" class="slot-container"><span class="muted"><?= e(__t('booking.slots.selectFirst')) ?></span></div>
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

    <button type="submit" class="btn"><?= e(__t('booking.form.submit')) ?></button>
</form>

<script type="application/json" id="booking-config"><?= json_encode($clientConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="js/booking.js"></script>
