<?php
/**
 * tests/BookingEmailsTest.php
 * Booking emails: who gets what, the calendar file on approval, links from public_url.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/BookingEmails.php';
require_once __DIR__ . '/../src/classes/CancelLink.php';

class BookingEmailsTest extends TestCase {
    private array $sent = [];

    protected function setUp(): void {
        CancelLink::setKey(str_repeat('c', 64));
    }

    protected function tearDown(): void {
        CancelLink::setKey(null);
    }

    private function emails(array $smtp = [], array $settings = []): BookingEmails {
        $this->sent = [];
        $notifier = new EmailNotifier(['smtp' => $smtp + ['enabled' => true, 'from_email' => 'salong@example.com', 'from_name' => 'Salong']],
            function (string $to, string $subject, string $body, string $headers): bool {
                $this->sent[] = compact('to', 'subject', 'body', 'headers');
                return true;
            });
        return new BookingEmails($notifier, $settings + ['business_name' => 'Salong Anna'], 'en');
    }

    private static function booking(): array {
        return ['id' => 'book_abc1', 'service_id' => 'no-such-service', 'date' => '2026-11-03', 'time' => '10:00', 'end_time' => '10:45',
            'customer_name' => 'Bo Ek', 'customer_email' => 'bo@example.com', 'language' => 'sv', 'status' => 'pending'];
    }

    public function testRequestSendsReceiptAndAlert() {
        $emails = $this->emails(['notification_email' => 'owner@example.com'], ['public_url' => 'https://example.se/boka']);
        $this->assertTrue($emails->requestReceived(self::booking()));

        $this->assertSame(['owner@example.com', 'bo@example.com'], array_column($this->sent, 'to'));
        // Alert in the admin language, receipt in the customer's
        $this->assertStringContainsString('A new booking request', $this->sent[0]['body']);
        $this->assertStringContainsString('https://example.se/boka/admin/pending.php', $this->sent[0]['body']);
        $this->assertStringContainsString('Tack för din bokningsförfrågan', $this->sent[1]['body']);
        $this->assertStringContainsString('https://example.se/boka/privacy.php', $this->sent[1]['body']);
        $this->assertStringContainsString('Salong Anna', $this->sent[1]['body']);
    }

    public function testNoAlertWithoutAddressAndNoLinksWithoutUrl() {
        $this->emails()->requestReceived(self::booking());
        $this->assertSame(['bo@example.com'], array_column($this->sent, 'to'));
        $this->assertStringNotContainsString('http', $this->sent[0]['body']);
    }

    public function testApprovalAttachesTheAppointment() {
        $this->assertTrue($this->emails()->decided(self::booking(), true));
        $body = $this->sent[0]['body'];
        $this->assertStringContainsString('name="booking.ics"', $body);
        preg_match('/base64\r\n\r\n([A-Za-z0-9+\/=\r\n]+)\r\n--/', $body, $m);
        $ics = base64_decode(str_replace("\r\n", '', $m[1]));
        $this->assertStringContainsString('UID:book_abc1@bookmecal', $ics);
        $this->assertStringContainsString('SUMMARY:no-such-service – Salong Anna', $ics);

        $this->emails()->decided(self::booking(), false);
        $this->assertStringContainsString('Tyvärr', $this->sent[0]['body']);
        $this->assertStringNotContainsString('booking.ics', $this->sent[0]['body']);
    }

    public function testNothingIsSentWhileEmailIsOff() {
        $emails = $this->emails(['enabled' => false, 'notification_email' => 'owner@example.com']);
        $this->assertFalse($emails->isEnabled());
        $this->assertFalse($emails->requestReceived(self::booking()));
        $this->assertSame([], $this->sent);
    }

    public function testSiteUrlValidation() {
        $this->assertTrue(BookingEmails::isValidSiteUrl('https://example.se/'));
        $this->assertTrue(BookingEmails::isValidSiteUrl('http://bookmecal.local'));
        $this->assertFalse(BookingEmails::isValidSiteUrl('javascript:alert(1)'));
        $this->assertFalse(BookingEmails::isValidSiteUrl('ftp://example.se/'));
        $this->assertFalse(BookingEmails::isValidSiteUrl('https://user:pw@example.se/'));
        $this->assertFalse(BookingEmails::isValidSiteUrl('https://example.se/?x=1'));
        $this->assertFalse(BookingEmails::isValidSiteUrl('https://example.se/#top'));
        $this->assertSame('https://example.se/boka/', BookingEmails::siteUrl(['public_url' => 'https://example.se/boka/']));
        $this->assertSame('', BookingEmails::siteUrl(['public_url' => 'nope']));
        $this->assertSame('', BookingEmails::siteUrl([]));
    }

    public function testCustomerEmailsCarryTheCancelLink() {
        $emails = $this->emails(['notification_email' => 'owner@example.com'], ['public_url' => 'https://example.se/']);
        $link = 'https://example.se/cancel.php?id=book_abc1&token=' . CancelLink::token('book_abc1') . '&lang=sv';

        $emails->requestReceived(self::booking());
        $this->assertStringNotContainsString('cancel.php', $this->sent[0]['body'], 'not in the admin alert');
        $this->assertStringContainsString($link, $this->sent[1]['body']);

        $this->sent = [];
        $emails->decided(self::booking(), true);
        $this->assertStringContainsString($link, $this->sent[0]['body']);
        $this->sent = [];
        $emails->decided(self::booking(), false);
        $this->assertStringNotContainsString('cancel.php', $this->sent[0]['body']);

        $this->sent = [];
        $emails->reminder(self::booking() + ['status' => 'approved'], '800', 'SEK');
        $this->assertStringContainsString($link, $this->sent[0]['body']);
        $this->assertStringContainsString('2026-11-03 10:00', $this->sent[0]['body']);
        $this->assertStringContainsString('800 SEK', $this->sent[0]['body']);
    }

    public function testCancellationAlertGoesToTheBusiness() {
        $this->assertFalse($this->emails()->cancelledByCustomer(self::booking()));
        $this->assertSame([], $this->sent);

        $this->assertTrue($this->emails(['notification_email' => 'owner@example.com'])->cancelledByCustomer(self::booking()));
        $this->assertSame('owner@example.com', $this->sent[0]['to']);
        $this->assertStringContainsString('Bo Ek <bo@example.com>', $this->sent[0]['body']);
    }
}
