<?php
/**
 * tests/CustomerMessagesTest.php
 * Messages to customers: who is in each group, one email per customer, the footer, the history.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/CustomerMessages.php';

class CustomerMessagesTest extends TestCase {
    private array $sent = [];
    private string $historyPath;

    protected function setUp(): void {
        $this->historyPath = sys_get_temp_dir() . '/bmc_messages_' . bin2hex(random_bytes(6)) . '.json';
    }

    protected function tearDown(): void {
        @unlink($this->historyPath);
    }

    private function messages(array $settings = [], bool $deliver = true): CustomerMessages {
        $this->sent = [];
        $notifier = new EmailNotifier(['smtp' => ['enabled' => true, 'from_email' => 'salong@example.com', 'from_name' => 'Salong']],
            function (string $to, string $subject, string $body, string $headers) use ($deliver): bool {
                $this->sent[] = compact('to', 'subject', 'body', 'headers');
                return $deliver && $to !== 'fails@example.com';
            });
        return new CustomerMessages($notifier, $settings + ['business_name' => 'Salong Anna'], $this->historyPath);
    }

    private static function bookings(): array {
        $booking = fn (string $id, string $date, string $email, string $name, array $extra = []) => $extra + [
            'id' => $id, 'date' => $date, 'time' => '10:00', 'customer_email' => $email, 'customer_name' => $name,
            'status' => 'approved', 'service_id' => 'massage', 'employee_id' => 'emp_a', 'language' => 'sv',
        ];
        return [
            'book_1' => $booking('book_1', '2026-10-01', 'old@example.com', 'Olle'),
            'book_2' => $booking('book_2', '2026-11-02', 'bo@example.com', 'Bo'),
            'book_3' => $booking('book_3', '2026-11-05', 'Cia@Example.com', 'Cia', ['employee_id' => 'emp_b', 'language' => 'en']),
            'book_4' => $booking('book_4', '2026-11-03', 'dan@example.com', 'Dan', ['status' => 'rejected']),
            'book_5' => $booking('book_5', '2026-12-01', 'cia@example.com', 'Cia Ek', ['service_id' => 'nails', 'employee_id' => 'emp_b']),
            'book_6' => $booking('book_6', '2026-11-04', 'not an address', 'X'),
        ];
    }

    private static function emails(array $recipients): array {
        return array_column(array_values($recipients), 'email');
    }

    public function testUpcomingTakesHeldBookingsFromToday() {
        $recipients = CustomerMessages::recipients(self::bookings(), ['group' => 'upcoming'], '2026-10-23');
        // Past, rejected and malformed bookings are left out; one entry per address, any case
        $this->assertSame(['bo@example.com', 'cia@example.com'], self::emails($recipients));
        // The latest booking gives the name and language
        $this->assertSame('Cia Ek', $recipients['cia@example.com']['name']);
        $this->assertSame('sv', $recipients['cia@example.com']['language']);
    }

    public function testUpcomingCanBeNarrowed() {
        $all = self::bookings();
        $this->assertSame(['bo@example.com', 'Cia@Example.com'], self::emails(CustomerMessages::recipients($all, ['group' => 'upcoming', 'to' => '2026-11-30'], '2026-10-23')));
        $this->assertSame(['Cia@Example.com'], self::emails(CustomerMessages::recipients($all, ['group' => 'upcoming', 'from' => '2026-11-05', 'to' => '2026-11-05'], '2026-10-23')));
        $this->assertSame(['cia@example.com'], self::emails(CustomerMessages::recipients($all, ['group' => 'upcoming', 'employee_id' => 'emp_b'], '2026-10-23')));
        $this->assertSame(['cia@example.com'], self::emails(CustomerMessages::recipients($all, ['group' => 'upcoming', 'service_id' => 'nails'], '2026-10-23')));
        // A start date in the past still means today
        $this->assertSame(['bo@example.com', 'cia@example.com'], self::emails(CustomerMessages::recipients($all, ['group' => 'upcoming', 'from' => '2026-01-01'], '2026-10-23')));
    }

    public function testAllAndOne() {
        $all = self::bookings();
        $this->assertSame(['bo@example.com', 'cia@example.com', 'dan@example.com', 'old@example.com'],
            self::emails(CustomerMessages::recipients($all, ['group' => 'all'], '2026-10-23')));
        $this->assertSame(['old@example.com'], self::emails(CustomerMessages::recipients($all, ['group' => 'one', 'email' => 'OLD@example.com'], '2026-10-23')));
        // Only addresses that booked: no open mail relay
        $this->assertSame([], CustomerMessages::recipients($all, ['group' => 'one', 'email' => 'someone@example.com'], '2026-10-23'));
        $this->assertSame([], CustomerMessages::recipients($all, ['group' => 'nobody'], '2026-10-23'));
    }

    public function testEachCustomerGetsTheirOwnEmail() {
        $messages = $this->messages(['public_url' => 'https://example.se/boka']);
        $recipients = CustomerMessages::recipients(self::bookings(), ['group' => 'upcoming'], '2026-10-23');
        $result = $messages->send($recipients, 'Hej {name}', "Vi har ny adress, {name}.\n");

        $this->assertSame(['sent' => 2, 'failed' => 0, 'error' => ''], $result);
        $this->assertSame(['bo@example.com', 'cia@example.com'], array_column($this->sent, 'to'));
        $this->assertSame(EmailNotifier::encodeHeader('Hej Bo'), $this->sent[0]['subject']);
        $this->assertStringContainsString("Vi har ny adress, Bo.\n\n-- \nDu får det här mejlet eftersom du har bokat hos Salong Anna.", $this->sent[0]['body']);
        $this->assertStringContainsString('https://example.se/boka/privacy.php', $this->sent[0]['body']);
        $this->assertStringNotContainsString('bo@example.com', $this->sent[1]['headers'] . $this->sent[1]['body']);
    }

    public function testFooterFollowsTheCustomersLanguage() {
        [, $body] = $this->messages()->compose(['email' => 'a@example.com', 'name' => '', 'language' => 'en'], 'S', 'Hello {name}');
        $this->assertStringStartsWith('Hello a@example.com', $body);
        $this->assertStringContainsString('You get this email because you have booked with Salong Anna.', $body);
        $this->assertStringNotContainsString('privacy.php', $body);
    }

    public function testFailuresAreCounted() {
        $messages = $this->messages();
        $recipients = [
            'ok@example.com' => ['email' => 'ok@example.com', 'name' => 'Ok', 'language' => null],
            'fails@example.com' => ['email' => 'fails@example.com', 'name' => 'F', 'language' => null],
        ];
        $result = $messages->send($recipients, 'S', 'T');
        $this->assertSame(1, $result['sent']);
        $this->assertSame(1, $result['failed']);
    }

    public function testHistoryKeepsTheNewestFirst() {
        $messages = $this->messages();
        $this->assertSame([], $messages->history());
        for ($i = 1; $i <= 55; $i++) {
            $this->assertTrue($messages->record(['subject' => "S{$i}"]));
        }
        $history = $messages->history();
        $this->assertCount(50, $history);
        $this->assertSame('S55', $history[0]['subject']);
    }
}
