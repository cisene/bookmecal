<?php
/**
 * tests/LanguageFilesTest.php
 * Keeps every language dictionary in sync with en.json (keys, key format and placeholders).
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/Localization.php';

class LanguageFilesTest extends TestCase {
    private const LANG_DIR = __DIR__ . '/../src/language';

    private static function load(string $lang): array {
        $decoded = json_decode(file_get_contents(self::LANG_DIR . "/{$lang}.json"), true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function placeholders(string $text): array {
        preg_match_all('/\{([a-zA-Z_]+)\}/', $text, $m);
        $found = array_unique($m[1]);
        sort($found);
        return $found;
    }

    public static function languageProvider(): array {
        $cases = [];
        foreach (glob(self::LANG_DIR . '/*.json') as $file) {
            $lang = basename($file, '.json');
            $cases[$lang] = [$lang];
        }
        return $cases;
    }

    public function testSupportedLanguagesMatchFiles() {
        $files = array_keys(self::languageProvider());
        $supported = Localization::SUPPORTED_LANGS;
        sort($files);
        sort($supported);
        $this->assertSame($files, $supported);
    }

    public function testReferenceKeysUseNamespacedFormat() {
        foreach (array_keys(self::load('en')) as $key) {
            $this->assertMatchesRegularExpression('/^[a-z]+(\.[a-z][a-zA-Z]*)+$/', $key);
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('languageProvider')]
    public function testLanguageHasSameKeysAsEnglish(string $lang) {
        $this->assertSame(array_keys(self::load('en')), array_keys(self::load($lang)));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('languageProvider')]
    public function testLanguageKeepsPlaceholders(string $lang) {
        $reference = self::load('en');
        foreach (self::load($lang) as $key => $text) {
            $this->assertNotSame('', trim($text), "{$lang}: {$key} is empty");
            $this->assertSame(self::placeholders($reference[$key] ?? ''), self::placeholders($text), "{$lang}: {$key}");
        }
    }
}
