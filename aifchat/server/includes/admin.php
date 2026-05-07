<?php
/**
 * AIF Chat - 管理后台模块
 * 
 * 管理员专用 API：查看统计、管理用户、系统配置
 * 只有 role = 'admin' 的用户可以访问
 */

declare(strict_types=1);

/**
 * 检查当前用户是否为管理员
 */
function requireAdmin(): int
{
    $userId = requireAuth();
    
    $stmt = db()->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user || ($user['role'] ?? '') !== 'admin') {
        throw new \RuntimeException('权限不足', 403);
    }
    
    return $userId;
}

/**
 * 获取系统概览统计
 */
function getSystemStats(): array
{
    // 用户总数
    $totalUsers = db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    
    // 今日注册
    $todayReg = db()->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    
    // 活跃用户（7天内操作过的）
    $activeUsers = db()->query(
        "SELECT COUNT(DISTINCT user_id) FROM refresh_tokens WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)"
    )->fetchColumn();
    
    // Token 使用总量
    $totalTokensUsed = db()->query('SELECT SUM(tokens_used) FROM users')->fetchColumn() ?: 0;
    
    // Token 剩余总量
    $totalTokensRemaining = db()->query('SELECT SUM(tokens_remaining) FROM users')->fetchColumn() ?: 0;
    
    return [
        'total_users' => (int) $totalUsers,
        'today_registrations' => (int) $todayReg,
        'active_users_7d' => (int) $activeUsers,
        'total_tokens_used' => (int) $totalTokensUsed,
        'total_tokens_remaining' => (int) $totalTokensRemaining,
        'server_time' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION,
    ];
}

/**
 * 获取用户列表（分页）
 */
function getUserList(int $page = 1, int $perPage = 20, string $search = ''): array
{
    $offset = ($page - 1) * $perPage;
    
    if ($search) {
        $stmt = db()->prepare(
            'SELECT id, username, nickname, email, role, status, points, tokens_used, tokens_limit, tokens_remaining, created_at 
             FROM users 
             WHERE username LIKE ? OR email LIKE ? OR nickname LIKE ?
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?'
        );
        $like = '%' . $search . '%';
        $stmt->execute([$like, $like, $like, $perPage, $offset]);
    } else {
        $stmt = db()->prepare(
            'SELECT id, username, nickname, email, role, status, points, tokens_used, tokens_limit, tokens_remaining, created_at 
             FROM users 
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->execute([$perPage, $offset]);
    }
    
    $users = $stmt->fetchAll();
    
    // 总数
    if ($search) {
        $count = db()->query("SELECT COUNT(*) FROM users WHERE username LIKE '%$search%' OR email LIKE '%$search%'")->fetchColumn();
    } else {
        $count = db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }
    
    return [
        'users' => array_map(function ($u) {
            return [
                'id' => (int) $u['id'],
                'username' => $u['username'],
                'nickname' => $u['nickname'],
                'email' => $u['email'],
                'role' => $u['role'],
                'status' => $u['status'],
                'points' => (int) ($u['points'] ?? 0),
                'tokens_used' => (int) ($u['tokens_used'] ?? 0),
                'tokens_limit' => (int) ($u['tokens_limit'] ?? 0),
                'tokens_remaining' => (int) ($u['tokens_remaining'] ?? 0),
                'created_at' => $u['created_at'],
            ];
        }, $users),
        'total' => (int) $count,
        'page' => $page,
        'per_page' => $perPage,
    ];
}

/**
 * 更新用户信息（管理员操作）
 */
function adminUpdateUser(int $targetUserId, array $updates): array
{
    $allowedFields = ['role', 'status', 'points', 'tokens_limit', 'tokens_remaining'];
    
    $sets = [];
    $params = [];
    
    foreach ($allowedFields as $field) {
        if (isset($updates[$field])) {
            $sets[] = "{$field} = ?";
            $params[] = $updates[$field];
        }
    }
    
    if (empty($sets)) {
        throw new \RuntimeException('没有需要更新的字段');
    }
    
    $params[] = $targetUserId;
    $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?';
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    
    // 返回更新后的用户
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$targetUserId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new \RuntimeException('用户不存在', 404);
    }
    
    return formatUser($user);
}

/**
 * 系统配置信息
 */
function getSystemConfig(): array
{
    return [
        'ai_api_configured' => !empty(config('AI_API_KEY', '')),
        'ai_default_model' => config('AI_DEFAULT_MODEL', 'gpt-4o-mini'),
        'smtp_configured' => !empty(config('SMTP_HOST', '')),
        'rate_limit' => (int) config('RATE_LIMIT_PER_MINUTE', 60),
        'site_name' => config('SITE_NAME', 'AIF Chat'),
        'site_url' => config('SITE_URL', ''),
    ];
}
