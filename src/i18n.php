<?php
// i18n.php - Internationalization Middleware & Translation Engine

// Define supported locale codes
define('DEFAULT_LANG', 'sv');
define('SUPPORTED_LANGS', ['sv', 'en', 'de', 'th', 'tl', 'fr', 'es', 'pt', 'it', 'el', 'ko', 'vi', 'ja', 'zh-CN']);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Determines the active language code based on Request Params > Cookie > Session > Default.
 */
function getActiveLanguage(): string {
    // 1. Check GET parameter (e.g., ?lang=en)
    if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
        $lang = $_GET['lang'];
        
        // Save to cookie (valid for 30 days) and session
        setcookie('app_lang', $lang, [
            'expires'  => time() + (86400 * 30),
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        $_SESSION['app_lang'] = $lang;

        return $lang;
    }

    // 2. Check Cookie
    if (isset($_COOKIE['app_lang']) && in_array($_COOKIE['app_lang'], SUPPORTED_LANGS, true)) {
        return $_COOKIE['app_lang'];
    }

    // 3. Check Session
    if (isset($_SESSION['app_lang']) && in_array($_SESSION['app_lang'], SUPPORTED_LANGS, true)) {
        return $_SESSION['app_lang'];
    }

    // 4. Fallback to Default
    return DEFAULT_LANG;
}

// Global active language constant
define('CURRENT_LANG', getActiveLanguage());

/**
 * Loads the language dictionary JSON file into memory.
 */
function loadDictionary(string $lang): array {
    static $dictionaries = [];

    if (!isset($dictionaries[$lang])) {
        $filePath = __DIR__ . "/lang/{$lang}.json";
        if (file_exists($filePath)) {
            $jsonContent = file_get_contents($filePath);
            $dictionaries[$lang] = json_decode($jsonContent, true) ?? [];
        } else {
            // Fallback to default language dictionary if requested file is missing
            $fallbackPath = __DIR__ . "/lang/" . DEFAULT_LANG . ".json";
            $dictionaries[$lang] = file_exists($fallbackPath) 
                ? json_decode(file_get_contents($fallbackPath), true) 
                : [];
        }
    }

    return $dictionaries[$lang];
}

/**
 * Translates a key with dynamic placeholder interpolation (e.g., {name}).
 */
function __t(string $key, array $placeholders = [], ?string $langOverride = null): string {
    $lang = $langOverride ?? CURRENT_LANG;
    $dictionary = loadDictionary($lang);

    $translation = $dictionary[$key] ?? $key;

    // Replace placeholders: {name} => Value
    foreach ($placeholders as $placeholderKey => $value) {
        $translation = str_replace("{{$placeholderKey}}", $value, $translation);
    }

    return $translation;
}