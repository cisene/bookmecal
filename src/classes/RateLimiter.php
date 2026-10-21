<?php
/**
 * src/classes/RateLimiter.php
 * Sliding-window request limits per client, stored as small JSON files in data/ratelimit/.
 * Client keys are hashed, so the files hold no IP addresses.
 */

require_once __DIR__ . '/JsonStore.php';
require_once __DIR__ . '/ResourceProvider.php';

class RateLimiter {
    /** Files untouched for this long are removed during the occasional sweep. */
    private const STALE_SECONDS = 86400;

    private string $dir;

    public function __construct(?string $dir = null) {
        $this->dir = $dir ?? ResourceProvider::dataPath('ratelimit');
    }

    /**
     * Records a hit for $key in $bucket. Returns false when $limit hits already fall within the
     * last $windowSeconds (the hit is then not recorded). Fails open if the store is not writable.
     */
    public function hit(string $bucket, string $key, int $limit, int $windowSeconds, ?int $now = null): bool {
        $now = $now ?? time();
        $allowed = true;
        JsonStore::update($this->path($bucket, $key), function (array $data) use ($now, $limit, $windowSeconds, &$allowed) {
            $hits = array_values(array_filter($data['hits'] ?? [], function ($t) use ($now, $windowSeconds) {
                return is_int($t) && $t > $now - $windowSeconds;
            }));
            if (count($hits) >= $limit) {
                $allowed = false;
            } else {
                $hits[] = $now;
            }
            return ['hits' => $hits];
        });

        if (random_int(1, 200) === 1) {
            $this->sweep($now);
        }
        return $allowed;
    }

    /**
     * Key for the requesting client: the IPv4 address, or the /64 prefix of an IPv6 address
     * (one household or server usually controls a whole /64). Proxy headers are not trusted.
     */
    public static function clientKey(?string $remoteAddr = null): string {
        $ip = $remoteAddr ?? (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return 'unknown';
        }
        return strlen($packed) === 16 ? bin2hex(substr($packed, 0, 8)) . '::/64' : $ip;
    }

    private function path(string $bucket, string $key): string {
        return $this->dir . '/' . hash('sha256', $bucket . '|' . $key) . '.json';
    }

    private function sweep(int $now): void {
        foreach (glob($this->dir . '/*.json') ?: [] as $file) {
            if (@filemtime($file) < $now - self::STALE_SECONDS) {
                @unlink($file);
            }
        }
    }
}
