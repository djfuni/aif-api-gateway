<?php
/**
 * AIF Chat - 移动端 API 主入口
 * 
 * 统一入口文件，通过 ?action=xxx 分发到各功能模块
 * 所有响应格式统一为 JSON: { "ok": true/false, "msg": "...", ... }
 * 
 * 支持动作:
 *   captcha         - 获取图形验证码
 *   send_email_code - 发送邮箱验证码
 *   register        - 用户注册
 *   login           - 用户登录
 *   refresh         - 刷新 Token
 *   logout          - 退出登录
 *   me              - 获取用户信息
 *   models          - 获取模型列表
 *   chat            - AI 聊天（支持 SSE 流式）
 *   health          - 健康检查
 *   feedback        - 用户反馈
 *   purchase_tokens - Token 充值（积分兑换）
 *   admin_stats     - [管理员] 系统统计
 *   admin_users     - [管理员] 用户列表
 *   admin_update_user - [管理员] 修改用户
 */

declare(strict_types=1);

// ====================== 错误与异常处理 ======================

error_reporting(E_ALL);
ini_set('display_errors', '0');
set_error_handler(function ($severity, $message, $file, $line) {
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

// ====================== 初始化 ======================

require_once __DIR__ . '/config.php';

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/captcha.php';
require_once __DIR__ . '/includes/ai.php';
require_once __DIR__ . '/includes/middleware.php';
require_once __DIR__ . '/includes/admin.php';

// ====================== 工具函数 ======================

/**
 * 输出统一 JSON 响应并终止
 */
function jsonResponse(array $data, int $statusCode = 200): never
{
    http_response_code($statusCode);
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * 输出成功响应
 */
function success(array $extra = []): never
{
    jsonResponse(array_merge(['ok' => true, 'msg' => '操作成功'], $extra));
}

/**
 * 输出错误响应
 */
function error(string $message, int $statusCode = 400): never
{
    jsonResponse(['ok' => false, 'msg' => $message], $statusCode);
}

/**
 * 获取 JSON 请求体
 */
function jsonBody(): array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        return [];
    }
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error('请求数据格式异常');
    }
    return $data ?? [];
}

/**
 * 获取请求参数（优先 JSON body，其次 GET/POST）
 */
function param(string $key, mixed $default = null): mixed
{
    static $body = null;
    if ($body === null) {
        $body = jsonBody();
    }
    return $body[$key] ?? $_POST[$key] ?? $default;
}

// ====================== 中间件 ======================

applyCorsHeaders();
applySecurityHeaders();
applyRateLimit();

// ====================== 路由分发 ======================

