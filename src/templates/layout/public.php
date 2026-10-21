<?php
/**
 * Public layout. Expects: $content, $lang, $languages.
 */
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(__t('booking.page.title')) ?></title>
    <link rel="stylesheet" href="css/booking.css">
</head>
<body>
<main class="container">
    <form method="get" class="lang-switch">
        <select name="lang" data-autosubmit aria-label="<?= e(__t('common.language')) ?>">
            <?php foreach ($languages as $code => $name): ?>
                <option value="<?= e($code) ?>"<?= $code === $lang ? ' selected' : '' ?>><?= e($name) ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <?= $content ?>

    <footer class="site-footer">
        <a href="privacy.php"><?= e(__t('privacy.title')) ?></a>
    </footer>
</main>
<script src="js/public.js"></script>
</body>
</html>
