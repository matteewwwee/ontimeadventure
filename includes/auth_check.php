<?php
/**
 * ============================================================
 * ON TIME ADVENTURE — Session Auth Guard
 * includes/auth_check.php
 *
 * Include this file at the top of any page that requires
 * an authenticated user session.
 *
 * Usage:
 *   require_once __DIR__ . '/auth_check.php';        // Requires login
 *   require_once __DIR__ . '/auth_check.php';
 *   requireAdmin();                                    // Requires admin role
 * ============================================================
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Base URL for redirects
$base_url = '/ontimeadventure/';

// ── Check if user is logged in ──
if (!isset($_SESSION['id_user'])) {
    $_SESSION['flash_error'] = 'Silakan login terlebih dahulu untuk mengakses halaman ini.';
    header('Location: ' . $base_url . 'login.php');
    exit;
}

/**
 * Require admin role — call this function on admin-only pages.
 *
 * Usage:
 *   requireAdmin();
 */
function requireAdmin()
{
    global $base_url;

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        $_SESSION['flash_error'] = 'Anda tidak memiliki akses ke halaman admin.';
        header('Location: ' . $base_url . 'katalog.php');
        exit;
    }
}

// ── CSRF Protection ──
if (!function_exists('generateCsrfToken')) {
    function generateCsrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('validateCsrfToken')) {
    function validateCsrfToken($token = null) {
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? '';
        }
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

