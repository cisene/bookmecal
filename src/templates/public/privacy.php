<?php
/**
 * Privacy notice. Expects: $business, $contact (email or null), $retentionDays (0 = kept until deleted).
 */
?>
<article class="privacy">
    <h1><?= e(__t('privacy.title')) ?></h1>
    <p><?= e(__t('privacy.intro', ['business' => $business])) ?></p>

    <h2><?= e(__t('privacy.controller.heading')) ?></h2>
    <p><?= e(__t('privacy.controller.body', ['business' => $business])) ?>
        <?php if ($contact !== null): ?>
            <?= e(__t('privacy.controller.contact')) ?> <a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a>
        <?php else: ?>
            <?= e(__t('privacy.controller.noContact')) ?>
        <?php endif; ?>
    </p>

    <h2><?= e(__t('privacy.data.heading')) ?></h2>
    <p><?= e(__t('privacy.data.body')) ?></p>

    <h2><?= e(__t('privacy.purpose.heading')) ?></h2>
    <p><?= e(__t('privacy.purpose.body')) ?></p>

    <h2><?= e(__t('privacy.retention.heading')) ?></h2>
    <p><?= e($retentionDays > 0 ? __t('privacy.retention.body', ['days' => $retentionDays]) : __t('privacy.retention.untilDeleted')) ?></p>

    <h2><?= e(__t('privacy.sharing.heading')) ?></h2>
    <p><?= e(__t('privacy.sharing.body')) ?><?php if ($calendarFeed): ?> <?= e(__t('privacy.sharing.calendar')) ?><?php endif; ?></p>

    <h2><?= e(__t('privacy.security.heading')) ?></h2>
    <p><?= e(__t('privacy.security.body')) ?></p>

    <h2><?= e(__t('privacy.cookies.heading')) ?></h2>
    <p><?= e(__t('privacy.cookies.body')) ?></p>

    <h2><?= e(__t('privacy.rights.heading')) ?></h2>
    <p><?= e(__t('privacy.rights.body')) ?></p>

    <p><a href="index.php"><?= e(__t('privacy.back')) ?></a></p>
</article>
