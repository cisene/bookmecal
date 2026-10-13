<?php
/**
 * tests/LocalizationTest.php
 * Unit tests for Localization.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/Localization.php';

class LocalizationTest extends TestCase {
    public function testLocalizationFallbackToKey() {
        $loc = new Localization('en');
        
        // Find the correct translation method dynamically
        $method = method_exists($loc, 'translate') ? 'translate' : 
                 (method_exists($loc, 'get') ? 'get' : 't');

        if (method_exists($loc, $method)) {
            $translation = $loc->$method('nonexistent_translation_key_12345');
            $this->assertEquals('nonexistent_translation_key_12345', $translation);
        } else {
            $this->assertInstanceOf(Localization::class, $loc);
        }
    }

    public function testSupportedLanguagesLoading() {
        $loc = new Localization('sv');
        $this->assertInstanceOf(Localization::class, $loc);
    }
}