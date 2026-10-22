<?php if (!empty($setupProblems)): ?>
<div class="card setup-check">
    <h2><?= e(__t('admin.setup.title')) ?></h2>
    <p class="muted"><?= e(__t('admin.setup.intro')) ?></p>
    <ul>
        <?php foreach ($setupProblems as $problem): ?>
            <li>
                <?= e(__t('admin.setup.' . $problem['key'])) ?>
                <?php if ($problem['page'] !== null): ?>
                    <a href="<?= e($problem['page']) ?>"><?= e(__t('admin.setup.fix')) ?></a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

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
                            <td><?= e($booking['service_name']) ?><?php if ($booking['employee_name'] !== ''): ?><br><small class="muted"><?= e($booking['employee_name']) ?><?= !empty($booking['employee_requested']) ? ' · ' . e(__t('admin.pending.requested')) : '' ?></small><?php endif; ?></td>
                            <td><?= e($booking['customer_name'] ?? '') ?></td>
                            <td><span class="badge badge-<?= e($booking['status'] ?? 'pending') ?>"><?= e(__t('admin.status.' . ($booking['status'] ?? 'pending'))) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
