<div class="card">
    <h2><?= e(__t('admin.hours.title')) ?></h2>
    <p class="muted"><?= e(__t('admin.hours.intro')) ?></p>

    <form method="post">
        <?php include __DIR__ . '/../partials/csrf.php'; ?>

        <?php foreach ($days as $day => $config): ?>
            <div class="day-row">
                <div class="day-name"><?= e(__t('common.weekday.' . $day)) ?></div>
                <label class="checkbox">
                    <input type="checkbox" name="hours[<?= e($day) ?>][is_open]" value="1"<?= $config['is_open'] ? ' checked' : '' ?>>
                    <?= e(__t('admin.hours.open')) ?>
                </label>
                <div class="time-inputs">
                    <input type="time" class="time-input" name="hours[<?= e($day) ?>][open_time]" value="<?= e($config['open_time']) ?>" aria-label="<?= e(__t('admin.hours.opens')) ?>">
                    <span><?= e(__t('admin.hours.to')) ?></span>
                    <input type="time" class="time-input" name="hours[<?= e($day) ?>][close_time]" value="<?= e($config['close_time']) ?>" aria-label="<?= e(__t('admin.hours.closes')) ?>">
                </div>
            </div>
        <?php endforeach; ?>

        <button type="submit" class="btn btn-block"><?= e(__t('admin.common.save')) ?></button>
    </form>
</div>
