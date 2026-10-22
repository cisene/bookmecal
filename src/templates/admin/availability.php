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

<?php if (!empty($names)): ?>
<div class="card" id="exceptions">
    <h2><?= e(__t('admin.slots.exceptions')) ?></h2>
    <p class="muted"><?= e(__t('admin.slots.exceptionsIntro', ['name' => $names[$selected] ?? ''])) ?></p>

    <form method="post" class="form-inline">
        <?php include __DIR__ . '/../partials/csrf.php'; ?>
        <input type="hidden" name="action" value="add_exception">
        <input type="hidden" name="employee" value="<?= e($selected) ?>">
        <div class="form-group">
            <label for="exception-from"><?= e(__t('admin.slots.exceptionFrom')) ?></label>
            <input type="date" id="exception-from" name="from" min="<?= e($today) ?>" required>
        </div>
        <div class="form-group">
            <label for="exception-to"><?= e(__t('admin.slots.exceptionTo')) ?></label>
            <input type="date" id="exception-to" name="to" min="<?= e($today) ?>">
        </div>
        <div class="form-group">
            <label for="exception-kind"><?= e(__t('admin.slots.exceptionKind')) ?></label>
            <select id="exception-kind" name="kind">
                <option value="off"><?= e(__t('admin.slots.exceptionOff')) ?></option>
                <option value="hours"><?= e(__t('admin.slots.exceptionHours')) ?></option>
            </select>
        </div>
        <div class="form-group">
            <label for="exception-hour-from"><?= e(__t('admin.slots.exceptionHourFrom')) ?></label>
            <select id="exception-hour-from" name="hour_from">
                <?php for ($h = 0; $h < 24; $h++): ?><option value="<?= $h ?>"<?= $h === 9 ? ' selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option><?php endfor; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="exception-hour-to"><?= e(__t('admin.slots.exceptionHourTo')) ?></label>
            <select id="exception-hour-to" name="hour_to">
                <?php for ($h = 1; $h <= 24; $h++): ?><option value="<?= $h ?>"<?= $h === 17 ? ' selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option><?php endfor; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="exception-note"><?= e(__t('admin.slots.exceptionNote')) ?></label>
            <input type="text" id="exception-note" name="note" maxlength="100" placeholder="<?= e(__t('admin.slots.exceptionNoteHint')) ?>">
        </div>
        <button type="submit" class="btn"><?= e(__t('admin.slots.exceptionAdd')) ?></button>
    </form>
    <p class="muted"><small><?= e(__t('admin.slots.exceptionHint')) ?></small></p>

    <?php if (empty($exceptions)): ?>
        <p class="empty"><?= e(__t('admin.slots.exceptionsEmpty')) ?></p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th><?= e(__t('admin.common.date')) ?></th>
                    <th><?= e(__t('admin.slots.exceptionKind')) ?></th>
                    <th><?= e(__t('admin.slots.exceptionNote')) ?></th>
                    <th><?= e(__t('admin.common.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($exceptions as $exception): ?>
                    <tr>
                        <td class="mono"><?= e($exception['from'] === $exception['to'] ? $exception['from'] : $exception['from'] . ' – ' . $exception['to']) ?></td>
                        <td><?= e($exception['periods'] === [] ? __t('admin.slots.exceptionOff') : implode(', ', $exception['periods'])) ?></td>
                        <td><?= e($exception['note']) ?></td>
                        <td class="actions">
                            <form method="post" class="inline" data-confirm="<?= e(__t('admin.slots.exceptionConfirmRemove')) ?>">
                                <?php include __DIR__ . '/../partials/csrf.php'; ?>
                                <input type="hidden" name="action" value="remove_exception">
                                <input type="hidden" name="employee" value="<?= e($selected) ?>">
                                <input type="hidden" name="from" value="<?= e($exception['from']) ?>">
                                <input type="hidden" name="to" value="<?= e($exception['to']) ?>">
                                <button type="submit" class="btn btn-small btn-danger"><?= e(__t('admin.common.delete')) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php endif; ?>
