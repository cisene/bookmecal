<div class="card">
    <h2><?= e(__t('admin.services.title')) ?></h2>
    <p class="muted"><?= e(__t('admin.services.intro')) ?></p>

    <form method="post" id="service-form">
        <?php include __DIR__ . '/../partials/csrf.php'; ?>
        <input type="hidden" name="action" value="save_service">
        <input type="hidden" name="service_id" value="">

        <div class="form-grid">
            <div class="form-group">
                <label for="srv-name"><?= e(__t('admin.services.name')) ?></label>
                <input type="text" id="srv-name" name="name" required maxlength="255">
            </div>
            <div class="form-group">
                <label for="srv-sku"><?= e(__t('admin.services.sku')) ?></label>
                <input type="text" id="srv-sku" name="sku" maxlength="64">
            </div>
            <div class="form-group">
                <label for="srv-duration"><?= e(__t('admin.services.duration')) ?></label>
                <input type="number" id="srv-duration" name="duration" min="5" max="720" step="5">
            </div>
            <div class="form-group">
                <label for="srv-cleanup"><?= e(__t('admin.services.cleanup')) ?></label>
                <input type="number" id="srv-cleanup" name="cleanup" min="0" max="240" step="5" value="0">
            </div>
            <div class="form-group">
                <label for="srv-price"><?= e(__t('admin.services.priceInclVat')) ?></label>
                <input type="number" id="srv-price" name="price_incl_vat" min="0" step="0.01" value="0">
            </div>
            <div class="form-group">
                <label for="srv-vat"><?= e(__t('admin.services.vatRate')) ?></label>
                <input type="number" id="srv-vat" name="vat_rate" min="0" max="100" step="0.01" value="25">
            </div>
            <div class="form-group span-2">
                <label for="srv-description"><?= e(__t('admin.services.description')) ?></label>
                <textarea id="srv-description" name="description" rows="3" maxlength="2000"></textarea>
            </div>
            <fieldset class="form-group span-2">
                <legend><?= e(__t('admin.services.items')) ?></legend>
                <?php foreach ($treatments as $key => $name): ?>
                    <label class="checkbox"><input type="checkbox" name="items[]" value="<?= e((string)$key) ?>"> <?= e($name) ?></label>
                <?php endforeach; ?>
                <small class="muted"><?= e(__t('admin.services.itemsHint')) ?></small>
            </fieldset>
            <div class="form-group">
                <label class="checkbox"><input type="checkbox" name="is_active" value="1" checked> <?= e(__t('admin.services.active')) ?></label>
            </div>
            <div class="form-group">
                <label class="checkbox"><input type="checkbox" name="exclusive" value="1"> <?= e(__t('admin.services.exclusive')) ?></label>
                <small class="muted"><?= e(__t('admin.services.exclusiveHint')) ?></small>
            </div>
        </div>

        <button type="submit" class="btn"><?= e(__t('admin.common.save')) ?></button>
        <button type="reset" class="btn btn-secondary"><?= e(__t('admin.common.new')) ?></button>
    </form>
</div>

<div class="card">
    <h2><?= e(__t('admin.services.existing')) ?></h2>
    <?php if (empty($rows)): ?>
        <p class="empty"><?= e(__t('admin.services.empty')) ?></p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th><?= e(__t('admin.services.name')) ?></th>
                    <th><?= e(__t('admin.services.duration')) ?></th>
                    <th><?= e(__t('admin.services.cleanup')) ?></th>
                    <th><?= e(__t('admin.services.priceInclVat')) ?></th>
                    <th><?= e(__t('admin.common.status')) ?></th>
                    <th><?= e(__t('admin.common.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $id => $row): ?>
                    <tr>
                        <td>
                            <strong><?= e($row['name']) ?></strong>
                            <?php if ($row['sku'] !== ''): ?><br><small class="muted"><?= e($row['sku']) ?></small><?php endif; ?>
                            <?php if ($row['items']): ?><br><small class="muted"><?= e(__t('admin.services.setOf', ['items' => implode(' + ', $row['items'])])) ?></small><?php endif; ?>
                            <?php if ($row['exclusive']): ?><br><span class="badge badge-exclusive"><?= e(__t('admin.services.exclusiveBadge')) ?></span><?php endif; ?>
                        </td>
                        <td><?= $row['duration'] === null
                            ? '<span class="error-text">' . e(__t('admin.services.brokenSet')) . '</span>'
                            : e(__t('admin.common.minutes', ['count' => $row['duration']])) ?></td>
                        <td><?= e(__t('admin.common.minutes', ['count' => $row['cleanup']])) ?></td>
                        <td><?= e(number_format($row['price'], 2)) ?></td>
                        <td><?= e(__t($row['isActive'] ? 'admin.services.active' : 'admin.services.inactive')) ?></td>
                        <td class="actions">
                            <button type="button" class="btn btn-small btn-secondary" data-edit-form="service-form" data-values="<?= e(json_encode($row['editValues'], JSON_UNESCAPED_UNICODE)) ?>"><?= e(__t('admin.common.edit')) ?></button>
                            <form method="post" class="inline" data-confirm="<?= e(__t('admin.services.confirmDelete')) ?>">
                                <?php include __DIR__ . '/../partials/csrf.php'; ?>
                                <input type="hidden" name="action" value="delete_service">
                                <input type="hidden" name="service_id" value="<?= e($id) ?>">
                                <button type="submit" class="btn btn-small btn-danger"><?= e(__t('admin.common.delete')) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
