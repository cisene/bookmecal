<?php
/**
 * src/classes/ContactCard.php
 * Contact cards (vCard 3.0, RFC 2426, the version phones and mail apps read best) for the venue
 * and, when they allow it, the employee taking a booking. Sent as contact.vcf with the approval
 * email. Venue details live in settings.json "venue"; settings.json "vcard" chooses what is sent:
 * 'off', 'venue' or 'staff' (venue and employee).
 */

require_once __DIR__ . '/CalendarFeed.php';
require_once __DIR__ . '/Staff.php';

class ContactCard {
    public const MODES = ['off', 'venue', 'staff'];

    /** Characters of an Open Location Code (plus code). */
    private const PLUS_CODE = '/^[23456789CFGHJMPQRVWX]{2,8}\+[23456789CFGHJMPQRVWX]{0,3}$/';

    /**
     * The cards to attach for a booking (one or two vCards in one file), or null when the mode is
     * 'off' or there is nothing to share. $employee: the record of the employee taking the booking;
     * $languagesLabel: "Languages" in the customer's language.
     */
    public static function forBooking(array $settings, ?array $employee, string $languagesLabel = 'Languages'): ?string {
        $mode = in_array($settings['vcard'] ?? 'off', self::MODES, true) ? $settings['vcard'] ?? 'off' : 'off';
        if ($mode === 'off') {
            return null;
        }
        $business = trim((string)($settings['business_name'] ?? ''));
        $cards = array_filter([
            self::venue(is_array($settings['venue'] ?? null) ? $settings['venue'] : [], $business, (string)($settings['public_url'] ?? '')),
            $mode === 'staff' && $employee !== null ? self::employee($employee, $business, $languagesLabel) : null,
        ]);
        return $cards ? implode('', $cards) : null;
    }

    /**
     * The venue's card, or null without a business name or without any way to reach the venue.
     * $fallbackUrl (the booking site) is used when no website is set.
     */
    public static function venue(array $venue, string $business, string $fallbackUrl = ''): ?string {
        $text = fn (string $key) => trim((string)($venue[$key] ?? ''));
        $website = $text('website') !== '' ? $text('website') : trim($fallbackUrl);
        $geo = self::geo($venue);
        $plusCode = self::plusCode($text('plus_code'));
        $hasAddress = $text('street') !== '' || $text('city') !== '';
        if ($business === '' || ($text('phone') === '' && $text('email') === '' && $website === '' && !$hasAddress && $geo === null && $plusCode === null)) {
            return null;
        }

        $lines = ['FN:' . self::escape($business), 'ORG:' . self::escape($business), 'X-ABShowAs:COMPANY'];
        if ($text('phone') !== '') {
            $lines[] = 'TEL;TYPE=WORK,VOICE:' . self::escape($text('phone'));
        }
        if ($text('email') !== '') {
            $lines[] = 'EMAIL;TYPE=INTERNET,WORK:' . self::escape($text('email'));
        }
        if ($website !== '') {
            $lines[] = 'URL:' . self::escape($website);
        }
        if ($hasAddress) {
            // ADR: post office box; extended address; street; city; region; postal code; country
            $lines[] = 'ADR;TYPE=WORK:;;' . implode(';', array_map([self::class, 'escape'], [
                $text('street'), $text('city'), '', $text('postal_code'), $text('country'),
            ]));
        }
        $notes = [];
        if ($geo !== null) {
            $lines[] = sprintf('GEO:%s;%s', $geo[0], $geo[1]);
            $notes[] = sprintf('WGS-84: %s, %s', $geo[0], $geo[1]);
        }
        if ($plusCode !== null) {
            $notes[] = 'Plus Code: ' . $plusCode;
        }
        if ($notes) {
            $lines[] = 'NOTE:' . self::escape(implode("\n", $notes));
        }
        return self::card($lines);
    }

