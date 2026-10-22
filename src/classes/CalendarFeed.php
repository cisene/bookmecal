<?php
/**
 * src/classes/CalendarFeed.php
 * Read-only iCalendar (RFC 5545) feed of bookings, for subscribing from Apple, Google or
 * Outlook calendars. The feed URL carries a random token kept in data/secrets.json; without
 * a token the feed is off. Anyone with the link can read the bookings, so it can be renewed.
 */

require_once __DIR__ . '/JsonStore.php';
require_once __DIR__ . '/ResourceProvider.php';

class CalendarFeed {
    /** Bookings that ended more than this many days ago are left out of the feed. */
    public const PAST_DAYS = 30;

    /** Booking status => iCalendar STATUS. Other statuses (rejected, expired, ...) are left out. */
    private const STATUS_MAP = [
        'pending' => 'TENTATIVE',
        'approved' => 'CONFIRMED',
        'confirmed' => 'CONFIRMED',
    ];

    private const TOKEN_KEY = 'calendar_feed_token';

    private string $secretsPath;

    public function __construct(?string $secretsPath = null) {
        $this->secretsPath = $secretsPath ?? ResourceProvider::dataPath('secrets.json');
    }

    /** The current feed token, or null when the feed is off. */
    public function token(): ?string {
        $token = JsonStore::read($this->secretsPath)[self::TOKEN_KEY] ?? null;
        return is_string($token) && preg_match('/^[0-9a-f]{64}$/D', $token) ? $token : null;
    }

    /** Creates a new token (turning the feed on, or invalidating the old link). Returns it, or null on a write error. */
    public function renewToken(): ?string {
        $token = bin2hex(random_bytes(32));
        $saved = JsonStore::update($this->secretsPath, function (array $data) use ($token) {
            $data[self::TOKEN_KEY] = $token;
            return $data;
        });
        @chmod($this->secretsPath, 0640);
        return $saved ? $token : null;
    }

    /** Turns the feed off: the old link stops working. */
    public function disable(): bool {
        return JsonStore::update($this->secretsPath, function (array $data) {
            unset($data[self::TOKEN_KEY]);
            return $data;
        });
    }

    /** Constant-time check of a token from a request. Always false while the feed is off. */
    public function accepts(string $token): bool {
        $current = $this->token();
        return $current !== null && hash_equals($current, $token);
    }

    /**
     * The feed as an iCalendar document (CRLF line endings).
     * $bookings: records as stored by BookingRepository; $serviceNames: service ID => display name;
     * $employeeNames: employee ID => name, added to the summary so each employee's bookings can be told apart.
     */
    public static function render(array $bookings, array $serviceNames, string $calendarName, DateTimeZone $timezone, ?DateTimeInterface $now = null, array $employeeNames = []): string {
        $now = DateTimeImmutable::createFromInterface($now ?? new DateTimeImmutable('now', $timezone));
        $cutoff = $now->modify('-' . self::PAST_DAYS . ' days');

        $lines = [
            'X-WR-CALNAME:' . self::escape($calendarName),
            'X-WR-TIMEZONE:' . $timezone->getName(),
            'REFRESH-INTERVAL;VALUE=DURATION:PT15M',
            'X-PUBLISHED-TTL:PT15M',
        ];

        foreach ($bookings as $booking) {
            $times = self::times($booking, $timezone);
            $status = self::STATUS_MAP[$booking['status'] ?? ''] ?? null;
            if ($status === null || $times === null || $times[1] < $cutoff) {
                continue;
            }
            $service = $serviceNames[(string)($booking['service_id'] ?? '')] ?? (string)($booking['service_id'] ?? '');
            $name = (string)($booking['customer_name'] ?? '');
            $employee = $employeeNames[(string)($booking['employee_id'] ?? '')] ?? '';
            $summary = ($status === 'TENTATIVE' ? '? ' : '') . $service . ($name !== '' ? ' – ' . $name : '') . ($employee !== '' ? ' (' . $employee . ')' : '');
            $details = array_filter([$name, (string)($booking['customer_email'] ?? ''), (string)($booking['customer_phone'] ?? '')]);
            array_push($lines, ...self::event((string)$booking['id'], $times, $now, $summary, implode("\n", $details), $status));
        }
        return self::calendar($lines);
    }

