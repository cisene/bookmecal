<?php
/**
 * src/bootstrap.php
 * Shared bootstrap for every entry point: class loading, timezone and small helpers.
 */

require_once __DIR__ . '/classes/JsonStore.php';
require_once __DIR__ . '/classes/ResourceProvider.php';
require_once __DIR__ . '/classes/Localization.php';
require_once __DIR__ . '/classes/View.php';

ResourceProvider::initTimezone();

/**
 * HTML-escapes a value for output in templates.
 */
function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Returns a trimmed string from a request array, or $default when missing or not a string (e.g. ?x[]=1).
 */
function request_string(array $source, string $key, string $default = ''): string {
    return (isset($source[$key]) && is_string($source[$key])) ? trim($source[$key]) : $default;
}

/**
 * Returns a request value as an array, or [] when missing or not an array.
 */
function request_array(array $source, string $key): array {
    return (isset($source[$key]) && is_array($source[$key])) ? $source[$key] : [];
}
