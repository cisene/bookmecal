<?php
/**
 * tests/GoogleSyncTest.php
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/GoogleSync.php';

class GoogleSyncTest extends TestCase {
    public function testGoogleSyncInstantiation() {
        // GoogleSync handles missing credentials gracefully with null/false states
        $sync = new GoogleSync();
        $this->assertInstanceOf(GoogleSync::class, $sync);
    }
}