    /**
     * One booking as a calendar file for the customer ("add to my calendar"), or null if the
     * booking has no valid time. $summary is e.g. "Medical Foot Care – Salong Anna".
     * $location: ['text' => address, 'geo' => [lat, lon] or null] (ContactCard::location()).
     */
    public static function single(array $booking, string $summary, DateTimeZone $timezone, ?DateTimeInterface $now = null, ?array $location = null): ?string {
        $times = self::times($booking, $timezone);
        if ($times === null) {
            return null;
        }
        $now = DateTimeImmutable::createFromInterface($now ?? new DateTimeImmutable('now', $timezone));
        $extra = [];
        if ($location !== null && ($location['text'] ?? '') !== '') {
            $extra[] = 'LOCATION:' . self::escape((string)$location['text']);
        }
        if ($location !== null && is_array($location['geo'] ?? null)) {
            $extra[] = 'GEO:' . $location['geo'][0] . ';' . $location['geo'][1];
        }
        return self::calendar(self::event((string)$booking['id'], $times, $now, $summary, '', 'CONFIRMED', $extra));
    }

    /** Wraps content lines in a VCALENDAR and folds them. */
    private static function calendar(array $lines): string {
        $lines = array_merge(
            ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Book Me Calendar//Bookings//EN', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH'],
            $lines,
            ['END:VCALENDAR']
        );
        return implode("\r\n", array_map([self::class, 'fold'], $lines)) . "\r\n";
    }

    /** Content lines of one VEVENT; times are written in UTC, so no VTIMEZONE block is needed. */
    private static function event(string $id, array $times, DateTimeImmutable $now, string $summary, string $description, string $status, array $extra = []): array {
        $utc = new DateTimeZone('UTC');
        $lines = [
            'BEGIN:VEVENT',
            'UID:' . $id . '@bookmecal',
            'DTSTAMP:' . $now->setTimezone($utc)->format('Ymd\THis\Z'),
            'DTSTART:' . $times[0]->setTimezone($utc)->format('Ymd\THis\Z'),
            'DTEND:' . $times[1]->setTimezone($utc)->format('Ymd\THis\Z'),
            'SUMMARY:' . self::escape($summary),
        ];
        if ($description !== '') {
            $lines[] = 'DESCRIPTION:' . self::escape($description);
        }
        array_push($lines, ...$extra);
        array_push($lines, 'STATUS:' . $status, 'TRANSP:OPAQUE', 'END:VEVENT');
        return $lines;
    }

    /**
     * [start, end] of a booking, or null for a malformed record. A missing or impossible end
     * becomes one hour after the start.
     */
    private static function times(array $booking, DateTimeZone $timezone): ?array {
        $date = (string)($booking['date'] ?? '');
        $start = self::parse($date, (string)($booking['time'] ?? ''), $timezone);
        if ($start === null || !preg_match('/^book_[0-9a-f]+$/D', (string)($booking['id'] ?? ''))) {
            return null;
        }
        $end = self::parse($date, (string)($booking['end_time'] ?? ''), $timezone);
        return [$start, $end !== null && $end > $start ? $end : $start->modify('+1 hour')];
    }

    /** Date plus time in $timezone, or null unless both are exact and real (no 2026-13-01 rolling over). */
    private static function parse(string $date, string $time, DateTimeZone $timezone): ?DateTimeImmutable {
        $value = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $time, $timezone);
        return $value && $value->format('Y-m-d H:i') === $date . ' ' . $time ? $value : null;
    }

    /** Escapes a TEXT value: backslash, semicolon, comma and newlines; other control characters are dropped. */
    public static function escape(string $text): string {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/u', '', $text) ?? '';
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], $text);
    }

    /** Folds a content line at 75 octets without splitting a UTF-8 character. */
    public static function fold(string $line): string {
        $out = '';
        $limit = 75;
        while (strlen($line) > $limit) {
            $cut = $limit;
            // Step back off UTF-8 continuation bytes (10xxxxxx)
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }
            $out .= substr($line, 0, $cut) . "\r\n ";
            $line = substr($line, $cut);
            $limit = 74; // continuation lines start with a space
        }
        return $out . $line;
    }
}