    /**
     * The employee's card, or null unless the employee agreed to share it (shareContact) and has a
     * name. Every profile field with a vCard counterpart is mapped:
     *   N (familyName; givenName; additionalName; honorificPrefix; honorificSuffix), FN, NICKNAME,
     *   ORG (business; businessAttributes.department), TITLE (jobTitle), ROLE (role),
     *   TEL (mobileNumber as CELL, phoneNumber as WORK), EMAIL, URL (website),
     *   PHOTO (avatar, when businessAttributes.showPhoto), X-SOCIALPROFILE (socialProfiles),
     *   CATEGORIES (qualifications), NOTE (bio and languages; vCard 3.0 has no LANG).
     * Not mapped: organisationNumber and swishPaymentNumber (no vCard property; payment details).
     */
    public static function employee(array $employee, string $business, string $languagesLabel = 'Languages'): ?string {
        $text = fn (string $key) => trim((string)($employee[$key] ?? ''));
        $list = fn (string $key) => array_values(array_filter(array_map(fn ($v) => trim((string)$v), (array)($employee[$key] ?? [])), 'strlen'));
        $attributes = is_array($employee['businessAttributes'] ?? null) ? $employee['businessAttributes'] : [];
        if (($employee['shareContact'] ?? false) !== true || Staff::name($employee) === '') {
            return null;
        }

        $lines = [
            'N:' . implode(';', array_map([self::class, 'escape'], [
                $text('familyName'), $text('givenName'), $text('additionalName'), $text('honorificPrefix'), $text('honorificSuffix'),
            ])),
            'FN:' . self::escape(Staff::name($employee)),
        ];
        if ($text('nickname') !== '') {
            $lines[] = 'NICKNAME:' . self::escape($text('nickname'));
        }
        $department = trim((string)($attributes['department'] ?? ''));
        if ($business !== '' || $department !== '') {
            $lines[] = 'ORG:' . self::escape($business) . ($department !== '' ? ';' . self::escape($department) : '');
        }
        if ($text('jobTitle') !== '') {
            $lines[] = 'TITLE:' . self::escape($text('jobTitle'));
        }
        if ($text('role') !== '') {
            $lines[] = 'ROLE:' . self::escape($text('role'));
        }
        foreach (array_filter(['CELL' => $text('mobileNumber'), 'WORK' => $text('phoneNumber')]) as $type => $number) {
            $lines[] = "TEL;TYPE={$type},VOICE:" . self::escape($number);
        }
        if ($text('email') !== '') {
            $lines[] = 'EMAIL;TYPE=INTERNET,WORK:' . self::escape($text('email'));
        }
        if (self::isWebUrl($text('website'))) {
            $lines[] = 'URL:' . self::escape($text('website'));
        }
        if (($attributes['showPhoto'] ?? false) === true && self::isWebUrl($text('avatar'))) {
            $lines[] = 'PHOTO;VALUE=URI:' . $text('avatar');
        }
        foreach ($list('socialProfiles') as $url) {
            if (self::isWebUrl($url)) {
                $network = self::socialNetwork($url);
                $lines[] = 'X-SOCIALPROFILE' . ($network !== null ? ';TYPE=' . $network : '') . ':' . self::escape($url);
            }
        }
        if ($list('qualifications')) {
            $lines[] = 'CATEGORIES:' . implode(',', array_map([self::class, 'escape'], $list('qualifications')));
        }
        $notes = array_filter([$text('bio'), $list('languages') ? $languagesLabel . ': ' . implode(', ', $list('languages')) : '']);
        if ($notes) {
            $lines[] = 'NOTE:' . self::escape(implode("\n\n", $notes));
        }
        return self::card($lines);
    }

    /** Network name for X-SOCIALPROFILE from a profile URL's host, or null for other sites. */
    private static function socialNetwork(string $url): ?string {
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^(www|m)\./', '', $host);
        return [
            'instagram.com' => 'instagram', 'facebook.com' => 'facebook', 'linkedin.com' => 'linkedin',
            'tiktok.com' => 'tiktok', 'x.com' => 'twitter', 'twitter.com' => 'twitter', 'youtube.com' => 'youtube',
            'pinterest.com' => 'pinterest', 'threads.net' => 'threads', 'bsky.app' => 'bluesky',
        ][$host] ?? null;
    }

    private static function isWebUrl(string $url): bool {
        return $url !== '' && preg_match('#^https?://[^\s]+$#i', $url) === 1 && !preg_match('/[\x00-\x1F\x7F]/', $url);
    }

    /**
     * Location for the calendar file: ['text' => one-line address, 'geo' => [lat, lon] or null],
     * or null when the venue has neither.
     */
    public static function location(array $settings): ?array {
        $venue = is_array($settings['venue'] ?? null) ? $settings['venue'] : [];
        $parts = array_filter(array_map(fn ($k) => trim((string)($venue[$k] ?? '')), ['street', 'postal_code', 'city']), 'strlen');
        $text = implode(', ', $parts);
        $geo = self::geo($venue);
        return $text === '' && $geo === null ? null : ['text' => $text, 'geo' => $geo];
    }

    /** [latitude, longitude] in WGS-84 as strings with at most 6 decimals, or null. */
    public static function geo(array $venue): ?array {
        $lat = self::coordinate($venue['latitude'] ?? null, 90);
        $lon = self::coordinate($venue['longitude'] ?? null, 180);
        return $lat !== null && $lon !== null ? [$lat, $lon] : null;
    }

    /** A decimal degree within ±$limit, normalized (dot, at most 6 decimals), or null. */
    public static function coordinate(mixed $value, int $limit): ?string {
        $value = str_replace(',', '.', trim((string)$value));
        if (!preg_match('/^-?\d{1,3}(\.\d+)?$/', $value) || abs((float)$value) > $limit) {
            return null;
        }
        return rtrim(rtrim(number_format((float)$value, 6, '.', ''), '0'), '.');
    }

    /**
     * A plus code (Open Location Code) in upper case, or null. Full codes ("9FFW83PH+X9") and short
     * codes followed by a place ("83PH+X9 Stockholm") are accepted.
     */
    public static function plusCode(string $code): ?string {
        $code = trim(preg_replace('/\s+/', ' ', $code) ?? '');
        if ($code === '') {
            return null;
        }
        [$head, $place] = array_pad(explode(' ', $code, 2), 2, '');
        $head = strtoupper($head);
        if (!preg_match(self::PLUS_CODE, $head) || mb_strlen($place) > 60 || preg_match('/[\x00-\x1F\x7F]/', $place)) {
            return null;
        }
        return trim($head . ' ' . $place);
    }

    private static function escape(string $text): string {
        return CalendarFeed::escape($text);
    }

    private static function card(array $lines): string {
        $lines = array_merge(['BEGIN:VCARD', 'VERSION:3.0', 'PRODID:-//Book Me Calendar//Contact//EN'], $lines, ['END:VCARD']);
        return implode("\r\n", array_map([CalendarFeed::class, 'fold'], $lines)) . "\r\n";
    }
}
