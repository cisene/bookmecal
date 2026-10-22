<div class="card">
    <h2><?= e(__t('admin.slots.title')) ?></h2>
    <p class="muted"><?= e(__t('admin.slots.intro')) ?></p>

    <?php if (empty($names)): ?>
        <p class="empty"><?= e(__t('admin.slots.noEmployees')) ?> <a href="employees.php"><?= e(__t('admin.nav.employees')) ?></a></p>
    <?php else: ?>
        <form method="get" class="form-group">
            <label for="slot-employee"><?= e(__t('admin.slots.employee')) ?></label>
            <select id="slot-employee" name="employee" data-autosubmit>
                <?php foreach ($names as $id => $name): ?>
                    <option value="<?= e($id) ?>"<?= $id === $selected ? ' selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="btn btn-small btn-secondary"><?= e(__t('admin.slots.show')) ?></button></noscript>
        </form>

        <?php if ($followsOpening): ?>
            <p class="muted"><?= e(__t('admin.slots.followsOpening')) ?></p>
        <?php endif; ?>

        <div class="legend">
            <span><span class="legend-box slot-open"></span> <?= e(__t('admin.slots.open')) ?></span>
            <span><span class="legend-box slot-closed"></span> <?= e(__t('admin.slots.closed')) ?></span>
            <span><span class="legend-box slot-closed slot-outside"></span> <?= e(__t('admin.slots.outside')) ?></span>
        </div>

        <form method="post">
            <?php include __DIR__ . '/../partials/csrf.php'; ?>
            <input type="hidden" name="action" value="save_schedule">
            <input type="hidden" name="employee" value="<?= e($selected) ?>">
            <div class="table-scroll">
                <table class="slot-matrix">
                    <thead>
                        <tr>
                            <th><?= e(__t('admin.common.time')) ?></th>
                            <?php foreach ($days as $day): ?><th><?= e(__t('common.weekday.' . $day)) ?></th><?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hours as $hour): ?>
                            <tr>
                                <th scope="row" class="mono"><?= e($hour) ?></th>
                                <?php foreach ($days as $day): $id = 'slot-' . $day . '-' . str_replace(':', '', $hour); ?>
                                    <td<?= $outside[$day][$hour] ? ' class="slot-outside" title="' . e(__t('admin.slots.outside')) . '"' : '' ?>>
                                        <input type="checkbox" id="<?= e($id) ?>" class="slot-input" name="slots[<?= e($day) ?>][<?= e($hour) ?>]" value="1"<?= $open[$day][$hour] ? ' checked' : '' ?>>
                                        <label for="<?= e($id) ?>" class="slot-label" data-open="<?= e(__t('admin.slots.open')) ?>" data-closed="<?= e(__t('admin.slots.closed')) ?>"></label>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-success"><?= e(__t('admin.common.save')) ?></button>
        </form>

        <?php if (!$followsOpening): ?>
            <form method="post" class="inline" data-confirm="<?= e(__t('admin.slots.confirmReset')) ?>">
                <?php include __DIR__ . '/../partials/csrf.php'; ?>
                <input type="hidden" name="action" value="reset_schedule">
                <input type="hidden" name="employee" value="<?= e($selected) ?>">
                <button type="submit" class="btn btn-secondary"><?= e(__t('admin.slots.reset')) ?></button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>
