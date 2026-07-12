<?php
/**
 * Every page in public/ requires this file first. It sets up a secure
 * session, loads the DB connection, and pulls in the helper libraries.
 */

declare(strict_types=1);

// --- Secure session cookie settings (must run BEFORE session_start) ---
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,   // JavaScript can never read the session cookie
    'samesite' => 'Lax',  // CSRF-hardening at the cookie level
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ai.php';
require_once __DIR__ . '/ocr.php';
require_once __DIR__ . '/uploads.php';

$appConfig = app_config();

if (!empty($appConfig['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}
