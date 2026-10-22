<?php
/**
 * src/classes/Localization.php
 * JSON dictionary translations (src/language/{lang}.json) with namespaced keys,
 * per-key fallback to English and the global __t() helper.
 */

require_once __DIR__ . '/ResourceProvider.php';
require_once __DIR__ . '/SecurityHeaders.php';

class Localization {
    public const FALLBACK_LANG = 'en';

    // Must match the files in src/language/ (enforced by LanguageFilesTest)
    public const SUPPORTED_LANGS = [
        'en', 'sv', 'de', 'el', 'es', 'fr', 'it', 'ja', 'ko', 'pt', 'th', 'tl', 'vi', 'zh-CN'
    ];

    private const COOKIE = 'app_lang';
    private const LANGUAGE_DIR = __DIR__ . '/../language';

    private static ?Localization $instance = null;
    private static array $dictionaries = [];
    private string $currentLang;

    public function __construct(?string $lang = null) {
        $this->currentLang = self::isSupported($lang) ? $lang : $this->determineLanguage();
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function isSupported(?string $lang): bool {
        return is_string($lang) && in_array($lang, self::SUPPORTED_LANGS, true);
    }

    /**
     * Order: ?lang= (remembered in a cookie), cookie, configured app language, English.
     */
    private function determineLanguage(): string {
        $requested = $_GET['lang'] ?? null;
        if (self::isSupported($requested)) {
            if (!headers_sent()) {
                setcookie(self::COOKIE, $requested, [
                    'expires' => time() + 86400 * 365,
                    'path' => '/',
                    'httponly' => true,
                    'secure' => SecurityHeaders::isHttps(),
                    'samesite' => 'Lax',
                ]);
            }
            return $requested;
        }

        $cookie = $_COOKIE[self::COOKIE] ?? null;
        if (self::isSupported($cookie)) {
            return $cookie;
        }

        $configured = ResourceProvider::getConfig()['app']['language'] ?? null;
        return self::isSupported($configured) ? $configured : self::FALLBACK_LANG;
    }

    public function getCurrentLang(): string {
        return $this->currentLang;
    }

    public function loadDictionary(string $lang): array {
        if (!self::isSupported($lang)) {
            $lang = self::FALLBACK_LANG;
        }
        if (!isset(self::$dictionaries[$lang])) {
            self::$dictionaries[$lang] = JsonStore::read(self::LANGUAGE_DIR . "/{$lang}.json") ?? [];
        }
        return self::$dictionaries[$lang];
    }

    /**
     * Translates $key, falling back to English and finally to the key itself.
     * Placeholders are written as {name} in the dictionary.
     */
    public function translate(string $key, array $placeholders = [], ?string $langOverride = null): string {
        $translation = $this->loadDictionary($langOverride ?? $this->currentLang)[$key]
            ?? $this->loadDictionary(self::FALLBACK_LANG)[$key]
            ?? $key;

        // One pass, so a value that itself contains {name} (e.g. a customer name) is not replaced again
        $pairs = [];
        foreach ($placeholders as $name => $value) {
            $pairs['{' . $name . '}'] = (string)$value;
        }
        return strtr($translation, $pairs);
    }

    /**
     * All translations whose key starts with $prefix (for handing to JavaScript).
     */
    public function exportPrefix(string $prefix): array {
        $merged = $this->loadDictionary($this->currentLang) + $this->loadDictionary(self::FALLBACK_LANG);
        return array_filter($merged, function ($key) use ($prefix) {
            return strncmp($key, $prefix, strlen($prefix)) === 0;
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Language code => native language name, for language switchers.
     */
    public function availableLanguages(): array {
        $languages = [];
        foreach (self::SUPPORTED_LANGS as $lang) {
            $languages[$lang] = $this->loadDictionary($lang)['meta.nativeName'] ?? $lang;
        }
        return $languages;
    }
}

if (!function_exists('__t')) {
    function __t(string $key, array $placeholders = [], ?string $langOverride = null): string {
        return Localization::getInstance()->translate($key, $placeholders, $langOverride);
    }
}
