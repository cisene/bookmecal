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

        <div class="form-group">
            <label class="checkbox">
                <input type="checkbox" name="avoid_small_gaps" value="1"<?= $avoidSmallGaps ? ' checked' : '' ?>>
                <?= e(__t('admin.config.avoidSmallGaps')) ?>
            </label>
            <small class="muted"><?= e(__t('admin.config.avoidSmallGapsHint')) ?></small>
        </div>

        <div class="form-group">
            <label for="cfg-expiry"><?= e(__t('admin.config.pendingExpiry')) ?></label>
            <input type="number" id="cfg-expiry" name="pending_expiry_hours" min="0" max="<?= e($maxPendingExpiryHours) ?>" step="1" value="<?= e($pendingExpiryHours) ?>" required>
            <small class="muted"><?= e(__t('admin.config.pendingExpiryHint')) ?></small>
        </div>

        <div class="form-group">
            <label for="cfg-cancel"><?= e(__t('admin.config.cancelCutoff')) ?></label>
            <input type="number" id="cfg-cancel" name="cancel_cutoff_hours" min="0" max="<?= e($maxCancelCutoffHours) ?>" step="1" value="<?= e($cancelCutoffHours) ?>" required>
            <small class="muted"><?= e(__t('admin.config.cancelCutoffHint')) ?></small>
        </div>

        <h3><?= e(__t('admin.config.privacyTitle')) ?></h3>
        <p class="muted"><?= e(__t('admin.config.privacyIntro')) ?> <a href="../privacy.php" target="_blank" rel="noopener"><?= e(__t('admin.config.privacyView')) ?></a></p>

        <div class="form-group">
            <label for="cfg-business"><?= e(__t('admin.config.businessName')) ?></label>
            <input type="text" id="cfg-business" name="business_name" maxlength="100" value="<?= e($businessName) ?>">
        </div>

        <div class="form-group">
            <label for="cfg-privacy-contact"><?= e(__t('admin.config.privacyContact')) ?></label>
            <input type="email" id="cfg-privacy-contact" name="privacy_contact" maxlength="254" value="<?= e($privacyContact) ?>">
            <small class="muted"><?= e(__t('admin.config.privacyContactHint')) ?></small>
        </div>

        <div class="form-group">
            <label for="cfg-retention"><?= e(__t('admin.config.retentionDays')) ?></label>
            <input type="number" id="cfg-retention" name="booking_retention_days" min="0" max="<?= e($maxRetentionDays) ?>" step="1" value="<?= e($retentionDays) ?>" required>
            <small class="muted"><?= e(__t('admin.config.retentionDaysHint')) ?></small>
        </div>

        <h3><?= e(__t('admin.config.emailTitle')) ?></h3>
        <p class="muted"><?= e(__t('admin.config.emailIntro')) ?></p>

        <div class="form-group">
            <label class="checkbox">
                <input type="checkbox" name="email_enabled" value="1"<?= $emailEnabled ? ' checked' : '' ?>>
                <?= e(__t('admin.config.emailEnabled')) ?>
            </label>
        </div>

        <div class="form-group">
            <label for="cfg-from-name"><?= e(__t('admin.config.fromName')) ?></label>
            <input type="text" id="cfg-from-name" name="from_name" maxlength="100" value="<?= e($fromName) ?>" required>
        </div>

        <div class="form-group">
            <label for="cfg-from-email"><?= e(__t('admin.config.fromEmail')) ?></label>
            <input type="email" id="cfg-from-email" name="from_email" maxlength="254" value="<?= e($fromEmail) ?>" required>
            <small class="muted"><?= e(__t('admin.config.fromEmailHint')) ?></small>
        </div>

        <div class="form-group">
            <label for="cfg-notify"><?= e(__t('admin.config.notificationEmail')) ?></label>
            <input type="email" id="cfg-notify" name="notification_email" maxlength="254" value="<?= e($notificationEmail) ?>">
            <small class="muted"><?= e(__t('admin.config.notificationEmailHint')) ?></small>
        </div>

        <h4><?= e(__t('admin.config.smtpTitle')) ?></h4>
        <p class="muted"><?= e(__t('admin.config.smtpIntro')) ?></p>

        <div class="form-group">
            <label for="cfg-smtp-host"><?= e(__t('admin.config.smtpHost')) ?></label>
            <input type="text" id="cfg-smtp-host" name="smtp_host" maxlength="253" placeholder="smtp.example.com" value="<?= e($smtpHost) ?>" autocapitalize="none" spellcheck="false">
            <small class="muted"><?= e(__t('admin.config.smtpHostHint')) ?></small>
        </div>

        <div class="form-group">
            <label for="cfg-smtp-encryption"><?= e(__t('admin.config.smtpSecurity')) ?></label>
            <select id="cfg-smtp-encryption" name="smtp_encryption">
                <?php foreach ($smtpEncryptions as $mode): ?>
                    <option value="<?= e($mode) ?>"<?= $mode === $smtpEncryption ? ' selected' : '' ?>><?= e(__t('admin.config.smtpEncryption.' . $mode)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="cfg-smtp-port"><?= e(__t('admin.config.smtpPort')) ?></label>
            <input type="number" id="cfg-smtp-port" name="smtp_port" min="1" max="65535" step="1" value="<?= e($smtpPort) ?>">
            <small class="muted"><?= e(__t('admin.config.smtpPortHint')) ?></small>
        </div>

        <div class="form-group">
            <label for="cfg-smtp-username"><?= e(__t('admin.config.smtpUsername')) ?></label>
            <input type="text" id="cfg-smtp-username" name="smtp_username" maxlength="254" value="<?= e($smtpUsername) ?>" autocomplete="off" autocapitalize="none" spellcheck="false">
        </div>

        <div class="form-group">
            <label for="cfg-smtp-password"><?= e(__t('admin.config.smtpPassword')) ?></label>
            <input type="password" id="cfg-smtp-password" name="smtp_password" maxlength="200" autocomplete="new-password" placeholder="<?= e($smtpPasswordSaved ? __t('admin.config.smtpPasswordSaved') : '') ?>">
            <small class="muted"><?= e(__t('admin.config.smtpPasswordHint')) ?></small>
            <?php if ($smtpPasswordSaved): ?>
                <label class="checkbox">
                    <input type="checkbox" name="smtp_password_clear" value="1">
                    <?= e(__t('admin.config.smtpPasswordClear')) ?>
                </label>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="cfg-public-url"><?= e(__t('admin.config.publicUrl')) ?></label>
            <input type="url" id="cfg-public-url" name="public_url" maxlength="200" placeholder="https://" value="<?= e($publicUrl) ?>">
            <small class="muted"><?= e(__t('admin.config.publicUrlHint')) ?></small>
        </div>

        <button type="submit" class="btn"><?= e(__t('admin.common.save')) ?></button>
    </form>
</div>

<div class="card" id="email">
    <h2><?= e(__t('admin.config.testTitle')) ?></h2>
    <p class="muted"><?= e(__t('admin.config.testIntro')) ?></p>
    <form method="post" class="form-inline">
        <?php include __DIR__ . '/../partials/csrf.php'; ?>
        <input type="hidden" name="test_action" value="send">
        <div class="form-group">
            <label for="cfg-test-to"><?= e(__t('admin.config.testTo')) ?></label>
            <input type="email" id="cfg-test-to" name="test_to" maxlength="254" value="<?= e($adminEmail) ?>" required>
        </div>
        <button type="submit" class="btn btn-secondary"><?= e(__t('admin.config.testSend')) ?></button>
    </form>
</div>

<div class="card" id="venue">
    <h2><?= e(__t('admin.config.venueTitle')) ?></h2>
    <p class="muted"><?= e(__t('admin.config.venueIntro')) ?></p>
    <form method="post">
        <?php include __DIR__ . '/../partials/csrf.php'; ?>
        <input type="hidden" name="venue_action" value="save">

        <div class="form-group">
            <label for="cfg-vcard"><?= e(__t('admin.config.vcard')) ?></label>
            <select id="cfg-vcard" name="vcard">
                <?php foreach ($vcardModes as $mode): ?>
                    <option value="<?= e($mode) ?>"<?= $mode === $vcardMode ? ' selected' : '' ?>><?= e(__t('admin.config.vcardMode.' . $mode)) ?></option>
                <?php endforeach; ?>
            </select>
            <small class="muted"><?= e(__t('admin.config.vcardHint')) ?></small>
        </div>

        <div class="form-grid">
            <?php foreach ([
                'website' => ['website', 'url', 200, 'https://'],
                'phone' => ['phone', 'tel', 30, ''],
                'email' => ['email', 'email', 254, ''],
                'street' => ['street', 'text', 100, ''],
                'postal_code' => ['postalCode', 'text', 20, ''],
                'city' => ['city', 'text', 100, ''],
                'country' => ['country', 'text', 60, ''],
                'latitude' => ['latitude', 'text', 12, '59.334591'],
                'longitude' => ['longitude', 'text', 12, '18.063240'],
                'plus_code' => ['plusCode', 'text', 80, '9FFW83PH+X9'],
            ] as $field => [$labelKey, $type, $max, $placeholder]): ?>
                <div class="form-group">
                    <label for="venue-<?= e($field) ?>"><?= e(__t('admin.config.venue.' . $labelKey)) ?></label>
                    <input type="<?= e($type) ?>" id="venue-<?= e($field) ?>" name="<?= e($field) ?>" maxlength="<?= e($max) ?>"<?= $placeholder !== '' ? ' placeholder="' . e($placeholder) . '"' : '' ?> value="<?= e($venue[$field] ?? '') ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <p class="muted"><?= e(__t('admin.config.venueGeoHint')) ?></p>

        <button type="submit" class="btn"><?= e(__t('admin.common.save')) ?></button>
    </form>
</div>

<div class="card" id="calendar-feed">
    <h2><?= e(__t('admin.config.feedTitle')) ?></h2>
    <p><?= e(__t('admin.config.feedIntro', ['days' => $feedPastDays])) ?></p>
    <?php if ($feedUrl !== null): ?>
        <div class="form-group">
            <label for="cfg-feed-url"><?= e(__t('admin.config.feedUrl')) ?></label>
            <input type="text" id="cfg-feed-url" class="feed-url" value="<?= e($feedUrl) ?>" readonly data-select-all>
            <small class="muted"><?= e(__t('admin.config.feedWarning')) ?></small>
        </div>
        <div class="toolbar">
            <a href="<?= e($feedWebcalUrl) ?>" class="btn"><?= e(__t('admin.config.feedSubscribe')) ?></a>
            <form method="post" class="inline" data-confirm="<?= e(__t('admin.config.feedConfirmRenew')) ?>">
                <?php include __DIR__ . '/../partials/csrf.php'; ?>
                <button type="submit" name="feed_action" value="renew" class="btn btn-secondary"><?= e(__t('admin.config.feedRenew')) ?></button>
            </form>
            <form method="post" class="inline" data-confirm="<?= e(__t('admin.config.feedConfirmDisable')) ?>">
                <?php include __DIR__ . '/../partials/csrf.php'; ?>
                <button type="submit" name="feed_action" value="disable" class="btn btn-danger"><?= e(__t('admin.config.feedDisable')) ?></button>
            </form>
        </div>
    <?php else: ?>
        <p class="muted"><?= e(__t('admin.config.feedOff')) ?></p>
        <form method="post">
            <?php include __DIR__ . '/../partials/csrf.php'; ?>
            <button type="submit" name="feed_action" value="renew" class="btn"><?= e(__t('admin.config.feedEnable')) ?></button>
        </form>
    <?php endif; ?>
</div>

<div class="card">
    <h2><?= e(__t('admin.config.googleTitle')) ?></h2>
    <p><?= e(__t('admin.config.googleIntro')) ?></p>
    <a href="setup_google.php" class="btn"><?= e(__t('admin.config.googleSetup')) ?></a>
    <a href="google_guide.php" class="btn btn-secondary"><?= e(__t('admin.config.googleGuide')) ?></a>
</div>
