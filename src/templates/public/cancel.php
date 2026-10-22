<?php
/**
 * Customer cancellation page. Expects: $state (invalid, confirm, done, inactive, tooLate, rateLimited),
 * $booking (service, date, time, end) or null, $hours, $contact (email or null).
 */
?>
<article class="cancel">
    <h1><?= e(__t('cancel.title')) ?></h1>

    <?php if ($booking !== null): ?>
        <p class="cancel-booking"><?= e(__t('cancel.booking', $booking)) ?></p>
    <?php endif; ?>

    <?php if ($state === 'confirm'): ?>
        <p><?= e(__t('cancel.confirm.text')) ?></p>
        <form method="post" action="cancel.php">
            <input type="hidden" name="id" value="<?= e($keepQuery['id']) ?>">
            <input type="hidden" name="token" value="<?= e($keepQuery['token']) ?>">
            <button type="submit" class="btn btn-danger"><?= e(__t('cancel.confirm.button')) ?></button>
        </form>
    <?php elseif ($state === 'done'): ?>
        <div class="alert alert-success"><?= e(__t('cancel.done')) ?></div>
        <p><a href="index.php"><?= e(__t('cancel.bookAgain')) ?></a></p>
    <?php elseif ($state === 'tooLate'): ?>
        <div class="alert alert-error"><?= e(__t('cancel.tooLate', ['hours' => $hours])) ?></div>
    <?php elseif ($state === 'inactive'): ?>
        <div class="alert alert-error"><?= e(__t('cancel.inactive')) ?></div>
    <?php elseif ($state === 'rateLimited'): ?>
        <div class="alert alert-error"><?= e(__t('cancel.rateLimited')) ?></div>
    <?php else: ?>
        <div class="alert alert-error"><?= e(__t('cancel.invalid')) ?></div>
    <?php endif; ?>

    <?php if (in_array($state, ['tooLate', 'invalid'], true) && ($contact ?? null) !== null): ?>
        <p><?= e(__t('cancel.contact')) ?> <a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a></p>
    <?php endif; ?>
</article>
