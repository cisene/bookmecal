<div class="card">
    <h2><?= e(__t('admin.holidays.title')) ?></h2>

    <form method="get" class="form-group">
        <label for="holiday-country"><?= e(__t('admin.holidays.country')) ?></label>
        <select id="holiday-country" name="country" data-autosubmit>
            <?php foreach ($countries as $code): ?>
                <option value="<?= e($code) ?>"<?= $code === $country ? ' selected' : '' ?>><?= e(__t('common.country.' . $code)) ?> (<?= e(strtoupper($code)) ?>)</option>
            <?php endforeach; ?>
        </select>
    </form>

    <form method="post" action="?country=<?= e($country) ?>" class="form-inline">
        <?php include __DIR__ . '/../partials/csrf.php'; ?>
        <input type="hidden" name="action" value="add_holiday">
        <div class="form-group">
            <label for="holiday-date"><?= e(__t('admin.common.date')) ?></label>
            <input type="date" id="holiday-date" name="date" required>
        </div>
        <div class="form-group">
            <label for="holiday-title"><?= e(__t('admin.holidays.name')) ?></label>
            <input type="text" id="holiday-title" name="title" required>
        </div>
        <button type="submit" class="btn"><?= e(__t('admin.holidays.add')) ?></button>
    </form>
</div>

<div class="card">
    <h2><?= e(__t('admin.holidays.configured')) ?></h2>
    <?php if (empty($holidays)): ?>
        <p class="empty"><?= e(__t('admin.holidays.empty')) ?></p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th><?= e(__t('admin.common.date')) ?></th>
                    <th><?= e(__t('admin.holidays.name')) ?></th>
                    <th><?= e(__t('admin.common.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($holidays as $holiday): ?>
                    <tr>
                        <td class="mono"><?= e($holiday['date'] ?? '') ?></td>
                        <td><?= e($holiday['title'] ?? '') ?></td>
                        <td class="actions">
                            <form method="post" action="?country=<?= e($country) ?>" class="inline" data-confirm="<?= e(__t('admin.holidays.confirmDelete')) ?>">
                                <?php include __DIR__ . '/../partials/csrf.php'; ?>
                                <input type="hidden" name="action" value="delete_holiday">
                                <input type="hidden" name="date" value="<?= e($holiday['date'] ?? '') ?>">
                                <button type="submit" class="btn btn-small btn-danger"><?= e(__t('admin.common.delete')) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
