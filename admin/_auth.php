<?php
declare(strict_types=1);

/**
 * AI site simple admin guard.
 * Put this file in /admin/_auth.php
 * /admin/.user.ini will auto-load it for admin PHP pages.
 */

if (PHP_SAPI === 'cli') {
    return;
}

$publicPages = [
    'login.php' => true,
    'logout.php' => true,
    'setup.php' => true,
];

$currentFile = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
if (isset($publicPages[$currentFile])) {
    return;
}

$config = [
    'session_name' => 'AI_ADMIN_SESSID',
    'login_path' => '/admin/login.php',
    'setup_path' => '/admin/setup.php',
    'success_path' => '/admin/',
    'idle_timeout' => 1800,
    'absolute_timeout' => 43200,
    'users' => [],
    'cookie_secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
];

foreach ([dirname(__DIR__) . '/config/admin_auth.local.php', dirname(__DIR__) . '/config/admin_auth.php'] as $file) {
    if (is_file($file)) {
        $loaded = require $file;
        if (is_array($loaded)) {
            $config = array_replace_recursive($config, $loaded);
        }
        break;
    }
}

function ai_admin_start_session(array $config): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.cookie_samesite', 'Lax');

    if (!empty($config['cookie_secure'])) {
        @ini_set('session.cookie_secure', '1');
    }

    session_name((string)$config['session_name']);
    session_start();
}

function ai_admin_safe_uri(): string
{
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/admin/');
    if ($uri === '' || preg_match('/[\r\n]/', $uri) || preg_match('#^https?://#i', $uri)) {
        return '/admin/';
    }
    return $uri;
}

function ai_admin_redirect(string $url): void
{
    if (!headers_sent()) {
        http_response_code(302);
        header('Location: ' . $url);
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
    }
    exit;
}

ai_admin_start_session($config);

if (empty($config['users']) || !is_array($config['users'])) {
    ai_admin_redirect((string)$config['setup_path']);
}

$now = time();
$loggedIn = !empty($_SESSION['ai_admin_auth']) && !empty($_SESSION['ai_admin_user']);
$lastSeen = (int)($_SESSION['ai_admin_last_seen'] ?? 0);
$created = (int)($_SESSION['ai_admin_created'] ?? 0);

if (!$loggedIn) {
    $login = (string)$config['login_path'];
    $sep = strpos($login, '?') === false ? '?' : '&';
    ai_admin_redirect($login . $sep . 'return=' . rawurlencode(ai_admin_safe_uri()));
}

if ($lastSeen > 0 && $now - $lastSeen > (int)$config['idle_timeout']) {
    $_SESSION = [];
    ai_admin_redirect((string)$config['login_path']);
}

if ($created > 0 && $now - $created > (int)$config['absolute_timeout']) {
    $_SESSION = [];
    ai_admin_redirect((string)$config['login_path']);
}

$_SESSION['ai_admin_last_seen'] = $now;

if (!headers_sent()) {
    header('Cache-Control: private, no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}
