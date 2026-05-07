<?php
declare(strict_types=1);

return [
    'session_name' => 'AI_ADMIN_SESSID',
    'login_path' => '/admin/login.php',
    'setup_path' => '/admin/setup.php',
    'success_path' => '/admin/',
    'idle_timeout' => 1800,
    'absolute_timeout' => 43200,
    'max_attempts' => 6,
    'lock_seconds' => 900,
    'users' => [
        // 'admin' => 'password_hash_here',
    ],
];
