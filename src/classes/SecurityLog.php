<?php
/**
 * src/classes/SecurityLog.php
 * Security events (rejected bookings, failed logins, lockouts, CSRF failures) as JSON lines in
 * src/log/security.log. One line per event, so fail2ban or grep can watch the file directly.
 * Never logs customer names, emails or passwords. The file rotates to security.log.1 at 1 MB.
 */

require_once __DIR__ . '/RateLimiter.php';

class SecurityLog {
    private const MAX_BYTES = 1048576;

    private static ?string $file = null;

    /**
     * Overrides the log file (tests).
     */
    public static function setFile(?string $file): void {
        self::$file = $file;
    }

    public static function path(): string {
        return self::$file ?? dirname(__DIR__) . '/log/security.log';
    }

    /**
     * Appends one event. $context holds extra non-personal fields (bucket, username, page).
     * Falls back to the PHP error log when the file cannot be written.
     */
    public static function record(string $event, array $context = []): void {
        $entry = [
            'time' => date(DATE_ATOM),
            'event' => $event,
            'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? 'cli'),
            'client' => RateLimiter::clientKey(),
        ] + array_map(function ($value) {
            return is_scalar($value) ? mb_substr((string)$value, 0, 200) : null;
        }, $context);
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        $file = self::path();
        if (is_file($file) && @filesize($file) > self::MAX_BYTES) {
            @rename($file, $file . '.1');
        }
        if ($line === false || @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX) === false) {
            error_log('bookmecal security: ' . $event . ' from ' . $entry['ip']);
        }
    }

    /**
     * The newest $limit events, newest first.
     */
    public static function recent(int $limit = 100): array {
        $file = self::path();
        $lines = is_file($file) ? (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [];
        $events = [];
        foreach (array_reverse(array_slice($lines, -$limit)) as $line) {
            $event = json_decode($line, true);
            if (is_array($event)) {
                $events[] = $event;
            }
        }
        return $events;
    }
}
