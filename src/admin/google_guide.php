<?php
/**
 * src/admin/google_guide.php
 * Step-by-step guide for creating Google OAuth credentials and finding the Calendar ID.
 */

require_once __DIR__ . '/auth.php';

render_admin('google_guide', [
    'title' => 'admin.guide.title',
    'redirectUri' => ((($_SERVER['HTTPS'] ?? '') === 'on') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        . dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/google_guide.php') . '/setup_google.php?step=2',
]);