try {
    $action = $_GET['action'] ?? '';
    if (empty($action)) {
        error('缺少 action 参数');
    }

    switch ($action) {

        // ====================== 获取验证码 ======================
        case 'captcha':
            cleanExpiredCaptcha();
            $captcha = generateCaptcha();
            success([
                'captcha_id' => $captcha['captcha_id'],
                'svg' => $captcha['svg'],
                'expires_in' => $captcha['expires_in'],
            ]);
            break;

        // ====================== 发送邮箱验证码 ======================
        case 'send_email_code':
            $email = param('email', '');
            $captchaId = param('captcha_id', '');
            $captchaCode = param('captcha_code', '');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                error('邮箱格式不正确');
            }
            if (empty($captchaId) || empty($captchaCode)) {
                error('请先完成图形验证码');
            }
            if (!verifyCaptcha($captchaId, $captchaCode)) {
                error('图形验证码错误或已过期');
            }

            // 生成 6 位随机码
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = date('Y-m-d H:i:s', time() + 600);

            // 保存到数据库
            $stmt = db()->prepare(
                'INSERT INTO email_codes (email, code, expires_at) VALUES (?, ?, ?)'
            );
            $stmt->execute([$email, $code, $expiresAt]);

            // 尝试发送邮件（失败不阻塞注册）
            try {
                sendVerificationEmail($email, $code);
            } catch (\Throwable $e) {
                // 开发模式下直接返回验证码
                $debugCode = $code;
                // 生产环境应记录日志
                error_log('邮件发送失败: ' . $e->getMessage());
            }

            success(['msg' => '验证码已发送']);
            break;

        // ====================== 用户注册 ======================
        case 'register':
            $username = trim(param('username', ''));
            $email = trim(param('email', ''));
            $password = param('password', '');
            $emailCode = param('email_code', '');
            $captchaId = param('captcha_id', '');
            $captchaCode = param('captcha_code', '');
            $deviceName = param('device_name', '');

            // 参数校验
            if (strlen($username) < 2 || strlen($username) > 50) {
                error('用户名长度需在 2-50 个字符之间');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                error('邮箱格式不正确');
            }
            if (strlen($password) < 6) {
                error('密码长度不能少于 6 位');
            }
            if (empty($emailCode)) {
                error('请填写邮箱验证码');
            }
            if (empty($captchaId) || empty($captchaCode)) {
                error('请先完成图形验证码');
            }

            // 验证图形验证码
            if (!verifyCaptcha($captchaId, $captchaCode)) {
                error('图形验证码错误或已过期');
            }

            // 验证邮箱验证码
            $stmt = db()->prepare(
                'SELECT id FROM email_codes WHERE email = ? AND code = ? AND used = 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1'
            );
            $stmt->execute([$email, $emailCode]);
            $codeRow = $stmt->fetch();

            if (!$codeRow) {
                error('邮箱验证码错误或已过期');
            }

            // 标记验证码已使用
            $stmt = db()->prepare('UPDATE email_codes SET used = 1 WHERE id = ?');
            $stmt->execute([$codeRow['id']]);

            // 检查用户名和邮箱是否已存在
            $stmt = db()->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                error('用户名或邮箱已被注册');
            }

            // 创建用户
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $tokensLimit = 1000000;

            $stmt = db()->prepare(
                'INSERT INTO users (username, nickname, email, password, tokens_limit, tokens_remaining) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$username, $username, $email, $hashedPassword, $tokensLimit, $tokensLimit]);
            $userId = (int) db()->lastInsertId();

            // 签发 Token
            $accessToken = issueAccessToken($userId, $username);
            $refreshToken = issueRefreshToken($userId);

            // 获取用户信息
            $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            success([
                'data' => [
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'token_type' => 'Bearer',
                    'expires_in' => jwtAccessExpires(),
                    'user' => formatUser($user),
                ],
            ]);
            break;

        // ====================== 用户登录 ======================
        case 'login':
            $account = trim(param('username', ''));
            $password = param('password', '');
            $deviceName = param('device_name', '');

            if (empty($account) || empty($password)) {
                error('请填写账号和密码');
            }

            // 支持用户名或邮箱登录
            $stmt = db()->prepare(
                'SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1'
            );
            $stmt->execute([$account, $account]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                error('账号或密码错误', 401);
            }

            if (($user['status'] ?? 'active') !== 'active') {
                error('账号已被禁用', 403);
            }

            // 签发 Token
            $accessToken = issueAccessToken((int) $user['id'], $user['username']);
            $refreshToken = issueRefreshToken((int) $user['id']);

            success([
                'data' => [
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'token_type' => 'Bearer',
                    'expires_in' => jwtAccessExpires(),
                    'user' => formatUser($user),
                ],
            ]);
            break;

        // ====================== 刷新 Token ======================
        case 'refresh':
            $refreshToken = param('refresh_token', '');
            if (empty($refreshToken)) {
                error('缺少 refresh_token');
            }

            $result = refreshTokens($refreshToken);
            if (!$result) {
                error('Refresh Token 无效或已过期，请重新登录', 401);
            }

            success(['data' => $result]);
            break;

        // ====================== 退出登录 ======================
        case 'logout':
            $userId = requireAuth();
            revokeAllTokens($userId);
            success(['msg' => '已退出登录']);
            break;

        // ====================== 获取用户信息 ======================
        case 'me':
            $userId = requireAuth();

            $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user) {
                error('用户不存在', 404);
            }

            success([
                'user' => formatUser($user),
                'default_model' => 'lite',
            ]);
            break;

        // ====================== 获取可用模型 ======================
        case 'models':
            $userId = requireAuth();
            $modelData = getModelList();

            success([
                'default_model' => $modelData['default_model'],
                'models' => $modelData['models'],
            ]);
            break;

        // ====================== AI 聊天 ======================
        case 'chat':
            $userId = requireAuth();
            $model = param('model', 'lite');
            $messages = param('messages', []);
            $stream = (bool) param('stream', false);
            $deepThinking = (bool) param('deep_thinking', false);

            if (empty($messages)) {
                error('缺少 messages 参数');
            }

            // 扣除 Token（简化：每轮对话固定消耗 100 tokens）
            $stmt = db()->prepare(
                'UPDATE users SET tokens_used = tokens_used + 100, tokens_remaining = tokens_remaining - 100 WHERE id = ? AND tokens_remaining >= 100'
            );
            $stmt->execute([$userId]);

            if ($stmt->rowCount() === 0) {
                // 检查是不是 Token 不足
                $stmt = db()->prepare('SELECT tokens_remaining FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                if ($user && ($user['tokens_remaining'] ?? 0) < 100) {
                    error('Token 余额不足，请访问网站充值', 403);
                }
            }

            // 提取 system prompt（头部 system 消息）
            $systemPrompt = null;
            $cleanMessages = [];
            foreach ($messages as $msg) {
                if (($msg['role'] ?? '') === 'system') {
                    $systemPrompt = ($systemPrompt ?? '') . ($msg['content'] ?? '');
                } else {
                    $cleanMessages[] = $msg;
                }
            }

            try {
                if ($stream) {
                    // 流式输出
                    header('Content-Type: text/event-stream');
                    header('Cache-Control: no-cache');
                    header('Connection: keep-alive');
                    header('X-Accel-Buffering: no');

                    // 先发一条"开始"事件让客户端确认是流式
                    echo "data: {\"choices\":[{\"delta\":{\"role\":\"assistant\"}}]}\n\n";
                    ob_flush();
                    flush();

                    handleChatStream($cleanMessages, $model, $deepThinking, $systemPrompt);
                    exit;
                } else {
                    // 非流式输出
                    $result = handleChat($cleanMessages, $model, $deepThinking, $systemPrompt);
                    success([
                        'message' => $result['message'],
                        'model' => $result['model'],
                        'usage' => $result['usage'],
                    ]);
                }
            } catch (\Throwable $e) {
                error('AI 服务异常: ' . $e->getMessage());
            }
            break;

        // ====================== 健康检查 ======================
        case 'health':
            $dbOk = false;
            try {
                db()->query('SELECT 1');
                $dbOk = true;
            } catch (\Throwable $e) {}
            jsonResponse([
                'ok' => true,
                'data' => [
                    'status' => 'running',
                    'time' => date('Y-m-d H:i:s'),
                    'php_version' => PHP_VERSION,
                    'database' => $dbOk ? 'connected' : 'disconnected',
                    'ai_configured' => !empty(config('AI_API_KEY', '')),
                    'smtp_configured' => !empty(config('SMTP_HOST', '')),
                ],
            ]);
            break;

        // ====================== 用户反馈 ======================
        case 'feedback':
            $userId = 0;
            try { $userId = requireAuth(); } catch (\Throwable $e) {}
            $content = trim(param('content', ''));
            $contact = trim(param('contact', ''));
            $category = param('category', 'other');

            if (empty($content)) {
                error('请填写反馈内容');
            }
            if (!in_array($category, ['bug', 'feature', 'other'])) {
                $category = 'other';
            }

            $stmt = db()->prepare(
                'INSERT INTO feedbacks (user_id, content, contact, category) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$userId ?: null, $content, $contact, $category]);

            success(['msg' => '感谢你的反馈！']);
            break;

        // ====================== Token 充值（积分兑换） ======================
        case 'purchase_tokens':
            $userId = requireAuth();
            $amount = (int) param('amount', 0);

            if ($amount < 1000 || $amount > 1000000) {
                error('兑换数量需要在 1,000 ~ 1,000,000 之间');
            }

            // 获取用户信息
            $stmt = db()->prepare('SELECT points FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user) {
                error('用户不存在', 404);
            }

            // 100 tokens = 1 point
            $costPoints = (int) ceil($amount / 100);
            $points = (int) ($user['points'] ?? 0);

            if ($points < $costPoints) {
                error("积分不足，需要 {$costPoints} 积分（当前 {$points} 积分）");
            }

            db()->beginTransaction();
            try {
                $stmt = db()->prepare('UPDATE users SET points = points - ?, tokens_limit = tokens_limit + ?, tokens_remaining = tokens_remaining + ? WHERE id = ?');
                $stmt->execute([$costPoints, $amount, $amount, $userId]);

                $stmt = db()->prepare(
                    'INSERT INTO token_purchases (user_id, amount, cost_points, method) VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$userId, $amount, $costPoints, 'points']);

                db()->commit();
            } catch (\Throwable $e) {
                db()->rollBack();
                error('兑换失败，请稍后再试');
            }

            // 返回更新后的用户信息
            $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $updatedUser = $stmt->fetch();

            success([
                'msg' => "兑换成功！消耗 {$costPoints} 积分，获得 {$amount} Token",
                'user' => formatUser($updatedUser),
            ]);
            break;

        // ====================== [管理员] 系统统计 ======================
        case 'admin_stats':
            requireAdmin();
            $stats = getSystemStats();
            success(['data' => $stats]);
            break;

        // ====================== [管理员] 用户列表 ======================
        case 'admin_users':
            requireAdmin();
            $page = max(1, (int) param('page', 1));
            $perPage = min(100, max(10, (int) param('per_page', 20)));
            $search = trim(param('search', ''));
            $result = getUserList($page, $perPage, $search);
            success(['data' => $result]);
            break;

        // ====================== [管理员] 修改用户 ======================
        case 'admin_update_user':
            requireAdmin();
            $targetUserId = (int) param('user_id', 0);
            if ($targetUserId <= 0) {
                error('缺少 user_id');
            }
            $updates = param('updates', []);
            if (!is_array($updates) || empty($updates)) {
                error('缺少更新字段');
            }
            $updatedUser = adminUpdateUser($targetUserId, $updates);
            success(['data' => ['user' => $updatedUser]]);
            break;

        // ====================== 未知动作 ======================
        default:
            error("未知动作: {$action}", 404);
    }
} catch (\RuntimeException $e) {
    $code = $e->getCode() ?: 400;
    $action = $_GET['action'] ?? 'unknown';
    logRequest($action, 0, 'error', $e->getMessage());
    error($e->getMessage(), $code);
} catch (\Throwable $e) {
    logError('API 错误', ['action' => $_GET['action'] ?? '', 'message' => $e->getMessage()]);
    error('服务器内部错误', 500);
}

