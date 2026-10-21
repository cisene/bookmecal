<div class="card">
    <h2><?= e(__t('admin.config.title')) ?></h2>
    <p class="muted"><?= e(__t('admin.config.intro')) ?></p>

    <form method="post">
        <?php include __DIR__ . '/../partials/csrf.php'; ?>

        <div class="form-group">
            <label for="cfg-timezone"><?= e(__t('admin.config.timezone')) ?></label>
            <select id="cfg-timezone" name="timezone">
                <?php foreach ($timezones as $tz): ?>
                    <option value="<?= e($tz) ?>"<?= $tz === $timezone ? ' selected' : '' ?>><?= e($tz) ?></option>
                <?php endforeach; ?>
            </select>
            <small class="muted"><?= e(__t('admin.config.timezoneHint')) ?></small>
        </div>

        <div class="form-group">
            <label for="cfg-country"><?= e(__t('admin.config.country')) ?></label>
            <select id="cfg-country" name="default_country">
                <?php foreach ($countries as $code): ?>
                    <option value="<?= e($code) ?>"<?= $code === $country ? ' selected' : '' ?>><?= e(__t('common.country.' . $code)) ?> (<?= e(strtoupper($code)) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="checkbox">
                <input type="checkbox" name="show_past_slots" value="1"<?= $showPastSlots ? ' checked' : '' ?>>
                <?= e(__t('admin.config.showPastSlots')) ?>
            </label>
        </div>

        <button type="submit" class="btn"><?= e(__t('admin.common.save')) ?></button>
    </form>
</div>

<div class="card">
    <h2><?= e(__t('admin.config.googleTitle')) ?></h2>
    <p><?= e(__t('admin.config.googleIntro')) ?></p>
    <a href="setup_google.php" class="btn"><?= e(__t('admin.config.googleSetup')) ?></a>
    <a href="google_guide.php" class="btn btn-secondary"><?= e(__t('admin.config.googleGuide')) ?></a>
</div>
