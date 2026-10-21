<div class="card">
    <h2><?= e(__t('admin.dashboard.title')) ?></h2>
    <p class="muted"><?= e(__t('admin.dashboard.timezone', ['timezone' => $timezone])) ?></p>

    <?php if (empty($days)): ?>
        <p class="empty"><?= e(__t('admin.dashboard.empty')) ?></p>
    <?php else: ?>
        <?php foreach ($days as $date => $bookings): ?>
            <h3><?= e($date) ?></h3>
            <table>
                <thead>
                    <tr>
                        <th><?= e(__t('admin.common.time')) ?></th>
                        <th><?= e(__t('admin.common.service')) ?></th>
                        <th><?= e(__t('admin.common.customer')) ?></th>
                        <th><?= e(__t('admin.common.status')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td class="mono"><?= e(($booking['time'] ?? '') . (isset($booking['end_time']) ? ' - ' . $booking['end_time'] : '')) ?></td>
                            <td><?= e($booking['service_name']) ?></td>
                            <td><?= e($booking['customer_name'] ?? '') ?></td>
                            <td><span class="badge badge-<?= e($booking['status'] ?? 'pending') ?>"><?= e(__t('admin.status.' . ($booking['status'] ?? 'pending'))) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
