<?php
/**
 * Admin layout. Expects: $title (translation key), $content, $flash, $currentAdmin,
 * $currentPage, $lang, $languages.
 */
$navItems = [
    'index.php' => ['admin.nav.dashboard', ['index.php']],
    'pending.php' => ['admin.nav.pending', ['pending.php']],
    'services.php' => ['admin.nav.services', ['services.php']],
    'employees.php' => ['admin.nav.employees', ['employees.php']],
    'openhours.php' => ['admin.nav.hours', ['openhours.php']],
    'holidays.php' => ['admin.nav.holidays', ['holidays.php']],
    'availability.php' => ['admin.nav.slots', ['availability.php']],
    'config.php' => ['admin.nav.config', ['config.php', 'setup_google.php', 'google_guide.php']],
    'users.php' => ['admin.nav.users', ['users.php']],
];
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(__t($title)) ?> - <?= e(__t('admin.appTitle')) ?></title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
<div class="container">
    <header class="admin-header">
        <div>
            <h1><?= e(__t('admin.appTitle')) ?></h1>
            <small><?= e(__t('admin.loggedInAs')) ?> <strong><?= e($currentAdmin) ?></strong></small>
        </div>
        <nav class="admin-nav">
            <?php foreach ($navItems as $href => [$labelKey, $activePages]): ?>
                <a href="<?= e($href) ?>"<?= in_array($currentPage, $activePages, true) ? ' class="active"' : '' ?>><?= e(__t($labelKey)) ?></a>
            <?php endforeach; ?>
        </nav>
        <form method="get" class="lang-switch">
            <select name="lang" data-autosubmit aria-label="<?= e(__t('common.language')) ?>">
                <?php foreach ($languages as $code => $name): ?>
                    <option value="<?= e($code) ?>"<?= $code === $lang ? ' selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </header>

    <?php foreach ($flash as $message): ?>
        <div class="alert alert-<?= e($message['type']) ?>"><?= e($message['text']) ?></div>
    <?php endforeach; ?>

    <?= $content ?>
</div>
<script src="../js/admin.js"></script>
</body>
</html>
