<div class="card">
    <h2><?= e(__t('admin.employees.title')) ?></h2>
    <p class="muted"><?= e(__t('admin.employees.intro')) ?></p>

    <form method="post" id="employee-form">
        <?php include __DIR__ . '/../partials/csrf.php'; ?>
        <input type="hidden" name="action" value="save_employee">
        <input type="hidden" name="id" value="">

        <div class="form-grid">
            <?php foreach ([
                'givenName' => 'text', 'familyName' => 'text', 'email' => 'email', 'jobTitle' => 'text',
                'phoneNumber' => 'tel', 'mobileNumber' => 'tel', 'swishPaymentNumber' => 'text', 'organisationNumber' => 'text',
            ] as $field => $type): ?>
                <div class="form-group">
                    <label for="emp-<?= e($field) ?>"><?= e(__t('admin.employees.' . $field)) ?></label>
                    <input type="<?= e($type) ?>" id="emp-<?= e($field) ?>" name="<?= e($field) ?>" maxlength="100">
                </div>
            <?php endforeach; ?>
            <div class="form-group span-2">
                <label for="emp-qualifications"><?= e(__t('admin.employees.qualifications')) ?></label>
                <input type="text" id="emp-qualifications" name="qualifications" maxlength="500">
                <small class="muted"><?= e(__t('admin.employees.qualificationsHint')) ?></small>
            </div>
            <div class="form-group">
                <label class="checkbox"><input type="checkbox" name="isBookable" value="1" checked> <?= e(__t('admin.employees.isBookable')) ?></label>
            </div>
            <div class="form-group">
                <label class="checkbox"><input type="checkbox" name="inactive" value="1"> <?= e(__t('admin.employees.inactive')) ?></label>
            </div>
        </div>

        <button type="submit" class="btn"><?= e(__t('admin.common.save')) ?></button>
        <button type="reset" class="btn btn-secondary"><?= e(__t('admin.common.new')) ?></button>
    </form>
</div>

<div class="card">
    <h2><?= e(__t('admin.employees.existing')) ?></h2>
    <?php if (empty($rows)): ?>
        <p class="empty"><?= e(__t('admin.employees.empty')) ?></p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th><?= e(__t('admin.employees.name')) ?></th>
                    <th><?= e(__t('admin.employees.jobTitle')) ?></th>
                    <th><?= e(__t('admin.employees.qualifications')) ?></th>
                    <th><?= e(__t('admin.common.status')) ?></th>
                    <th><?= e(__t('admin.common.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $id => $row): ?>
                    <tr>
                        <td><strong><?= e($row['name']) ?></strong><?php if ($row['email'] !== ''): ?><br><small class="muted"><?= e($row['email']) ?></small><?php endif; ?></td>
                        <td><?= e($row['jobTitle']) ?></td>
                        <td><?= e($row['qualifications']) ?></td>
                        <td>
                            <?= e(__t($row['inactive'] ? 'admin.employees.inactive' : 'admin.employees.active')) ?>
                            <?php if ($row['isBookable'] && !$row['inactive']): ?><br><small class="muted"><?= e(__t('admin.employees.isBookable')) ?></small><?php endif; ?>
                        </td>
                        <td class="actions">
                            <button type="button" class="btn btn-small btn-secondary" data-edit-form="employee-form" data-values="<?= e(json_encode($row['editValues'], JSON_UNESCAPED_UNICODE)) ?>"><?= e(__t('admin.common.edit')) ?></button>
                            <form method="post" class="inline" data-confirm="<?= e(__t('admin.employees.confirmDelete')) ?>">
                                <?php include __DIR__ . '/../partials/csrf.php'; ?>
                                <input type="hidden" name="action" value="delete_employee">
                                <input type="hidden" name="id" value="<?= e($id) ?>">
                                <button type="submit" class="btn btn-small btn-danger"><?= e(__t('admin.common.delete')) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
