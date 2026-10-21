<div class="card">
    <h2><?= e(__t('admin.slots.title')) ?></h2>
    <p class="muted"><?= e(__t('admin.slots.intro')) ?></p>

    <div class="legend">
        <span><span class="legend-box slot-open"></span> <?= e(__t('admin.slots.open')) ?></span>
        <span><span class="legend-box slot-closed"></span> <?= e(__t('admin.slots.closed')) ?></span>
    </div>

    <form method="post">
        <?php include __DIR__ . '/../partials/csrf.php'; ?>
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
                                <td>
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
</div>
