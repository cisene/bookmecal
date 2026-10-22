<?php
/**
 * tests/CancelLinkTest.php
 * Customer cancellation links: token per booking, constant format, URL building.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/CancelLink.php';

class CancelLinkTest extends TestCase {
    protected function setUp(): void {
        CancelLink::setKey(str_repeat('c', 64));
    }

    protected function tearDown(): void {
        CancelLink::setKey(null);
    }

    public function testTokenIsTiedToTheBookingAndKey() {
        $token = CancelLink::token('book_a1');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $token);
        $this->assertTrue(CancelLink::check('book_a1', $token));
        $this->assertFalse(CancelLink::check('book_a2', $token));
        $this->assertFalse(CancelLink::check('book_a1', strtoupper($token)));
        $this->assertFalse(CancelLink::check('book_a1', $token . "\n"));
        $this->assertFalse(CancelLink::check('book_a1', ''));

        CancelLink::setKey(str_repeat('d', 64));
        $this->assertFalse(CancelLink::check('book_a1', $token));
    }

    public function testUrlNeedsASiteAddress() {
        $booking = ['id' => 'book_a1', 'language' => 'sv'];
        $this->assertSame('', CancelLink::url('', $booking));
        $this->assertSame('https://example.se/boka/cancel.php?id=book_a1&token=' . CancelLink::token('book_a1') . '&lang=sv',
            CancelLink::url('https://example.se/boka/', $booking));
    }
}
