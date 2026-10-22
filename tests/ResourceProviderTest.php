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

    public function testSetDurationCleanupAndExclusiveComeFromItsTreatments() {
        $services = [
            'a' => ['duration' => 45, 'cleanup' => 15],
            'b' => ['duration' => 30, 'cleanup' => 5, 'exclusive' => true],
            'set' => ['items' => ['a', 'b'], 'duration' => 999],
            'nested' => ['items' => ['set']],
            'missing' => ['items' => ['a', 'nope']],
        ];
        $this->assertSame(75, ResourceProvider::serviceDuration($services['set'], $services));
        $this->assertSame(5, ResourceProvider::serviceCleanup($services['set'], $services));
        $this->assertTrue(ResourceProvider::isServiceExclusive($services['set'], $services));
        $this->assertFalse(ResourceProvider::isServiceExclusive($services['a'], $services));
        $this->assertNull(ResourceProvider::serviceDuration($services['nested'], $services));
        $this->assertNull(ResourceProvider::serviceDuration($services['missing'], $services));
        $this->assertSame(45, ResourceProvider::serviceDuration($services['a'], $services));
    }
}
