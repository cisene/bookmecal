<?php
require_once 'i18n.php';
?>
<!DOCTYPE html>
<html lang="<?= CURRENT_LANG ?>">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars(__t('page_title')) ?></title>
</head>
<body>
    <header>
        <h1><?= htmlspecialchars(__t('heading')) ?></h1>
        <?php include 'lang_switcher.php'; ?>
    </header>

    <main>
        <form>
            <label><?= htmlspecialchars(__t('full_name')) ?></label>
            <input type="text" name="full_name">

            <label><?= htmlspecialchars(__t('email')) ?></label>
            <input type="email" name="email">

            <button type="submit"><?= htmlspecialchars(__t('submit_btn')) ?></button>
        </form>
    </main>
</body>
</html>