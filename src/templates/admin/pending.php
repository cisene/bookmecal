<div class="card">
    <h2><?= e(__t('admin.pending.title')) ?></h2>

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
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
