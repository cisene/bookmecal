<div class="card guide">
    <h2><?= e(__t('admin.guide.title')) ?></h2>
    <p><?= e(__t('admin.guide.intro')) ?></p>
    <p><a href="setup_google.php" class="btn"><?= e(__t('admin.guide.toWizard')) ?></a></p>

    <h3><?= e(__t('admin.guide.part1.title')) ?></h3>
    <p><?= e(__t('admin.guide.part1.intro')) ?></p>
    <ol>
        <li><strong><?= e(__t('admin.guide.part1.step1.title')) ?></strong><br>
            <?= e(__t('admin.guide.part1.step1.text')) ?> <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">console.cloud.google.com</a></li>
        <li><strong><?= e(__t('admin.guide.part1.step2.title')) ?></strong><br><?= e(__t('admin.guide.part1.step2.text')) ?></li>
        <li><strong><?= e(__t('admin.guide.part1.step3.title')) ?></strong><br><?= e(__t('admin.guide.part1.step3.text')) ?></li>
        <li><strong><?= e(__t('admin.guide.part1.step4.title')) ?></strong><br><?= e(__t('admin.guide.part1.step4.text')) ?></li>
        <li><strong><?= e(__t('admin.guide.part1.step5.title')) ?></strong><br><?= e(__t('admin.guide.part1.step5.text')) ?>
            <pre class="illustration">[ Google Cloud Console: Credentials ]
+---------------------------------------------------+
| Application type: [ Web application             v ] |
| Name:             [ Book Me Calendar Web Client     ] |
|                                                   |
| Authorized redirect URIs:                         |
| [+ ADD URI] <?= e($redirectUri) ?>

+---------------------------------------------------+
[ CREATE ]</pre>
        </li>
        <li><strong><?= e(__t('admin.guide.part1.step6.title')) ?></strong><br><?= e(__t('admin.guide.part1.step6.text')) ?></li>
    </ol>

    <h3><?= e(__t('admin.guide.part2.title')) ?></h3>
    <p><?= e(__t('admin.guide.part2.intro')) ?></p>
    <ol>
        <li><strong><?= e(__t('admin.guide.part2.step1.title')) ?></strong><br>
            <?= e(__t('admin.guide.part2.step1.text')) ?> <a href="https://calendar.google.com/" target="_blank" rel="noopener">calendar.google.com</a></li>
        <li><strong><?= e(__t('admin.guide.part2.step2.title')) ?></strong><br><?= e(__t('admin.guide.part2.step2.text')) ?></li>
        <li><strong><?= e(__t('admin.guide.part2.step3.title')) ?></strong><br><?= e(__t('admin.guide.part2.step3.text')) ?>
            <pre class="illustration">[ Google Calendar Settings ]
+---------------------------------------------------+
| Integrate calendar                                |
| Calendar ID:                                      |
| [ primary or abc123xyz@group.calendar.google.com ]|
+---------------------------------------------------+</pre>
            <p><em><?= e(__t('admin.guide.part2.note')) ?></em></p>
        </li>
    </ol>

    <p class="center"><a href="setup_google.php" class="btn"><?= e(__t('admin.guide.toWizard')) ?></a></p>
</div>