// ====================== 辅助函数 ======================

/**
 * 格式化用户数据为移动端需要的格式
 */
function formatUser(array $user): array
{
    return [
        'id' => (int) $user['id'],
        'username' => $user['username'],
        'nickname' => $user['nickname'] ?: $user['username'],
        'email' => $user['email'] ?? '',
        'role' => $user['role'] ?? 'user',
        'status' => $user['status'] ?? 'active',
        'avatar_url' => $user['avatar_url'] ?? '',
        'points' => (int) ($user['points'] ?? 0),
        'tokens_used' => (int) ($user['tokens_used'] ?? 0),
        'tokens_limit' => (int) ($user['tokens_limit'] ?? 0),
        'tokens_remaining' => (int) ($user['tokens_remaining'] ?? 0),
    ];
}

/**
 * 发送邮箱验证码
 */
function sendVerificationEmail(string $email, string $code): void
{
    $smtpHost = config('SMTP_HOST');
    if (empty($smtpHost)) {
        // 没有配置 SMTP，使用 mail() 函数尝试发送
        $subject = '=?UTF-8?B?' . base64_encode(config('SITE_NAME', 'AIF Chat') . ' - 邮箱验证码') . '?=';
        $body = "您的验证码是：{$code}\n\n验证码有效期为 10 分钟。\n\n如果不是您本人操作，请忽略此邮件。";
        mail($email, $subject, $body, "Content-Type: text/plain; charset=UTF-8\r\nFrom: " . config('SMTP_FROM', 'noreply@localhost'));
        return;
    }

    // SMTP 方式发送
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = config('SMTP_USER');
    $mail->Password = config('SMTP_PASS');
    $mail->SMTPSecure = config('SMTP_PORT', '465') === '465' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int) config('SMTP_PORT', '465');

    $mail->setFrom(config('SMTP_FROM'), config('SMTP_FROM_NAME', config('SITE_NAME', 'AIF Chat')));
    $mail->addAddress($email);
    $mail->CharSet = 'UTF-8';
    $mail->Subject = config('SITE_NAME', 'AIF Chat') . ' - 邮箱验证码';
    $mail->Body = "您的验证码是：{$code}\n\n验证码有效期为 10 分钟。\n\n如果不是您本人操作，请忽略此邮件。";

    $mail->send();
}
