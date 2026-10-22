<?php
/**
 * src/classes/FormToken.php
 * Signed timestamp embedded in the booking page. The API rejects bookings sent without a page
 * load, sooner than a person could fill in the form, or from a page left open for too long.
 * The signing key is generated on first use and kept in data/secrets.json.
 */

require_once __DIR__ . '/Secrets.php';

class FormToken {
    public const MIN_AGE = 3;
    public const MAX_AGE = 86400;

    public const VALID = 'valid';
    public const INVALID = 'invalid';
    public const TOO_FAST = 'too_fast';
    public const EXPIRED = 'expired';

    private static ?string $secret = null;

    public static function issue(?int $now = null): string {
        $issued = (string)($now ?? time());
        return $issued . '.' . self::sign($issued);
    }

    /**
     * Returns one of the VALID / INVALID / TOO_FAST / EXPIRED constants.
     */
    public static function check(string $token, ?int $now = null): string {
        if (!preg_match('/^(\d{1,12})\.([0-9a-f]{64})$/', $token, $m) || !hash_equals(self::sign($m[1]), $m[2])) {
            return self::INVALID;
        }
        $age = ($now ?? time()) - (int)$m[1];
        if ($age < self::MIN_AGE) {
            return $age < -60 ? self::INVALID : self::TOO_FAST;
        }
        return $age > self::MAX_AGE ? self::EXPIRED : self::VALID;
    }

    /** For tests: use a fixed key instead of data/secrets.json. */
    public static function setSecret(?string $secret): void {
        self::$secret = $secret;
    }

    private static function sign(string $issued): string {
        return hash_hmac('sha256', 'booking-form|' . $issued, self::secret());
    }

    private static function secret(): string {
        return self::$secret ?? Secrets::key('form_secret');
    }
}
