<?php
/**
 * tests/FormTokenTest.php
 * Signed booking form tokens: signature, minimum and maximum age.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/FormToken.php';

class FormTokenTest extends TestCase {
    protected function setUp(): void {
        FormToken::setSecret(str_repeat('k', 64));
    }

    protected function tearDown(): void {
        FormToken::setSecret(null);
    }

    public function testValidAfterMinimumAge() {
        $token = FormToken::issue(1000);
        $this->assertSame(FormToken::VALID, FormToken::check($token, 1000 + FormToken::MIN_AGE));
    }

    public function testTooFastBeforeMinimumAge() {
        $this->assertSame(FormToken::TOO_FAST, FormToken::check(FormToken::issue(1000), 1001));
    }

    public function testExpiredAfterMaximumAge() {
        $this->assertSame(FormToken::EXPIRED, FormToken::check(FormToken::issue(1000), 1001 + FormToken::MAX_AGE));
    }

    public function testRejectsTamperedOrForeignTokens() {
        $token = FormToken::issue(1000);
        $this->assertSame(FormToken::INVALID, FormToken::check('999' . substr($token, 4), 2000));
        $this->assertSame(FormToken::INVALID, FormToken::check('', 2000));
        $this->assertSame(FormToken::INVALID, FormToken::check('1000.abc', 2000));

        FormToken::setSecret(str_repeat('x', 64));
        $this->assertSame(FormToken::INVALID, FormToken::check($token, 2000));
    }

    public function testRejectsTokensFromTheFuture() {
        $this->assertSame(FormToken::INVALID, FormToken::check(FormToken::issue(5000), 1000));
    }
}
