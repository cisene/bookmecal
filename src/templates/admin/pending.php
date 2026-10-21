<div class="card">
    <h2><?= e(__t('admin.pending.title')) ?></h2>

    <form method="get" class="form-inline">
        <div class="form-group">
            <label for="filter-email"><?= e(__t('admin.pending.filterEmail')) ?></label>
            <input type="email" id="filter-email" name="email" value="<?= e($emailFilter) ?>" maxlength="254">
        </div>
        <button type="submit" class="btn"><?= e(__t('admin.pending.filter')) ?></button>
        <?php if ($emailFilter !== ''): ?>
            <a href="pending.php" class="btn btn-secondary"><?= e(__t('admin.pending.clearFilter')) ?></a>
        <?php endif; ?>
    </form>
    <p class="muted"><?= e(__t('admin.pending.gdprHint')) ?>
        <?= e($retentionDays > 0 ? __t('admin.pending.retention', ['days' => $retentionDays]) : __t('admin.pending.retentionOff')) ?></p>

    <?php if ($emailFilter !== '' && !empty($bookings)): ?>
        <div class="toolbar">
            <a href="pending.php?email=<?= e(rawurlencode($emailFilter)) ?>&amp;export=1" class="btn btn-small btn-secondary"><?= e(__t('admin.pending.export')) ?></a>
            <form method="post" class="inline" data-confirm="<?= e(__t('admin.pending.confirmDeleteAll', ['count' => count($bookings), 'email' => $emailFilter])) ?>">
                <?php include __DIR__ . '/../partials/csrf.php'; ?>
                <input type="hidden" name="action" value="delete_all">
                <button type="submit" class="btn btn-small btn-danger"><?= e(__t('admin.pending.deleteAll', ['email' => $emailFilter])) ?></button>
            </form>
        </div>
    <?php endif; ?>

    <?php if (empty($bookings)): ?>
        <p class="empty"><?= e(__t('admin.pending.empty')) ?></p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th><?= e(__t('admin.common.date')) ?></th>
                    <th><?= e(__t('admin.common.service')) ?></th>
                    <th><?= e(__t('admin.common.customer')) ?></th>
                    <th><?= e(__t('admin.common.email')) ?></th>
                    <th><?= e(__t('admin.common.status')) ?></th>
                    <th><?= e(__t('admin.common.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $id => $booking): $status = $booking['status'] ?? 'pending'; ?>
                    <tr>
                        <td class="mono"><?= e(trim(($booking['date'] ?? '') . ' ' . ($booking['time'] ?? ''))) ?></td>
                        <td><?= e($booking['service_name']) ?></td>
                        <td><?= e($booking['customer_name'] ?? '') ?></td>
                        <td><?= e($booking['customer_email'] ?? '') ?></td>
                        <td><span class="badge badge-<?= e($status) ?>"><?= e(__t('admin.status.' . $status)) ?></span></td>
                        <td class="actions">
                            <?php if ($status === 'pending'): ?>
                                <?php foreach (['approve' => 'btn-success', 'reject' => 'btn-danger'] as $action => $class): ?>
                                    <form method="post" class="inline">
                                        <?php include __DIR__ . '/../partials/csrf.php'; ?>
                                        <input type="hidden" name="id" value="<?= e($id) ?>">
                                        <input type="hidden" name="action" value="<?= e($action) ?>">
                                        <button type="submit" class="btn btn-small <?= e($class) ?>"><?= e(__t('admin.pending.' . $action)) ?></button>
                                    </form>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <form method="post" class="inline" data-confirm="<?= e(__t('admin.pending.confirmDelete')) ?>">
                                <?php include __DIR__ . '/../partials/csrf.php'; ?>
                                <input type="hidden" name="id" value="<?= e($id) ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn-small btn-secondary"><?= e(__t('admin.pending.delete')) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
