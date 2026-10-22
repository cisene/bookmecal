<?php
/**
 * src/classes/CancelLink.php
 * Secret per-booking link that lets the customer cancel (cancel.php). The token is an HMAC of the
 * booking ID with a key in data/secrets.json, so nothing extra is stored and every email can carry it.
 */

require_once __DIR__ . '/Secrets.php';

class CancelLink {
    private static ?string $key = null;

    public static function token(string $bookingId): string {
        return substr(hash_hmac('sha256', 'cancel|' . $bookingId, self::key()), 0, 32);
    }

    public static function check(string $bookingId, string $token): bool {
        return preg_match('/^[0-9a-f]{32}$/D', $token) === 1 && hash_equals(self::token($bookingId), $token);
    }

    /**
     * Full link for a booking, or '' without a site URL (see BookingEmails::siteUrl()).
     */
    public static function url(string $siteUrl, array $booking): string {
        $id = (string)($booking['id'] ?? '');
        if ($siteUrl === '' || $id === '') {
            return '';
        }
        $query = ['id' => $id, 'token' => self::token($id)];
        if (is_string($booking['language'] ?? null) && $booking['language'] !== '') {
            $query['lang'] = $booking['language'];
        }
        return $siteUrl . 'cancel.php?' . http_build_query($query);
    }

    /** For tests: use a fixed key instead of data/secrets.json. */
    public static function setKey(?string $key): void {
        self::$key = $key;
    }

    private static function key(): string {
        return self::$key ?? Secrets::key('cancel_secret');
    }
}
