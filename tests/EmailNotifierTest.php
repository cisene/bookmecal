<?php
/**
 * tests/EmailNotifierTest.php
 * Email sending through a captured transport: on/off switch, address checks, header injection,
 * texts with placeholders and the .ics attachment.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/EmailNotifier.php';

class EmailNotifierTest extends TestCase {
    private array $sent = [];

    private function notifier(array $smtp = []): EmailNotifier {
        $this->sent = [];
        $config = ['smtp' => $smtp + ['enabled' => true, 'from_email' => 'salong@example.com', 'from_name' => 'Salong Anna']];
        return new EmailNotifier($config, function (string $to, string $subject, string $body, string $headers): bool {
            $this->sent[] = compact('to', 'subject', 'body', 'headers');
            return true;
        });
    }

    private static function decodeSubject(string $subject): string {
        return iconv_mime_decode($subject, 0, 'UTF-8');
    }

    public function testLongSubjectsAreSplitIntoShortEncodedWords() {
        $subject = 'Din bokning är bekräftad: Medical Foot Care hos Lynn Isene 2099-07-28 kl. 08:00 – åäö';
        $encoded = EmailNotifier::encodeHeader($subject);
        foreach (explode("\r\n ", $encoded) as $word) {
            $this->assertMatchesRegularExpression('/^=\?UTF-8\?B\?[A-Za-z0-9+\/=]+\?=$/', $word);
            $this->assertLessThanOrEqual(75, strlen($word));
        }
        $this->assertGreaterThan(1, substr_count($encoded, '=?UTF-8'));
        $this->assertSame($subject, self::decodeSubject($encoded));
    }

    public function testNothingIsSentWhileEmailIsOff() {
        $notifier = $this->notifier(['enabled' => false]);
        $this->assertFalse($notifier->isEnabled());
        $this->assertFalse($notifier->send('anna@example.com', 'Hi', 'Body'));
        $this->assertSame([], $this->sent);

        // Only a real true turns it on
        $this->assertFalse($this->notifier(['enabled' => 'yes'])->isEnabled());
    }

    public function testRejectsBadAddresses() {
        $notifier = $this->notifier();
        $this->assertFalse($notifier->send('', 'Hi', 'Body'));
        $this->assertFalse($notifier->send("anna@example.com\r\nBcc: x@example.com", 'Hi', 'Body'));
        $this->assertFalse($this->notifier(['from_email' => 'not an address'])->send('anna@example.com', 'Hi', 'Body'));
        $this->assertSame([], $this->sent);
    }

    public function testHeaderValuesCannotInjectHeaders() {
        $notifier = $this->notifier(['from_name' => "Evil\r\nBcc: x@example.com"]);
        $this->assertTrue($notifier->send('anna@example.com', "Hi\r\nBcc: y@example.com", 'Body'));
        $mail = $this->sent[0];
        $this->assertStringNotContainsString("\nBcc:", $mail['headers']);
        $this->assertStringNotContainsString("\n", self::decodeSubject($mail['subject']));
        $this->assertStringContainsString('<salong@example.com>', $mail['headers']);
    }

    public function testReceiptUsesTheBookingLanguageAndPrivacyLink() {
        $notifier = $this->notifier();
        $vars = ['name' => 'Åsa {service}', 'service' => 'Fotvård', 'date' => '2026-10-27', 'time' => '10:00', 'end' => '10:45',
            'business' => 'Salong Anna', 'privacyUrl' => 'https://example.se/privacy.php'];
        $this->assertTrue($notifier->sendBookingReceived('asa@example.com', $vars, 'sv'));

        $mail = $this->sent[0];
        $this->assertSame('asa@example.com', $mail['to']);
        $this->assertStringContainsString('Fotvård 2026-10-27', self::decodeSubject($mail['subject']));
        // The name is inserted as is: its {service} is not replaced again
        $this->assertStringContainsString('Hej Åsa {service},', $mail['body']);
        $this->assertStringContainsString('Fotvård, 2026-10-27 kl. 10:00–10:45', $mail['body']);
        $this->assertStringContainsString("Salong Anna\n\nSå hanterar vi dina personuppgifter: https://example.se/privacy.php", $mail['body']);
        $this->assertStringContainsString('Content-Type: text/plain; charset=UTF-8', $mail['headers']);
    }

    public function testApprovalCarriesTheCalendarFile() {
        $notifier = $this->notifier();
        $ics = "BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n";
        $vars = ['name' => 'Bo', 'service' => 'Foot care', 'date' => '2026-10-27', 'time' => '10:00', 'end' => '10:45', 'business' => 'Salong'];
        $this->assertTrue($notifier->sendBookingDecision('bo@example.com', true, $vars, 'en', $ics));

        $mail = $this->sent[0];
        $this->assertMatchesRegularExpression('/Content-Type: multipart\/mixed; boundary="(bmc-[0-9a-f]+)"/', $mail['headers']);
        preg_match('/boundary="([^"]+)"/', $mail['headers'], $m);
        $this->assertStringContainsString("--{$m[1]}\r\nContent-Type: text/plain; charset=UTF-8", $mail['body']);
        $this->assertStringContainsString('Your booking is confirmed', $mail['body']);
        $this->assertStringContainsString('Content-Type: text/calendar; method=PUBLISH; charset=UTF-8; name="booking.ics"', $mail['body']);
        $this->assertStringContainsString(chunk_split(base64_encode($ics), 76, "\r\n"), $mail['body']);
        $this->assertStringEndsWith("--{$m[1]}--\r\n", $mail['body']);
        $this->assertStringNotContainsString('personal data', $mail['body']);
    }

    public function testRejectionHasNoAttachment() {
        $notifier = $this->notifier();
        $vars = ['name' => 'Bo', 'service' => 'Foot care', 'date' => '2026-10-27', 'time' => '10:00', 'end' => '10:45', 'business' => 'Salong'];
        $this->assertTrue($notifier->sendBookingDecision('bo@example.com', false, $vars, 'en', 'ignored'));
        $this->assertStringContainsString('Content-Type: text/plain', $this->sent[0]['headers']);
        $this->assertStringContainsString('cannot offer you Foot care on 2026-10-27 at 10:00', $this->sent[0]['body']);
    }

    public function testAdminAlertAndNotificationAddress() {
        $this->assertNull($this->notifier()->notificationEmail());
        $this->assertNull($this->notifier(['notification_email' => 'nope'])->notificationEmail());

        $notifier = $this->notifier(['notification_email' => 'owner@example.com']);
        $this->assertSame('owner@example.com', $notifier->notificationEmail());
        $vars = ['name' => 'Bo', 'email' => 'bo@example.com', 'service' => 'Foot care', 'date' => '2026-10-27', 'time' => '10:00', 'end' => '10:45',
            'adminUrl' => 'https://example.se/admin/pending.php'];
        $this->assertTrue($notifier->sendAdminAlert('owner@example.com', $vars, 'en'));
        $this->assertStringContainsString("Bo <bo@example.com>\n\nAnswer it here: https://example.se/admin/pending.php", $this->sent[0]['body']);
    }
}
