<?php
/**
 * src/classes/Secrets.php
 * Random signing keys kept in data/secrets.json, generated on first use, and other values that
 * must stay out of the tracked config files (the SMTP password).
 */

require_once __DIR__ . '/JsonStore.php';
require_once __DIR__ . '/ResourceProvider.php';

class Secrets {
    private static array $cache = [];
    private static ?string $path = null;

    /**
     * The key stored under $name (64 hex characters), created if missing.
     */
    public static function key(string $name): string {
        if (isset(self::$cache[$name])) {
            return self::$cache[$name];
        }
        $path = self::$path ?? ResourceProvider::dataPath('secrets.json');
        $key = JsonStore::read($path)[$name] ?? null;
        if (!self::valid($key)) {
            // Generate under the store's lock so concurrent first requests agree on one key
            JsonStore::update($path, function (array $data) use ($name, &$key) {
                if (!self::valid($data[$name] ?? null)) {
                    $data[$name] = bin2hex(random_bytes(32));
                }
                $key = $data[$name];
                return $data;
            });
            @chmod($path, 0640);
        }
        return self::$cache[$name] = (string)$key;
    }

    /** A stored value such as a password, or '' when none is saved. */
    public static function value(string $name): string {
        $value = JsonStore::read(self::$path ?? ResourceProvider::dataPath('secrets.json'))[$name] ?? '';
        return is_string($value) ? $value : '';
    }

    /** Saves a value ('' removes it); the file is readable by the owner and group only. */
    public static function store(string $name, string $value): bool {
        $path = self::$path ?? ResourceProvider::dataPath('secrets.json');
        $saved = JsonStore::update($path, function (array $data) use ($name, $value) {
            if ($value === '') {
                unset($data[$name]);
            } else {
                $data[$name] = $value;
            }
            return $data;
        });
        @chmod($path, 0640);
        return $saved;
    }

    /** For tests: use another file (null = data/secrets.json) and forget cached keys. */
    public static function setPath(?string $path): void {
        self::$path = $path;
        self::$cache = [];
    }

    private static function valid($key): bool {
        return is_string($key) && strlen($key) >= 32;
    }
}
