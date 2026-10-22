<?php
/**
 * src/classes/Input.php
 * One place for validating request values from the public API and the admin forms.
 * Every method takes the raw (trimmed) string and returns the clean value, or null when invalid.
 * Output is still escaped where it is used (e() in templates); this layer keeps bad data out of storage.
 */

class Input {
    /** Longest accepted email address (RFC 5321 path limit). */
    public const EMAIL_MAX = 254;

    /**
     * A calendar date in YYYY-MM-DD that actually exists (no 2026-02-30).
     */
    public static function date(string $value): ?string {
        $parsed = DateTime::createFromFormat('!Y-m-d', $value);
        return ($parsed && $parsed->format('Y-m-d') === $value) ? $value : null;
    }

    /**
     * A time of day in HH:MM (24-hour).
     */
    public static function time(string $value): ?string {
        $parsed = DateTime::createFromFormat('!H:i', $value);
        return ($parsed && $parsed->format('H:i') === $value) ? $value : null;
    }

    /**
     * An email address that PHP's validator accepts and that fits in a mail header.
     */
    public static function email(string $value): ?string {
        if ($value === '' || strlen($value) > self::EMAIL_MAX || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }
        return $value;
    }

    /**
     * Free text: valid UTF-8, at most $maxLength characters, no control characters.
     * With $multiline, line breaks and tabs are allowed and line endings become "\n".
     * An empty string is valid; callers decide whether a field is required.
     */
    public static function text(string $value, int $maxLength, bool $multiline = false): ?string {
        if (!mb_check_encoding($value, 'UTF-8') || mb_strlen($value, 'UTF-8') > $maxLength) {
            return null;
        }
        if ($multiline) {
            $value = str_replace(["\r\n", "\r"], "\n", $value);
        }
        // C0 controls and DEL, plus C1 controls and the Unicode line/paragraph separators
        $forbidden = $multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{0080}-\x{009F}\x{2028}\x{2029}]/u' : '/[\x00-\x1F\x7F\x{0080}-\x{009F}\x{2028}\x{2029}]/u';
        return preg_match($forbidden, $value) === 0 ? $value : null;
    }

    /**
     * A whole number between $min and $max (inclusive). No decimals, no exponent.
     */
    public static function int(string $value, int $min, int $max): ?int {
        if (!preg_match('/^-?\d{1,18}$/D', $value)) {
            return null;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
        return $parsed === false ? null : $parsed;
    }

    /**
     * A decimal number between $min and $max; a comma is accepted as decimal separator.
     */
    public static function decimal(string $value, float $min, float $max): ?float {
        $value = str_replace(',', '.', $value);
        if (!preg_match('/^-?\d{1,12}(\.\d{1,4})?$/D', $value)) {
            return null;
        }
        $parsed = (float)$value;
        return ($parsed >= $min && $parsed <= $max) ? $parsed : null;
    }

    /**
     * The value when it is one of $allowed (strict comparison), otherwise null.
     */
    public static function oneOf(string $value, array $allowed): ?string {
        return in_array($value, $allowed, true) ? $value : null;
    }

    /**
     * A phone number: digits, spaces and + - ( ) only, 3 to 30 characters.
     */
    public static function phone(string $value): ?string {
        return preg_match('/^\+?[0-9 ()\-]{3,30}$/D', $value) === 1 ? $value : null;
    }

    /**
     * An absolute http(s) URL without user name or password, at most $maxLength characters.
     */
    public static function url(string $value, int $maxLength = 500): ?string {
        if (strlen($value) > $maxLength || preg_match('/[\x00-\x20\x7F]/', $value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        $parts = parse_url($value);
        return in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true) && !isset($parts['user']) && !isset($parts['pass'])
            ? $value : null;
    }

    /**
     * An identifier used as a key or in a file name: letters (any script), digits, _ . -,
     * 1 to $maxLength characters. Never a path separator, so it cannot leave a directory.
     */
    public static function id(string $value, int $maxLength = 100): ?string {
        return preg_match('/^[\p{L}\p{N}_.\-]{1,' . $maxLength . '}$/Du', $value) === 1 && !str_contains($value, '..') ? $value : null;
    }
}
