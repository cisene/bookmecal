<?php

class Localization {
    private static ?Localization $instance = null;
    private string $currentLang;
    private static array $dictionaries = [];
    
    public const DEFAULT_LANG = 'sv';
    public const SUPPORTED_LANGS = [
        'sv', 'en', 'de', 'th', 'tl', 'fr', 'es', 'pt', 'it', 'el', 'ko', 'vi', 'ja', 'zh-CN'
    ];

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->currentLang = $this->determineLanguage();
    }

    /**
     * Singleton-mönster för att nå instansen globalt.
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function determineLanguage(): string {
        // 1. Kolla GET-parameter (t.ex. ?lang=en)
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

        // 2. Kolla Cookie
        if (isset($_COOKIE['app_lang']) && in_array($_COOKIE['app_lang'], self::SUPPORTED_LANGS, true)) {
            return $_COOKIE['app_lang'];
        }

        // 3. Kolla Session
        if (isset($_SESSION['app_lang']) && in_array($_SESSION['app_lang'], self::SUPPORTED_LANGS, true)) {
            return $_SESSION['app_lang'];
        }

        // 4. Standardfallback
        return self::DEFAULT_LANG;
    }

    public function getCurrentLang(): string {
        return $this->currentLang;
    }

    /**
     * Laddar in språkfilen (JSON) i minnet med statisk cachning och BOM-rensning.
     */
    public function loadDictionary(string $lang): array {
        if (!isset(self::$dictionaries[$lang])) {
            $filePath = __DIR__ . "/lang/{$lang}.json";
            if (file_exists($filePath)) {
                $jsonContent = file_get_contents($filePath);
                $jsonContent = preg_replace('/^ï»¿/', '', $jsonContent);
                self::$dictionaries[$lang] = json_decode($jsonContent, true) ?? [];
            } else {
                $fallbackPath = __DIR__ . "/lang/" . self::DEFAULT_LANG . ".json";
                if (file_exists($fallbackPath)) {
                    $fallbackContent = file_get_contents($fallbackPath);
                    $fallbackContent = preg_replace('/^ï»¿/', '', $fallbackContent);
                    self::$dictionaries[$lang] = json_decode($fallbackContent, true) ?? [];
                } else {
                    self::$dictionaries[$lang] = [];
                }
            }
        }

        return self::$dictionaries[$lang];
    }

    /**
     * Översätter en nyckel med dynamiska platshållare (t.ex. {name}).
     */
    public function translate(string $key, array $placeholders = [], ?string $langOverride = null): string {
        $lang = $langOverride ?? $this->currentLang;
        $dictionary = $this->loadDictionary($lang);

        $translation = $dictionary[$key] ?? $key;

        foreach ($placeholders as $placeholderKey => $value) {
            $translation = str_replace("{{$placeholderKey}}", $value, $translation);
        }

        return $translation;
    }
}

// --- BAKÅTKOMPATIBEL BRYGGA ---
if (!function_exists('__t')) {
    function __t(string $key, array $placeholders = [], ?string $langOverride = null): string {
        return Localization::getInstance()->translate($key, $placeholders, $langOverride);
    }
}	