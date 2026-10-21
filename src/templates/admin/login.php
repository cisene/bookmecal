<?php
/**
 * Sign-in form. Expects: $error ('failed'|'locked'|null), $notice ('expired'|'signedOut'|null),
 * $username, $lockMinutes.
 */
?>
<div class="card login-card">
    <h2><?= e(__t('admin.login.title')) ?></h2>

    <?php if ($error === 'failed'): ?>
        <div class="alert alert-error" role="alert"><?= e(__t('admin.login.failed')) ?></div>
    <?php elseif ($error === 'locked'): ?>
        <div class="alert alert-error" role="alert"><?= e(__t('admin.login.locked', ['minutes' => $lockMinutes])) ?></div>
    <?php elseif ($notice !== null): ?>
        <div class="alert alert-success" role="status"><?= e(__t('admin.login.' . $notice)) ?></div>
    <?php endif; ?>

    <form method="post" action="login.php">
        <?php include __DIR__ . '/../partials/csrf.php'; ?>
        <div class="form-group">
            <label for="login-username"><?= e(__t('admin.login.username')) ?></label>
            <input type="text" id="login-username" name="username" value="<?= e($username) ?>" required maxlength="50" autocomplete="username" autocapitalize="none" autofocus>
        </div>
        <div class="form-group">
            <label for="login-password"><?= e(__t('admin.login.password')) ?></label>
            <input type="password" id="login-password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn"><?= e(__t('admin.login.submit')) ?></button>
    </form>
</div>
