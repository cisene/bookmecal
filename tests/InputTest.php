<?php
/**
 * tests/InputTest.php
 * The shared input validation layer used by the public API and the admin forms.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/Input.php';

class InputTest extends TestCase {
    public function testDateAcceptsRealDatesOnly() {
        $this->assertSame('2026-10-22', Input::date('2026-10-22'));
        $this->assertSame('2028-02-29', Input::date('2028-02-29'));
        foreach (['2026-02-30', '2026-13-01', '26-10-22', '2026-10-22 10:00', '', '2026/10/22', '2026-1-2'] as $bad) {
            $this->assertNull(Input::date($bad), $bad);
        }
    }

    public function testTimeAcceptsHoursAndMinutes() {
        $this->assertSame('09:05', Input::time('09:05'));
        $this->assertSame('23:59', Input::time('23:59'));
        foreach (['24:00', '9:05', '09:60', '09:05:00', '', 'noon'] as $bad) {
            $this->assertNull(Input::time($bad), $bad);
        }
    }

    public function testEmail() {
        $this->assertSame('anna@example.se', Input::email('anna@example.se'));
        $this->assertNull(Input::email(''));
        $this->assertNull(Input::email('anna@'));
        $this->assertNull(Input::email("anna@example.se\r\nBcc: x@example.com"));
        $this->assertNull(Input::email(str_repeat('a', 64) . '@' . str_repeat('b', 190) . '.se'));
    }

    public function testTextLimitsLengthInCharactersNotBytes() {
        $this->assertSame('Åsa Öberg', Input::text('Åsa Öberg', 9));
        $this->assertNull(Input::text('Åsa Öberg', 8));
        $this->assertSame('', Input::text('', 10));
    }

    public function testTextRejectsControlCharactersAndBrokenUtf8() {
        $this->assertNull(Input::text("Anna\x00", 50));
        $this->assertNull(Input::text("Anna\nBcc", 50));
        $this->assertNull(Input::text("Anna\x1B[31m", 50));
        $this->assertNull(Input::text("Anna\u{0085}", 50));
        $this->assertNull(Input::text("Anna\u{2028}", 50));
        $this->assertNull(Input::text("\xC3\x28", 50));
    }

    public function testMultilineTextKeepsLineBreaksAndNormalizesThem() {
        $this->assertSame("Rad 1\nRad 2\n\tindrag", Input::text("Rad 1\r\nRad 2\r\tindrag", 50, true));
        $this->assertNull(Input::text("Rad 1\x00", 50, true));
    }

    public function testInt() {
        $this->assertSame(45, Input::int('45', 5, 720));
        $this->assertSame(0, Input::int('0', 0, 10));
        foreach (['4', '721', '45.5', '1e2', '', 'abc', '45 ', "45\n"] as $bad) {
            $this->assertNull(Input::int($bad, 5, 720), $bad);
        }
    }

    public function testDecimalAcceptsCommaAndRange() {
        $this->assertSame(450.5, Input::decimal('450,5', 0, 1000));
        $this->assertSame(25.0, Input::decimal('25', 0, 100));
        foreach (['-1', '100.01', '1e3', 'NaN', 'INF', '', '1.23456', '0x10'] as $bad) {
            $this->assertNull(Input::decimal($bad, 0, 100), $bad);
        }
    }

    public function testOneOfIsStrict() {
        $this->assertSame('se', Input::oneOf('se', ['se', 'no']));
        $this->assertNull(Input::oneOf('SE', ['se', 'no']));
        $this->assertNull(Input::oneOf('0', [0, 1]));
    }

    public function testPhone() {
        $this->assertSame('+46 70-123 45 67', Input::phone('+46 70-123 45 67'));
        $this->assertSame('(08) 123 456', Input::phone('(08) 123 456'));
        $this->assertNull(Input::phone('call me'));
        $this->assertNull(Input::phone('12'));
    }

    public function testUrl() {
        $this->assertSame('https://instagram.com/salong?x=1', Input::url('https://instagram.com/salong?x=1'));
        $this->assertNull(Input::url('javascript:alert(1)'));
        $this->assertNull(Input::url('ftp://example.com/'));
        $this->assertNull(Input::url('https://user:pw@example.com/'));
        $this->assertNull(Input::url('https://example.com/a b'));
        $this->assertNull(Input::url('https://example.com/' . str_repeat('a', 500)));
    }

    public function testIdCannotEscapeADirectory() {
        $this->assertSame('Fotvård-bas', Input::id('Fotvård-bas'));
        $this->assertSame('srv_6713a1.5', Input::id('srv_6713a1.5'));
        foreach (['../config', 'a/b', 'a\\b', '..', 'a b', '', str_repeat('a', 101)] as $bad) {
            $this->assertNull(Input::id($bad), $bad);
        }
    }
}
