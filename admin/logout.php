<?php
declare(strict_types=1);

$config = [
    'session_name' => 'AI_ADMIN_SESSID',
    'login_path' => '/admin/login.php',
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

session_name((string)$config['session_name']);
session_start();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
}
session_destroy();

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Location: ' . (string)$config['login_path']);
exit;
