<?php
/**
 * src/classes/AdminAccounts.php
 * Admin accounts stored in an Apache-style .htpasswd file (username:hash per line).
 * Verifies bcrypt, SHA-crypt ($5$/$6$) and Apache MD5 ($apr1$) hashes, and writes new
 * passwords as bcrypt, which both PHP and Apache understand.
 */

class AdminAccounts {
    /** Usernames: 3-20 letters, digits or underscores. */
    public const USERNAME_PATTERN = '/^[A-Za-z0-9_]{3,20}$/D';
    public const PASSWORD_MIN_LENGTH = 10;
    /** bcrypt only uses the first 72 bytes; longer passwords would be silently cut. */
    public const PASSWORD_MAX_BYTES = 72;

    /** A real bcrypt hash (of a throwaway password) checked for unknown users to keep timing even. */
    private const DUMMY_HASH = '$2y$12$RSe7GPBmPjuYQKU5tG5Ns.9QukrOpmdL.fJ6X/jIJ/reVGqstYby6';

    private const APR1_ALPHABET = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    private string $file;

    public function __construct(?string $file = null) {
        $this->file = $file ?? dirname(__DIR__) . '/admin/.htpasswd';
    }

    /**
     * username => hash.
     */
    public function all(): array {
        $users = [];
        foreach (is_file($this->file) ? (file($this->file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [] as $line) {
            if (strpos($line, ':') !== false) {
                [$user, $hash] = explode(':', $line, 2);
                $users[trim($user)] = trim($hash);
            }
        }
        return $users;
    }

    public function exists(string $username): bool {
        return isset($this->all()[$username]);
    }

    /**
     * Checks a password. Takes about the same time for unknown users, so the response time
     * does not reveal which usernames exist. Upgrades old hash formats to bcrypt on success.
     */
    public function verify(string $username, string $password): bool {
        $hash = $this->all()[$username] ?? null;
        if ($hash === null || $password === '') {
            password_verify($password, self::DUMMY_HASH);
            return false;
        }
        $valid = str_starts_with($hash, '$apr1$')
            ? hash_equals($hash, self::apr1($password, explode('$', $hash)[2] ?? ''))
            : password_verify($password, $hash);

        if ($valid && password_needs_rehash($hash, PASSWORD_BCRYPT) && strlen($password) <= self::PASSWORD_MAX_BYTES) {
            $this->setPassword($username, $password);
        }
        return $valid;
    }

    /**
     * Short fingerprint of the stored hash; changes whenever the password changes.
     * Sessions keep it so a password change or removed account ends other sessions.
     */
    public function fingerprint(string $username): ?string {
        $hash = $this->all()[$username] ?? null;
        return $hash === null ? null : substr(hash('sha256', $hash), 0, 16);
    }

    /**
     * Error message for a password that does not meet the policy, or null when it is acceptable.
     */
    public static function passwordProblem(string $password): ?string {
        if (mb_strlen($password) < self::PASSWORD_MIN_LENGTH) {
            return 'The password must be at least ' . self::PASSWORD_MIN_LENGTH . ' characters.';
        }
        if (strlen($password) > self::PASSWORD_MAX_BYTES) {
            return 'The password must be at most ' . self::PASSWORD_MAX_BYTES . ' bytes.';
        }
        return null;
    }

    public function setPassword(string $username, string $password): bool {
        if (!preg_match(self::USERNAME_PATTERN, $username)) {
            return false;
        }
        $users = $this->all();
        $users[$username] = password_hash($password, PASSWORD_BCRYPT);
        return $this->write($users);
    }

    public function delete(string $username): bool {
        $users = $this->all();
        if (!isset($users[$username])) {
            return false;
        }
        unset($users[$username]);
        return $this->write($users);
    }

    private function write(array $users): bool {
        $content = '';
        foreach ($users as $user => $hash) {
            $content .= $user . ':' . $hash . "\n";
        }
        return @file_put_contents($this->file, $content, LOCK_EX) !== false;
    }

    /**
     * Apache's MD5-based "$apr1$" hash (what htpasswd -m creates), for verifying existing accounts.
     */
    public static function apr1(string $password, string $salt): string {
        $salt = substr($salt, 0, 8);
        $length = strlen($password);
        $text = $password . '$apr1$' . $salt;
        $binary = md5($password . $salt . $password, true);
        for ($i = $length; $i > 0; $i -= 16) {
            $text .= substr($binary, 0, min(16, $i));
        }
        for ($i = $length; $i > 0; $i >>= 1) {
            $text .= ($i & 1) ? "\0" : ($password[0] ?? '');
        }
        $binary = md5($text, true);
        for ($i = 0; $i < 1000; $i++) {
            $round = ($i & 1) ? $password : $binary;
            if ($i % 3) {
                $round .= $salt;
            }
            if ($i % 7) {
                $round .= $password;
            }
            $round .= ($i & 1) ? $binary : $password;
            $binary = md5($round, true);
        }

        // Encode the 16 bytes in Apache's byte order and 6-bit alphabet
        $encoded = '';
        foreach ([[0, 6, 12], [1, 7, 13], [2, 8, 14], [3, 9, 15], [4, 10, 5]] as [$a, $b, $c]) {
            $value = (ord($binary[$a]) << 16) | (ord($binary[$b]) << 8) | ord($binary[$c]);
            for ($n = 0; $n < 4; $n++, $value >>= 6) {
                $encoded .= self::APR1_ALPHABET[$value & 0x3f];
            }
        }
        $value = ord($binary[11]);
        for ($n = 0; $n < 2; $n++, $value >>= 6) {
            $encoded .= self::APR1_ALPHABET[$value & 0x3f];
        }
        return '$apr1$' . $salt . '$' . $encoded;
    }
}
