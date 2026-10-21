<div class="card">
    <h2><?= e(__t('admin.users.title')) ?></h2>
    <p class="muted"><?= e(__t('admin.users.intro')) ?></p>

    <h3><?= e(__t('admin.users.configured')) ?></h3>
    <?php if (empty($users)): ?>
        <p class="empty"><?= e(__t('admin.users.empty')) ?></p>
    <?php else: ?>
        <table>
            <thead>
                <tr><th><?= e(__t('admin.users.username')) ?></th><th><?= e(__t('admin.common.actions')) ?></th></tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><strong><?= e($user) ?></strong></td>
                        <td class="actions">
                            <?php if ($user === $currentAdmin): ?>
                                <span class="muted"><?= e(__t('admin.users.currentUser')) ?></span>
                            <?php else: ?>
                                <form method="post" class="inline" data-confirm="<?= e(__t('admin.users.confirmDelete', ['name' => $user])) ?>">
                                    <?php include __DIR__ . '/../partials/csrf.php'; ?>
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="username" value="<?= e($user) ?>">
                                    <button type="submit" class="btn btn-small btn-danger"><?= e(__t('admin.users.revoke')) ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h3><?= e(__t('admin.users.addOrUpdate')) ?></h3>
    <form method="post">
        <?php include __DIR__ . '/../partials/csrf.php'; ?>
        <input type="hidden" name="action" value="save_user">
        <div class="form-group">
            <label for="user-name"><?= e(__t('admin.users.username')) ?></label>
            <input type="text" id="user-name" name="username" required pattern="[A-Za-z0-9_]{3,20}" autocomplete="off">
        </div>
        <div class="form-group">
            <label for="user-password"><?= e(__t('admin.users.password')) ?></label>
            <input type="password" id="user-password" name="password" required minlength="<?= e($passwordMinLength) ?>" maxlength="72" autocomplete="new-password" aria-describedby="user-password-hint">
            <small id="user-password-hint" class="muted"><?= e(__t('admin.users.passwordHint', ['min' => $passwordMinLength])) ?></small>
        </div>
        <button type="submit" class="btn"><?= e(__t('admin.common.save')) ?></button>
    </form>
</div>

<div class="card">
    <h2><?= e(__t('admin.audit.title')) ?></h2>
    <p class="muted"><?= e(__t('admin.audit.intro')) ?></p>

    <?php if (empty($auditLogs)): ?>
        <p class="empty"><?= e(__t('admin.audit.empty')) ?></p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th><?= e(__t('admin.audit.timestamp')) ?></th>
                    <th><?= e(__t('admin.audit.user')) ?></th>
                    <th><?= e(__t('admin.audit.action')) ?></th>
                    <th><?= e(__t('admin.audit.details')) ?></th>
                    <th><?= e(__t('admin.audit.ip')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($auditLogs as $log): ?>
                    <tr>
                        <td class="mono"><?= e($log['timestamp'] ?? '') ?></td>
                        <td><strong><?= e($log['user'] ?? '') ?></strong></td>
                        <td><code><?= e($log['action'] ?? '') ?></code></td>
                        <td><?= e($log['details'] ?? '') ?></td>
                        <td class="mono"><?= e($log['ip'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h2><?= e(__t('admin.security.title')) ?></h2>
    <p class="muted"><?= e(__t('admin.security.intro')) ?></p>

    <?php if (empty($securityEvents)): ?>
        <p class="empty"><?= e(__t('admin.security.empty')) ?></p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th><?= e(__t('admin.audit.timestamp')) ?></th>
                    <th><?= e(__t('admin.security.event')) ?></th>
                    <th><?= e(__t('admin.audit.ip')) ?></th>
                    <th><?= e(__t('admin.audit.details')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($securityEvents as $event): ?>
                    <tr>
                        <td class="mono"><?= e($event['time'] ?? '') ?></td>
                        <td><code><?= e($event['event'] ?? '') ?></code></td>
                        <td class="mono"><?= e($event['ip'] ?? '') ?></td>
                        <td><?= e($event['details']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
