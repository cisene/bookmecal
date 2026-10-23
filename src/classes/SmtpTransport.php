<?php
/**
 * src/classes/SmtpTransport.php
 * Sends mail through an SMTP server (RFC 5321) instead of PHP mail(), so the emails leave
 * through the mail provider of the sender's domain and pass its SPF/DKIM checks. Supports
 * STARTTLS (port 587), implicit TLS (port 465) and, for a relay on the same machine only,
 * an unencrypted connection. Signs in with AUTH PLAIN or AUTH LOGIN when a username is set.
 * Used by EmailNotifier as its transport: ($to, $encodedSubject, $body, $headers) => bool.
 */

class SmtpTransport {
    public const ENCRYPTIONS = ['starttls', 'ssl', 'none'];

    private string $lastError = '';
    /** @var resource|null */
    private $stream = null;
    /** @var callable(string $address, int $timeout): (resource|false) */
    private $connect;

    /**
     * $connect opens the connection (tests); by default a TCP or TLS socket to $host:$port.
     */
    public function __construct(
        private string $host,
        private int $port,
        private string $encryption,
        private string $username,
        private string $password,
        private string $fromEmail,
        private int $timeout = 15,
        ?callable $connect = null,
    ) {
        $this->connect = $connect ?? function (string $address, int $timeout) {
            $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $this->host]]);
            return @stream_socket_client($address, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        };
    }

    /** Why the last send failed ('' after a success), for the admin's test email. */
    public function lastError(): string {
        return $this->lastError;
    }

    /** Whether the server is this machine, where an unencrypted connection is acceptable. */
    public static function isLocalHost(string $host): bool {
        return in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true);
    }

    public function __invoke(string $to, string $subject, string $body, string $headers): bool {
        $this->lastError = '';
        try {
            $this->deliver($to, $subject, $body, $headers);
            return true;
        } catch (RuntimeException $e) {
            $this->lastError = $e->getMessage();
            return false;
        } finally {
            if (is_resource($this->stream)) {
                @fwrite($this->stream, "QUIT\r\n");
                @fclose($this->stream);
            }
            $this->stream = null;
        }
    }

    private function deliver(string $to, string $subject, string $body, string $headers): void {
        if (!in_array($this->encryption, self::ENCRYPTIONS, true) || $this->host === '' || $this->port < 1 || $this->port > 65535) {
            throw new RuntimeException('The SMTP server, port or encryption is not set correctly.');
        }
        if ($this->encryption === 'none' && $this->username !== '' && !self::isLocalHost($this->host)) {
            throw new RuntimeException('Refusing to send the SMTP password without encryption. Choose STARTTLS or SSL/TLS.');
        }
        foreach ([$to, $this->fromEmail] as $address) {
            if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException('Invalid email address.');
            }
        }

        $scheme = $this->encryption === 'ssl' ? 'ssl' : 'tcp';
        $stream = ($this->connect)("{$scheme}://{$this->host}:{$this->port}", $this->timeout);
        if (!is_resource($stream)) {
            throw new RuntimeException("Could not connect to {$this->host}:{$this->port}.");
        }
        $this->stream = $stream;
        stream_set_timeout($stream, $this->timeout);

        $this->expect(220);
        $extensions = $this->ehlo();
        if ($this->encryption === 'starttls') {
            if (!isset($extensions['STARTTLS'])) {
                throw new RuntimeException('The server does not offer STARTTLS.');
            }
            $this->command('STARTTLS', 220);
            if (@stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT) !== true) {
                throw new RuntimeException('The TLS handshake failed (check the server name and its certificate).');
            }
            $extensions = $this->ehlo();
        }
        if ($this->username !== '') {
            $this->authenticate($extensions['AUTH'] ?? '');
        }

        $this->command('MAIL FROM:<' . $this->fromEmail . '>' . (isset($extensions['8BITMIME']) ? ' BODY=8BITMIME' : ''), 250);
        $this->command('RCPT TO:<' . $to . '>', [250, 251]);
        $this->command('DATA', 354);
        $this->write($this->message($to, $subject, $body, $headers) . "\r\n.\r\n");
        $this->expect(250, 'sending the message');
    }

    /** Sends EHLO and returns the offered extensions as KEYWORD => parameters. */
    private function ehlo(): array {
        $lines = $this->command('EHLO ' . $this->clientName(), 250);
        $extensions = [];
        foreach (array_slice($lines, 1) as $line) {
            [$keyword, $params] = array_pad(explode(' ', trim($line), 2), 2, '');
            $extensions[strtoupper($keyword)] = strtoupper($params);
        }
        return $extensions;
    }

    private function authenticate(string $mechanisms): void {
        $offered = explode(' ', $mechanisms);
        if (in_array('PLAIN', $offered, true) || !in_array('LOGIN', $offered, true)) {
            $this->command('AUTH PLAIN ' . base64_encode("\0{$this->username}\0{$this->password}"), 235, true);
            return;
        }
        $this->command('AUTH LOGIN', 334);
        $this->command(base64_encode($this->username), 334, true);
        $this->command(base64_encode($this->password), 235, true);
    }

    /** The full message: the caller's MIME headers plus To, Subject, Date and Message-ID. */
    private function message(string $to, string $subject, string $body, string $headers): string {
        $domain = substr((string)strrchr($this->fromEmail, '@'), 1);
        $message = rtrim(str_replace(["\r\n", "\r"], "\n", $headers), "\n") . "\n"
            . "To: <{$to}>\n"
            // Folded lines (a line break followed by a space) are kept; other line breaks are removed
            . 'Subject: ' . str_replace("\r\n", "\n", preg_replace('/\r?\n(?![ \t])|\r(?!\n)/', ' ', $subject)) . "\n"
            . 'Date: ' . date(DATE_RFC2822) . "\n"
            . 'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . ">\n"
            . "\n" . str_replace(["\r\n", "\r"], "\n", $body);
        // CRLF line endings; a line starting with a dot gets a second one (RFC 5321 4.5.2)
        $lines = array_map(fn ($line) => str_starts_with($line, '.') ? '.' . $line : $line, explode("\n", rtrim($message, "\n")));
        return implode("\r\n", $lines);
    }

    private function clientName(): string {
        $name = (string)gethostname();
        return preg_match('/^[A-Za-z0-9.-]+$/', $name) ? $name : 'localhost';
    }

    /**
     * Sends a command and checks the reply code. $secret hides the command in error messages.
     * Returns the reply lines.
     */
    private function command(string $command, int|array $expected, bool $secret = false): array {
        $this->write($command . "\r\n");
        return $this->expect($expected, $secret ? '(credentials)' : $command);
    }

    private function write(string $data): void {
        for ($written = 0; $written < strlen($data); $written += $bytes) {
            $bytes = @fwrite($this->stream, substr($data, $written));
            if ($bytes === false || $bytes === 0) {
                throw new RuntimeException('The connection to the SMTP server was lost.');
            }
        }
    }

    /** Reads one (possibly multi-line) reply and checks its code. */
    private function expect(int|array $expected, string $after = 'connecting'): array {
        $lines = [];
        do {
            $line = @fgets($this->stream, 1024);
            if ($line === false) {
                throw new RuntimeException('The SMTP server did not answer (after ' . $after . ').');
            }
            $lines[] = substr(rtrim($line, "\r\n"), 4);
            $code = (int)substr($line, 0, 3);
        } while (($line[3] ?? ' ') === '-');
        if (!in_array($code, (array)$expected, true)) {
            $reply = mb_substr(preg_replace('/[\x00-\x1F\x7F]/', ' ', implode(' ', $lines)) ?? '', 0, 200);
            throw new RuntimeException("The SMTP server answered {$code} {$reply} (after {$after}).");
        }
        return $lines;
    }
}
