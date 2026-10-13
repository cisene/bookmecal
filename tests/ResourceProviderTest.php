<?php
/**
 * tests/ResourceProviderTest.php
 * Unit tests for ResourceProvider abstraction and settings loading.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/ResourceProvider.php';

class ResourceProviderTest extends TestCase {
    
    public function testInitTimezoneReturnsStringOrNull() {
        $tz = ResourceProvider::initTimezone();
        $this->assertTrue(is_string($tz) || $tz === null);
    }

    public function testGetSettingsReturnsArrayOrNull() {
        $settings = ResourceProvider::getSettings();
        $this->assertTrue(is_array($settings) || $settings === null);
    }

    public function testGetNonexistentServiceReturnsNull() {
        $service = ResourceProvider::getServiceById('completely_fake_sku_999');
        $this->assertNull($service);
    }
}