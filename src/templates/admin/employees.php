<div class="card">
    <h2><?= e(__t('admin.employees.title')) ?></h2>
    <p class="muted"><?= e(__t('admin.employees.intro')) ?></p>

    <form method="post" id="employee-form">
        <?php include __DIR__ . '/../partials/csrf.php'; ?>
        <input type="hidden" name="action" value="save_employee">
        <input type="hidden" name="id" value="">

        <div class="form-grid">
            <?php foreach ([
                'givenName' => 'text', 'familyName' => 'text', 'additionalName' => 'text', 'nickname' => 'text',
                'honorificPrefix' => 'text', 'honorificSuffix' => 'text',
                'jobTitle' => 'text', 'role' => 'text', 'department' => 'text', 'email' => 'email',
                'phoneNumber' => 'tel', 'mobileNumber' => 'tel', 'website' => 'url', 'avatar' => 'url',
                'swishPaymentNumber' => 'text', 'organisationNumber' => 'text',
            ] as $field => $type): ?>
                <div class="form-group">
                    <label for="emp-<?= e($field) ?>"><?= e(__t('admin.employees.' . $field)) ?></label>
                    <input type="<?= e($type) ?>" id="emp-<?= e($field) ?>" name="<?= e($field) ?>" maxlength="<?= $type === 'url' ? 500 : 100 ?>"<?= $type === 'url' ? ' placeholder="https://"' : '' ?>>
                </div>
            <?php endforeach; ?>
            <div class="form-group span-2">
                <label for="emp-qualifications"><?= e(__t('admin.employees.qualifications')) ?></label>
                <input type="text" id="emp-qualifications" name="qualifications" maxlength="500">
                <small class="muted"><?= e(__t('admin.employees.qualificationsHint')) ?></small>
            </div>
            <div class="form-group span-2">
                <label for="emp-languages"><?= e(__t('admin.employees.languages')) ?></label>
                <input type="text" id="emp-languages" name="languages" maxlength="300">
                <small class="muted"><?= e(__t('admin.employees.languagesHint')) ?></small>
            </div>
            <div class="form-group span-2">
                <label for="emp-socialProfiles"><?= e(__t('admin.employees.socialProfiles')) ?></label>
                <textarea id="emp-socialProfiles" name="socialProfiles" rows="3" maxlength="5000"></textarea>
                <small class="muted"><?= e(__t('admin.employees.socialProfilesHint')) ?></small>
            </div>
            <div class="form-group span-2">
                <label for="emp-bio"><?= e(__t('admin.employees.bio')) ?></label>
                <textarea id="emp-bio" name="bio" rows="3" maxlength="1000"></textarea>
                <small class="muted"><?= e(__t('admin.employees.bioHint')) ?></small>
            </div>
            <fieldset class="form-group span-2">
                <legend><?= e(__t('admin.employees.services')) ?></legend>
                <?php foreach ($services as $key => $name): ?>
                    <label class="checkbox"><input type="checkbox" name="services[]" value="<?= e((string)$key) ?>"> <?= e($name) ?></label>
                <?php endforeach; ?>
                <small class="muted"><?= e(__t('admin.employees.servicesHint')) ?></small>
            </fieldset>
            <div class="form-group">
                <label class="checkbox"><input type="checkbox" name="isBookable" value="1" checked> <?= e(__t('admin.employees.isBookable')) ?></label>
            </div>
            <div class="form-group">
                <label class="checkbox"><input type="checkbox" name="inactive" value="1"> <?= e(__t('admin.employees.inactive')) ?></label>
            </div>
            <div class="form-group">
                <label class="checkbox"><input type="checkbox" name="shareContact" value="1"> <?= e(__t('admin.employees.shareContact')) ?></label>
                <small class="muted"><?= e(__t('admin.employees.shareContactHint')) ?></small>
            </div>
            <div class="form-group">
                <label class="checkbox"><input type="checkbox" name="showPhoto" value="1"> <?= e(__t('admin.employees.showPhoto')) ?></label>
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
                    <th><?= e(__t('admin.employees.services')) ?></th>
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
                        <td><?= e($row['services'] ? implode(', ', $row['services']) : __t('admin.employees.allServices')) ?><br><a class="small" href="availability.php?employee=<?= e(rawurlencode($id)) ?>"><?= e(__t('admin.employees.hoursLink')) ?></a></td>
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
