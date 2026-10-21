<?php
// Localization.php - Multi-language translation class with JSON dictionary support

class Localization {
    private static ?Localization $instance = null;
    private string $currentLang;
    private static array $dictionaries = [];
    
    public const DEFAULT_LANG = 'en';
    public const SUPPORTED_LANGS = [
        'en', 'sv', 'de', 'fr', 'es'
    ];

    public function __construct(?string $lang = null) {
        if (session_status() === PHP_SESSION_NONE && !headers_sent() && PHP_SAPI !== 'cli') {
            session_start();
        }
        $this->currentLang = ($lang !== null && in_array($lang, self::SUPPORTED_LANGS, true))
            ? $lang
            : $this->determineLanguage();
    }

    /**
     * Singleton instance accessor.
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function determineLanguage(): string {
        // 1. Check GET parameter (e.g. ?lang=sv)
        if (isset($_GET['lang']) && in_array($_GET['lang'], self::SUPPORTED_LANGS, true)) {
            $lang = $_GET['lang'];
            
            if (!headers_sent()) {
                setcookie('app_lang', $lang, [
                    'expires'  => time() + (86400 * 30),
                    'path'     => '/',
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }
            $_SESSION['app_lang'] = $lang;

            return $lang;
        }

        // 2. Check Cookie
        if (isset($_COOKIE['app_lang']) && in_array($_COOKIE['app_lang'], self::SUPPORTED_LANGS, true)) {
            return $_COOKIE['app_lang'];
        }

        // 3. Check Session
        if (isset($_SESSION['app_lang']) && in_array($_SESSION['app_lang'], self::SUPPORTED_LANGS, true)) {
            return $_SESSION['app_lang'];
        }

        // 4. Fallback to default
        return self::DEFAULT_LANG;
    }

    public function getCurrentLang(): string {
        return $this->currentLang;
    }

    /**
     * Loads the language JSON dictionary into memory with static caching.
     */
    public function loadDictionary(string $lang): array {
        // Only whitelisted codes may reach the filesystem path
        if (!in_array($lang, self::SUPPORTED_LANGS, true)) {
            $lang = self::DEFAULT_LANG;
        }
        if (!isset(self::$dictionaries[$lang])) {
            $filePath = dirname(__DIR__) . "/language/{$lang}.json";
            if (file_exists($filePath)) {
                $jsonContent = file_get_contents($filePath);
                self::$dictionaries[$lang] = json_decode($jsonContent, true) ?? [];
            } else {
                // Fallback to default language dictionary if requested file is missing
                $fallbackPath = dirname(__DIR__) . "/language/" . self::DEFAULT_LANG . ".json";
                self::$dictionaries[$lang] = file_exists($fallbackPath) 
                    ? (json_decode(file_get_contents($fallbackPath), true) ?? [])
                    : [];
            }
        }

        return self::$dictionaries[$lang];
    }

    /**
     * Translates a key with optional dynamic placeholders (e.g., {name}).
     */
    public function translate(string $key, array $placeholders = [], ?string $langOverride = null): string {
        $lang = $langOverride ?? $this->currentLang;
        $dictionary = $this->loadDictionary($lang);

        $translation = $dictionary[$key] ?? $key;

        foreach ($placeholders as $placeholderKey => $value) {
            $translation = str_replace("{{$placeholderKey}}", (string)$value, $translation);
        }

        return $translation;
    }
}

// --- BACKWARD-COMPATIBLE GLOBAL HELPER ---
if (!function_exists('__t')) {
    function __t(string $key, array $placeholders = [], ?string $langOverride = null): string {
        return Localization::getInstance()->translate($key, $placeholders, $langOverride);
    }
}