<?php
/**
 * src/classes/SecurityHeaders.php
 * HTTP security headers for pages and API responses. Pages load scripts and styles only from
 * their own origin (no inline code), so the Content-Security-Policy can be strict.
 */

class SecurityHeaders {
    private const PAGE_CSP = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; "
        . "font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'; object-src 'none'";

    private const API_CSP = "default-src 'none'; frame-ancestors 'none'; base-uri 'none'";

    /**
     * Sends the headers for a 'page' (HTML) or 'api' (JSON) response. Later calls replace earlier ones.
     */
    public static function send(string $profile = 'page'): void {
        if (headers_sent()) {
            return;
        }
        header_remove('X-Powered-By');
        header('Content-Security-Policy: ' . ($profile === 'api' ? self::API_CSP : self::PAGE_CSP));
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }

    /** Only the server's own TLS flag counts; X-Forwarded-Proto can be forged by the client. */
    public static function isHttps(): bool {
        $https = $_SERVER['HTTPS'] ?? '';
        return $https !== '' && strtolower((string)$https) !== 'off';
    }
}
