<div class="card">
    <h2><?= e(__t('admin.google.title')) ?></h2>
    <p><?= e(__t('admin.google.intro')) ?></p>

    <div class="steps">
        <?php foreach ([1, 2, 3] as $number): ?>
            <div class="step<?= $number === $step ? ' active' : '' ?>"><?= e(__t('admin.google.step' . $number)) ?></div>
        <?php endforeach; ?>
    </div>

    <?php if ($completed): ?>
        <p><a href="config.php" class="btn"><?= e(__t('admin.google.backToConfig')) ?></a></p>

    <?php elseif ($step === 1): ?>
        <form method="post">
            <?php include __DIR__ . '/../partials/csrf.php'; ?>
            <input type="hidden" name="step" value="1">
            <p class="muted"><?= e(__t('admin.google.step1Intro')) ?> <a href="google_guide.php" target="_blank"><?= e(__t('admin.config.googleGuide')) ?></a></p>

            <div class="form-group">
                <label for="g-owner"><?= e(__t('admin.google.ownerName')) ?></label>
                <input type="text" id="g-owner" name="owner_name" value="<?= e($calendar['owner_name']) ?>" required>
            </div>
            <div class="form-group">
                <label for="g-email"><?= e(__t('admin.google.notificationEmail')) ?></label>
                <input type="email" id="g-email" name="notification_email" value="<?= e($calendar['notification_email']) ?>" required>
            </div>
            <div class="form-group">
                <label for="g-client"><?= e(__t('admin.google.clientId')) ?></label>
                <input type="text" id="g-client" name="client_id" value="<?= e($calendar['client_id']) ?>" required>
            </div>
            <div class="form-group">
                <label for="g-secret"><?= e(__t('admin.google.clientSecret')) ?></label>
                <input type="password" id="g-secret" name="client_secret" value="" autocomplete="off"
                       placeholder="<?= e(__t($calendar['has_secret'] ? 'admin.google.secretStored' : 'admin.google.clientSecret')) ?>"<?= $calendar['has_secret'] ? '' : ' required' ?>>
            </div>
            <div class="form-group">
                <label for="g-calendar"><?= e(__t('admin.google.calendarId')) ?></label>
                <input type="text" id="g-calendar" name="calendar_id" value="<?= e($calendar['calendar_id']) ?>" required>
            </div>
            <button type="submit" class="btn"><?= e(__t('admin.google.next')) ?></button>
        </form>

    <?php elseif ($step === 2): ?>
        <p class="muted"><?= e(__t('admin.google.step2Intro')) ?></p>
        <?php if ($authUrl === null): ?>
            <div class="alert alert-error"><?= e(__t('admin.google.missingClientId')) ?></div>
        <?php else: ?>
            <p class="center"><a href="<?= e($authUrl) ?>" class="btn btn-google"><?= e(__t('admin.google.authorize')) ?></a></p>
        <?php endif; ?>
        <p><a href="setup_google.php?step=1" class="btn btn-secondary"><?= e(__t('admin.google.back')) ?></a></p>

    <?php else: ?>
        <form method="post">
            <?php include __DIR__ . '/../partials/csrf.php'; ?>
            <input type="hidden" name="step" value="3">
            <p class="muted"><?= e(__t('admin.google.step3Intro')) ?></p>
            <div class="form-group">
                <label for="g-cache"><?= e(__t('admin.google.cacheInterval')) ?></label>
                <input type="number" id="g-cache" name="cache_refresh_interval" value="<?= e($calendar['cache_refresh_interval']) ?>" min="60" step="60" required>
            </div>
            <a href="setup_google.php?step=2" class="btn btn-secondary"><?= e(__t('admin.google.back')) ?></a>
            <button type="submit" class="btn btn-success"><?= e(__t('admin.google.finish')) ?></button>
        </form>
    <?php endif; ?>
</div>
