<?php
/**
 * tests/LanguageFilesTest.php
 * Language dictionaries: en is the reference, sv must be complete, other languages may be
 * partial (missing keys fall back to English) but must not contain unknown keys.
 * Also checks that every translation key used in the code exists in en.json.
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/Localization.php';

class LanguageFilesTest extends TestCase {
    private const SRC = __DIR__ . '/../src';
    private const COMPLETE_LANGS = ['en', 'sv'];

    /** Keys built at runtime from a prefix plus a value, listed here so they are checked too. */
    private const DYNAMIC_KEYS = [
        'admin.status.' => ['pending', 'approved', 'confirmed', 'rejected', 'cancelled', 'expired'],
        'admin.pending.done.' => ['approve', 'reject', 'delete'],
        'admin.pending.' => ['approve', 'reject'],
        'admin.pending.mail.' => ['sent', 'failed', 'off'],
        'admin.login.' => ['expired', 'signedOut'],
        'admin.config.done.' => ['saved', 'feedRenewed', 'feedDisabled'],
        'admin.services.done.' => ['saved', 'deleted'],
        'admin.employees.done.' => ['saved', 'deleted'],
        'admin.holidays.done.' => ['added', 'deleted'],
        'admin.users.done.' => ['saved', 'deleted'],
        'admin.google.step' => ['1', '2', '3'],
        'admin.employees.' => ['givenName', 'familyName', 'email', 'jobTitle', 'phoneNumber', 'mobileNumber', 'swishPaymentNumber', 'organisationNumber'],
        'admin.nav.' => ['dashboard', 'pending', 'services', 'employees', 'hours', 'holidays', 'slots', 'config', 'users'],
        'common.weekday.' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        'common.country.' => ['se', 'no', 'fi', 'ee', 'lv', 'lt'],
        'booking.error.' => ['missingFields', 'invalidDate', 'invalidTime', 'invalidEmail', 'invalidName', 'unknownService', 'invalidRequest', 'slotUnavailable', 'saveFailed', 'rateLimited', 'tooManyPending', 'formExpired', 'tooFast'],
    ];

    private static function load(string $lang): array {
        $decoded = json_decode(file_get_contents(self::SRC . "/language/{$lang}.json"), true);
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
        foreach (glob(self::SRC . '/language/*.json') as $file) {
            $lang = basename($file, '.json');
            $cases[$lang] = [$lang];
        }
        return $cases;
    }

    /**
     * Literal translation keys referenced in PHP and JavaScript sources.
     */
    private static function keysUsedInCode(): array {
        $keys = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::SRC, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!in_array($file->getExtension(), ['php', 'js'], true)) {
                continue;
            }
            // File names such as 'privacy.php' or 'booking.ics' are not keys
            preg_match_all("/'((?:admin|booking|cancel|common|email|meta|privacy)\.(?!(?:php|ics|js|json|css)')[a-zA-Z0-9.]*[a-zA-Z0-9])'/", file_get_contents($file->getPathname()), $m);
            foreach ($m[1] as $key) {
                $keys[$key] = $file->getFilename();
            }
        }
        foreach (self::DYNAMIC_KEYS as $prefix => $suffixes) {
            foreach ($suffixes as $suffix) {
                $keys[$prefix . $suffix] = 'dynamic';
            }
        }
        // Prefixes used for dynamic keys are not keys themselves
        return array_diff_key($keys, array_flip(array_map(fn ($p) => rtrim($p, '.'), array_keys(self::DYNAMIC_KEYS))));
    }

    public function testSupportedLanguagesMatchFiles() {
        $files = array_keys(self::languageProvider());
        $supported = Localization::SUPPORTED_LANGS;
        sort($files);
        sort($supported);
        $this->assertSame($files, $supported);
    }

    public function testKeysUseNamespacedFormat() {
        foreach (array_keys(self::load('en')) as $key) {
            $this->assertMatchesRegularExpression('/^[a-z]+(\.[a-zA-Z0-9]+)+$/', $key);
        }
    }

    public function testCompleteLanguagesHaveIdenticalKeys() {
        $reference = array_keys(self::load('en'));
        sort($reference);
        foreach (self::COMPLETE_LANGS as $lang) {
            $keys = array_keys(self::load($lang));
            sort($keys);
            $this->assertSame($reference, $keys, "{$lang}.json must contain exactly the keys of en.json");
        }
    }

    #[DataProvider('languageProvider')]
    public function testLanguageHasOnlyKnownKeysAndAName(string $lang) {
        $dictionary = self::load($lang);
        $this->assertSame([], array_keys(array_diff_key($dictionary, self::load('en'))), "{$lang}.json has keys missing from en.json");
        $this->assertNotEmpty($dictionary['meta.nativeName'] ?? '', "{$lang}.json needs meta.nativeName");
    }

    #[DataProvider('languageProvider')]
    public function testLanguageKeepsPlaceholders(string $lang) {
        $reference = self::load('en');
        foreach (self::load($lang) as $key => $text) {
            $this->assertNotSame('', trim($text), "{$lang}: {$key} is empty");
            $this->assertSame(self::placeholders($reference[$key] ?? ''), self::placeholders($text), "{$lang}: {$key}");
        }
    }

    public function testEveryKeyUsedInCodeExistsInEnglish() {
        $missing = array_diff_key(self::keysUsedInCode(), self::load('en'));
        $this->assertSame([], $missing, 'Translation keys used in code but missing from en.json');
    }

    public function testMissingKeysFallBackToEnglish() {
        $loc = new Localization('de');
        $this->assertSame(self::load('en')['admin.nav.dashboard'], $loc->translate('admin.nav.dashboard'));
    }
}
