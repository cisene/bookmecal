<?php
// lang_switcher.php - UI Component
require_once __DIR__ . '/i18n.php';

$languages = [
    'sv'    => 'Svenska',
    'en'    => 'English',
    'de'    => 'Deutsch',
    'fr'    => 'Français',
    'es'    => 'Español',
    'pt'    => 'Português',
    'it'    => 'Italiano',
    'el'    => 'Ελληνικά',
    'th'    => 'ไทย',
    'tl'    => 'Tagalog',
    'ko'    => '한국어',
    'vi'    => 'Tiếng Việt',
    'ja'    => '日本語',
    'zh-CN' => '简体中文'
];

// Preserve current query parameters when switching languages
$currentQueryParams = $_GET;
?>

<div class="language-switcher">
    <form method="GET" action="" style="display:inline-block;">
        <?php foreach ($currentQueryParams as $paramKey => $paramValue): ?>
            <?php if ($paramKey !== 'lang'): ?>
                <input type="hidden" name="<?= htmlspecialchars($paramKey) ?>" value="<?= htmlspecialchars($paramValue) ?>">
            <?php endif; ?>
        <?php endforeach; ?>

        <select name="lang" onchange="this.form.submit()" aria-label="Select Language">
            <?php foreach ($languages as $code => $label): ?>
                <option value="<?= $code ?>" <?= CURRENT_LANG === $code ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>