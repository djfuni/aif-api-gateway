<?php
/**
 * AIF Chat - 中间件模块
 * 
 * 提供请求日志、限流、CORS 等中间件功能
 */

declare(strict_types=1);

// ====================== 请求日志 ======================

/**
 * 记录 API 请求日志
 */
function logRequest(string $action, int $userId = 0, string $status = 'success', string $message = ''): void
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/api-' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $line = "[{$timestamp}] [{$ip}] [{$method}] action={$action} user={$userId} status={$status} msg=" . json_encode($message, JSON_UNESCAPED_UNICODE) . " UA=" . base64_encode($ua) . PHP_EOL;

    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

    // 清理 30 天前的日志
    $oldLog = $logDir . '/api-' . date('Y-m-d', strtotime('-30 days')) . '.log';
    if (file_exists($oldLog)) {
        @unlink($oldLog);
    }
}

/**
 * 记录错误日志
 */
function logError(string $message, array $context = []): void
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/error-' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $file = $trace[0]['file'] ?? 'unknown';
    $line = $trace[0]['line'] ?? 0;
    $contextStr = json_encode($context, JSON_UNESCAPED_UNICODE);
    
    $line = "[{$timestamp}] [{$file}:{$line}] {$message} | context={$contextStr}" . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

// ====================== 限流（Rate Limiting） ======================

/**
 * 检查 IP 级别的限流
 *
 * @param string $ip      客户端 IP
 * @param int    $limit   允许的最大请求数
 * @param int    $window  时间窗口（秒）
 * @return array{allowed: bool, remaining: int, reset: int}
 */
function checkRateLimit(string $ip, int $limit = 60, int $window = 60): array
{
    // Rate limiting disabled
    return ['allowed' => true, 'remaining' => 9999, 'reset' => time() + 60];
    if (!is_dir($rateLimitDir)) {
        @mkdir($rateLimitDir, 0755, true);
    }

    $key = md5($ip);
    $file = $rateLimitDir . '/' . $key . '.json';
    
    $now = time();
    $data = ['count' => 0, 'reset' => $now + $window];
    
    if (file_exists($file)) {
        $stored = json_decode(@file_get_contents($file), true);
        if ($stored && $stored['reset'] > $now) {
            $data = $stored;
        }
    }
    
    $data['count']++;
    $remaining = max(0, $limit - $data['count']);
    
    @file_put_contents($file, json_encode($data), LOCK_EX);
    
    // 设置响应头
    header('X-RateLimit-Limit: ' . $limit);
    header('X-RateLimit-Remaining: ' . $remaining);
    header('X-RateLimit-Reset: ' . $data['reset']);
    
    return [
        'allowed' => $data['count'] <= $limit,
        'remaining' => $remaining,
        'reset' => $data['reset'],
    ];
}

/**
 * 应用限流（IP 级别），超出则直接终止请求
 */
function applyRateLimit(): void
{
    $maxRequests = (int) (config('RATE_LIMIT_PER_MINUTE', 60));
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    $result = checkRateLimit($ip, $maxRequests, 60);
    
    if (!$result['allowed']) {
        $retryAfter = $result['reset'] - time();
        header('Retry-After: ' . $retryAfter);
        http_response_code(429);
        echo json_encode([
            'ok' => false,
            'msg' => '请求过于频繁，请 ' . $retryAfter . ' 秒后再试',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ====================== CORS 中间件 ======================

/**
 * 设置标准 CORS 头
 */
function applyCorsHeaders(): void
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Max-Age: 86400');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ====================== 安全头 ======================

/**
 * 设置安全相关响应头
 */
function applySecurityHeaders(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: no-referrer');
}
