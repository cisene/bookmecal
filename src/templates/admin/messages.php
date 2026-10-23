<?php
/** The form fields carried to the send button, so the confirmed message is the previewed one. */
$hiddenFields = $form;
?>
<div class="card">
    <h2><?= e(__t('admin.messages.title')) ?></h2>
    <p class="muted"><?= e(__t('admin.messages.intro')) ?></p>
    <?php if (!$enabled): ?>
        <div class="alert alert-error"><?= e(__t('admin.messages.disabled')) ?> <a href="config.php#email"><?= e(__t('admin.nav.config')) ?></a></div>
    <?php endif; ?>

    <form method="post">
        <?php include __DIR__ . '/../partials/csrf.php'; ?>
        <input type="hidden" name="action" value="preview">

        <div class="form-group">
            <label for="msg-group"><?= e(__t('admin.messages.group')) ?></label>
            <select id="msg-group" name="group" data-toggle-group>
                <?php foreach (CustomerMessages::GROUPS as $group): ?>
                    <option value="<?= e($group) ?>"<?= $group === $form['group'] ? ' selected' : '' ?>><?= e(__t('admin.messages.groups.' . $group)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" data-show-for="one">
            <label for="msg-email"><?= e(__t('admin.common.email')) ?></label>
            <input type="email" id="msg-email" name="email" maxlength="254" value="<?= e($form['email']) ?>">
        </div>

        <div class="form-grid" data-show-for="upcoming">
            <div class="form-group">
                <label for="msg-from"><?= e(__t('admin.messages.from')) ?></label>
                <input type="date" id="msg-from" name="from" min="<?= e($today) ?>" value="<?= e($form['from']) ?>">
            </div>
            <div class="form-group">
                <label for="msg-to"><?= e(__t('admin.messages.to')) ?></label>
                <input type="date" id="msg-to" name="to" min="<?= e($today) ?>" value="<?= e($form['to']) ?>">
            </div>
            <?php if ($employees !== []): ?>
                <div class="form-group">
                    <label for="msg-employee"><?= e(__t('admin.messages.employee')) ?></label>
                    <select id="msg-employee" name="employee_id">
                        <option value=""><?= e(__t('admin.messages.anyEmployee')) ?></option>
                        <?php foreach ($employees as $id => $name): ?>
                            <option value="<?= e($id) ?>"<?= $id === $form['employee_id'] ? ' selected' : '' ?>><?= e($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="form-group">
                <label for="msg-service"><?= e(__t('admin.common.service')) ?></label>
                <select id="msg-service" name="service_id">
                    <option value=""><?= e(__t('admin.messages.anyService')) ?></option>
                    <?php foreach ($services as $id => $name): ?>
                        <option value="<?= e($id) ?>"<?= $id === $form['service_id'] ? ' selected' : '' ?>><?= e($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <p class="muted" data-show-for="all"><?= e(__t('admin.messages.allHint')) ?></p>

        <div class="form-group">
            <label for="msg-subject"><?= e(__t('admin.messages.subject')) ?></label>
            <input type="text" id="msg-subject" name="subject" maxlength="<?= e($subjectMax) ?>" value="<?= e($form['subject']) ?>" required>
        </div>
        <div class="form-group">
            <label for="msg-text"><?= e(__t('admin.messages.text')) ?></label>
            <textarea id="msg-text" name="text" rows="10" maxlength="<?= e($bodyMax) ?>" required><?= e($form['text']) ?></textarea>
            <small class="muted"><?= e(__t('admin.messages.textHint')) ?></small>
        </div>

        <button type="submit" class="btn"><?= e(__t('admin.messages.preview')) ?></button>
    </form>
</div>

<?php if ($preview !== null): $count = count($preview['recipients']); ?>
    <div class="card" id="preview">
        <h2><?= e(__t('admin.messages.previewTitle')) ?></h2>
        <p><?= e(__t('admin.messages.recipients', ['count' => $count])) ?></p>

        <h3><?= e(__t('admin.messages.sample')) ?></h3>
        <pre class="message-sample"><strong><?= e($preview['subject']) ?></strong>

<?= e($preview['body']) ?></pre>

        <details>
            <summary><?= e(__t('admin.messages.showRecipients')) ?></summary>
            <table>
                <thead>
                    <tr>
                        <th><?= e(__t('admin.common.customer')) ?></th>
                        <th><?= e(__t('admin.common.email')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($preview['recipients'] as $recipient): ?>
                        <tr>
                            <td><?= e($recipient['name']) ?></td>
                            <td><?= e($recipient['email']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </details>

        <?php if ($enabled): ?>
            <form method="post" class="toolbar" data-confirm="<?= e(__t('admin.messages.confirmSend', ['count' => $count])) ?>">
                <?php include __DIR__ . '/../partials/csrf.php'; ?>
                <input type="hidden" name="action" value="send">
                <?php foreach ($hiddenFields as $name => $value): ?>
                    <input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>">
                <?php endforeach; ?>
                <button type="submit" class="btn btn-success"><?= e(__t('admin.messages.send', ['count' => $count])) ?></button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="card">
    <h2><?= e(__t('admin.messages.history')) ?></h2>
    <?php if ($history === []): ?>
        <p class="empty"><?= e(__t('admin.messages.historyEmpty')) ?></p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th><?= e(__t('admin.common.date')) ?></th>
                    <th><?= e(__t('admin.messages.group')) ?></th>
                    <th><?= e(__t('admin.messages.subject')) ?></th>
                    <th><?= e(__t('admin.messages.result')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $entry): $criteria = is_array($entry['criteria'] ?? null) ? $entry['criteria'] : []; $group = (string)($criteria['group'] ?? ''); ?>
                    <tr>
                        <td class="mono"><?= e(substr((string)($entry['at'] ?? ''), 0, 16)) ?><br><small class="muted"><?= e($entry['by'] ?? '') ?></small></td>
                        <td>
                            <?= in_array($group, CustomerMessages::GROUPS, true) ? e(__t('admin.messages.groups.' . $group)) : '' ?>
                            <?php
                            $details = array_filter([
                                ($criteria['from'] ?? '') . ($criteria['to'] ?? '') !== '' ? (($criteria['from'] ?? '') ?: '…') . ' – ' . (($criteria['to'] ?? '') ?: '…') : '',
                                $employees[$criteria['employee_id'] ?? ''] ?? '',
                                $services[$criteria['service_id'] ?? ''] ?? '',
                            ]);
                            ?>
                            <?php if ($details !== []): ?><br><small class="muted"><?= e(implode(' · ', $details)) ?></small><?php endif; ?>
                        </td>
                        <td>
                            <details>
                                <summary><?= e($entry['subject'] ?? '') ?></summary>
                                <pre class="message-sample"><?= e($entry['text'] ?? '') ?></pre>
                            </details>
                        </td>
                        <td><?= e(__t('admin.messages.resultCounts', ['sent' => (int)($entry['sent'] ?? 0), 'failed' => (int)($entry['failed'] ?? 0)])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
