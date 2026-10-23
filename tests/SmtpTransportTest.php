<?php
/**
 * tests/SmtpTransportTest.php
 * Unit tests for SmtpTransport: the SMTP conversation against a scripted server on a socket pair.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/SmtpTransport.php';

class SmtpTransportTest extends TestCase {
    /** @var resource|null The server's end of the socket pair. */
    private $server = null;

    /**
     * A transport talking to a fake server that has already queued $replies (one per command,
     * the greeting first). SMTP is lockstep, so queued replies are read in order.
     */
    private function transport(array $replies, string $encryption = 'none', string $username = '', string $host = 'localhost'): SmtpTransport {
        [$client, $this->server] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        fwrite($this->server, implode('', array_map(fn ($r) => $r . "\r\n", $replies)));
        return new SmtpTransport($host, 25, $encryption, $username, 'p4ss', 'booking@example.com', 5, fn () => $client);
    }

    /** Everything the client sent. */
    private function sent(): string {
        stream_set_blocking($this->server, false);
        return (string)stream_get_contents($this->server);
    }

    public function testSendsAMessageWithAuthentication() {
        $send = $this->transport(['220 mail.test ESMTP', '250-mail.test', '250-8BITMIME', '250 AUTH LOGIN PLAIN', '235 ok', '250 ok', '250 ok', '354 go', '250 queued'], 'none', 'user@example.com');
        $headers = "MIME-Version: 1.0\r\nFrom: Test <booking@example.com>\r\nContent-Type: text/plain; charset=UTF-8\r\n";
        $this->assertTrue($send('kund@example.com', '=?UTF-8?B?SGVq?=', "Hej\n.hidden dot\nBye", $headers), $send->lastError());
        $this->assertSame('', $send->lastError());

        $sent = $this->sent();
        $this->assertStringContainsString('AUTH PLAIN ' . base64_encode("\0user@example.com\0p4ss") . "\r\n", $sent);
        $this->assertStringContainsString("MAIL FROM:<booking@example.com> BODY=8BITMIME\r\n", $sent);
        $this->assertStringContainsString("RCPT TO:<kund@example.com>\r\nDATA\r\n", $sent);
        $this->assertStringContainsString("To: <kund@example.com>\r\nSubject: =?UTF-8?B?SGVq?=\r\nDate: ", $sent);
        $this->assertMatchesRegularExpression('/Message-ID: <[0-9a-f]{32}@example\.com>\r\n\r\nHej\r\n/', $sent);
        // A line starting with a dot is doubled so it does not end the message
        $this->assertStringContainsString("\r\n..hidden dot\r\nBye\r\n.\r\nQUIT\r\n", $sent);
    }

    public function testFoldedSubjectKeepsItsFoldAndLosesOtherLineBreaks() {
        $send = $this->transport(['220 hi', '250 mail.test', '250 ok', '250 ok', '354 go', '250 queued']);
        $this->assertTrue($send('kund@example.com', "=?UTF-8?B?QQ==?=\r\n =?UTF-8?B?Qg==?=\nBcc: x@example.com", 'B', ''), $send->lastError());
        $this->assertStringContainsString("Subject: =?UTF-8?B?QQ==?=\r\n =?UTF-8?B?Qg==?= Bcc: x@example.com\r\nDate: ", $this->sent());
    }

    public function testServerRefusalIsReported() {
        $send = $this->transport(['220 hi', '250 mail.test', '250 ok', '550 5.1.1 No such user']);
        $this->assertFalse($send('nobody@example.com', 'S', 'B', ''));
        $this->assertStringContainsString('550', $send->lastError());
        $this->assertStringContainsString('RCPT TO', $send->lastError());
    }

    public function testFailedSignInHidesTheCredentials() {
        $send = $this->transport(['220 hi', '250-mail.test', '250 AUTH PLAIN', '535 bad credentials'], 'none', 'user@example.com');
        $this->assertFalse($send('kund@example.com', 'S', 'B', ''));
        $this->assertStringContainsString('535', $send->lastError());
        $this->assertStringNotContainsString(base64_encode("\0user@example.com\0p4ss"), $send->lastError());
    }

    public function testStartTlsMustBeOffered() {
        $send = $this->transport(['220 hi', '250 mail.test'], 'starttls');
        $this->assertFalse($send('kund@example.com', 'S', 'B', ''));
        $this->assertStringContainsString('STARTTLS', $send->lastError());
    }

    public function testPasswordIsNeverSentUnencryptedToAnotherMachine() {
        $send = $this->transport(['220 hi'], 'none', 'user@example.com', 'smtp.example.com');
        $this->assertFalse($send('kund@example.com', 'S', 'B', ''));
        $this->assertStringContainsString('without encryption', $send->lastError());
        $this->assertSame('', $this->sent());
    }

    public function testSilentServerFails() {
        $send = $this->transport([]);
        fclose($this->server);
        $this->assertFalse($send('kund@example.com', 'S', 'B', ''));
        $this->assertStringContainsString('did not answer', $send->lastError());
    }
}
