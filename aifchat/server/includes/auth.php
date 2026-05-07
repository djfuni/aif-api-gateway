<?php
/**
 * AIF Chat - JWT 认证模块
 * 
 * 处理 Access Token 和 Refresh Token 的签发、验证、刷新
 */

declare(strict_types=1);

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * 签发 Access Token
 */
function issueAccessToken(int $userId, string $username): string
{
    $now = time();
    $payload = [
        'iss' => 'aifchat',
        'sub' => $userId,
        'username' => $username,
        'iat' => $now,
        'exp' => $now + jwtAccessExpires(),
        'type' => 'access',
    ];
    return JWT::encode($payload, jwtSecret(), 'HS256');
}

/**
 * 签发 Refresh Token
 */
function issueRefreshToken(int $userId): string
{
    $now = time();
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', $now + jwtRefreshExpires());

    $stmt = db()->prepare(
        'INSERT INTO refresh_tokens (user_id, token, expires_at) VALUES (?, ?, ?)'
    );
    $stmt->execute([$userId, $token, $expiresAt]);

    return $token;
}

/**
 * 验证 Access Token，返回用户数据
 * 成功返回 ['user_id' => int, 'username' => string]
 * 失败抛出异常
 *
 * @throws \RuntimeException
 */
function verifyAccessToken(string $token): array
{
    try {
        $decoded = JWT::decode($token, new Key(jwtSecret(), 'HS256'));

        if (empty($decoded->sub) || ($decoded->type ?? '') !== 'access') {
            throw new \RuntimeException('无效的 Token 类型');
        }

        return [
            'user_id' => (int) $decoded->sub,
            'username' => $decoded->username ?? '',
        ];
    } catch (\Firebase\JWT\ExpiredException $e) {
        throw new \RuntimeException('Token 已过期', 401);
    } catch (\Exception $e) {
        throw new \RuntimeException('Token 验证失败: ' . $e->getMessage(), 401);
    }
}

/**
 * 从请求头获取 Bearer Token
 */
function getBearerToken(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';

    if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
        return $matches[1];
    }
    return null;
}

/**
 * 验证请求认证，返回用户 ID
 *
 * @throws \RuntimeException
 */
function requireAuth(): int
{
    $token = getBearerToken();
    if (!$token) {
        throw new \RuntimeException('缺少认证信息', 401);
    }
    $payload = verifyAccessToken($token);
    return $payload['user_id'];
}

/**
 * 使用 Refresh Token 刷新 Access Token
 *
 * @return array{access_token: string, refresh_token: string, expires_in: int}|null
 */
function refreshTokens(string $refreshToken): ?array
{
    // 查找并删除旧的 refresh token
    $stmt = db()->prepare(
        'SELECT user_id, expires_at FROM refresh_tokens WHERE token = ? AND expires_at > NOW()'
    );
    $stmt->execute([$refreshToken]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    // 删除已使用的 refresh token
    $stmt = db()->prepare('DELETE FROM refresh_tokens WHERE token = ?');
    $stmt->execute([$refreshToken]);

    // 查询用户
    $stmt = db()->prepare('SELECT id, username FROM users WHERE id = ? AND status = "active"');
    $stmt->execute([$row['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        return null;
    }

    // 签发新 Token
    $accessToken = issueAccessToken((int) $user['id'], $user['username']);
    $newRefreshToken = issueRefreshToken((int) $user['id']);

    return [
        'access_token' => $accessToken,
        'refresh_token' => $newRefreshToken,
        'expires_in' => jwtAccessExpires(),
        'token_type' => 'Bearer',
    ];
}

/**
 * 清除用户的所有 Refresh Token（登出）
 */
function revokeAllTokens(int $userId): void
{
    $stmt = db()->prepare('DELETE FROM refresh_tokens WHERE user_id = ?');
    $stmt->execute([$userId]);
}
