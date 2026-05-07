<?php
/**
 * AIF Chat - 服务端配置
 * 
 * 从 .env 文件加载配置，提供统一的配置接口
 */

declare(strict_types=1);
if (function_exists('opcache_reset')) { opcache_reset(); }

// 加载 Composer 自动加载
require_once __DIR__ . '/vendor/autoload.php';

// 加载 .env 文件
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

/**
 * 获取配置值
 */
function config(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? $default;
}

/**
 * 获取数据库 DSN
 */
function dbDsn(): string
{
    return sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        config('DB_HOST', '127.0.0.1'),
        config('DB_PORT', '3306'),
        config('DB_NAME', 'aifchat')
    );
}

/**
 * 获取数据库连接（单例）
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(dbDsn(), config('DB_USER'), config('DB_PASS'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

/**
 * 获取 JWT 密钥
 */
function jwtSecret(): string
{
    return config('JWT_SECRET', 'change-this-to-a-random-secret-key');
}

/**
 * Access Token 过期时间（秒）
 */
function jwtAccessExpires(): int
{
    return (int) config('JWT_ACCESS_EXPIRES', 3600);
}

/**
 * Refresh Token 过期时间（秒）
 */
function jwtRefreshExpires(): int
{
    return (int) config('JWT_REFRESH_EXPIRES', 2592000);
}
