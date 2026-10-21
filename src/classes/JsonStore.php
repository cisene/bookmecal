<?php
/**
 * src/classes/JsonStore.php
 * Single place for reading and writing JSON data files with flock() concurrency control.
 */

class JsonStore {
    private const ENCODE_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /**
     * Returns the decoded array, or null when the file is missing or not a JSON object/array.
     * Takes a shared lock, so it waits for a running update() instead of seeing a half-written file.
     */
    public static function read(string $path): ?array {
        if (!is_file($path)) {
            return null;
        }
        $fp = @fopen($path, 'rb');
        if (!$fp) {
            return null;
        }
        try {
            flock($fp, LOCK_SH);
            $content = stream_get_contents($fp);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        return $content === false ? null : self::decode($content);
    }

    /**
     * Replaces the file contents with $data.
     */
    public static function write(string $path, array $data): bool {
        return self::update($path, function () use ($data) {
            return $data;
        });
    }

    /**
     * Read-modify-write under one exclusive lock. $mutator receives the current data
     * (empty array when missing) and returns the new data.
     */
    public static function update(string $path, callable $mutator): bool {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            return false;
        }

        $fp = @fopen($path, 'c+b');
        if (!$fp) {
            return false;
        }
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return false;
        }

        try {
            $current = self::decode((string)stream_get_contents($fp)) ?? [];
            $json = json_encode($mutator($current), self::ENCODE_FLAGS);
            if ($json === false) {
                return false;
            }
            ftruncate($fp, 0);
            rewind($fp);
            $written = fwrite($fp, $json . "\n");
            fflush($fp);
            return $written !== false;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    public static function delete(string $path): bool {
        return is_file($path) && @unlink($path);
    }

    private static function decode(string $content): ?array {
        if (strncmp($content, "\xEF\xBB\xBF", 3) === 0) {
            $content = substr($content, 3);
        }
        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }
}
