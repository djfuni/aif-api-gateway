<?php
declare(strict_types=1);

$config = [
    'session_name' => 'AI_ADMIN_SESSID',
    'setup_path' => '/admin/setup.php',
    'success_path' => '/admin/',
    'users' => [],
    'max_attempts' => 6,
    'lock_seconds' => 900,
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

if (empty($config['users']) || !is_array($config['users'])) {
    header('Location: ' . (string)$config['setup_path']);
    exit;
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

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['ai_admin_csrf'])) {
        $_SESSION['ai_admin_csrf'] = bin2hex(random_bytes(24));
    }
    return (string)$_SESSION['ai_admin_csrf'];
}

function safe_return(string $default = '/admin/'): string
{
    $return = (string)($_GET['return'] ?? $default);
    if ($return === '' || preg_match('/[\r\n]/', $return) || preg_match('#^https?://#i', $return)) {
        return $default;
    }
    return $return;
}

function attempt_key(): string
{
    return 'ai_admin_attempt_' . sha1((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

$error = '';
$key = attempt_key();
$attempt = $_SESSION[$key] ?? ['count' => 0, 'until' => 0];
if (!is_array($attempt)) {
    $attempt = ['count' => 0, 'until' => 0];
}

$locked = !empty($attempt['until']) && time() < (int)$attempt['until'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($locked) {
        $error = '失败次数过多，请稍后再试。';
    } elseif (!hash_equals((string)($_SESSION['ai_admin_csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
        $error = '请求已过期，请刷新后重试。';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $hash = $config['users'][$username] ?? null;

        if (is_string($hash) && password_verify($password, $hash)) {
            session_regenerate_id(true);
            $_SESSION['ai_admin_auth'] = true;
            $_SESSION['ai_admin_user'] = $username;
            $_SESSION['ai_admin_created'] = time();
            $_SESSION['ai_admin_last_seen'] = time();
            unset($_SESSION[$key]);
            header('Location: ' . safe_return((string)($config['success_path'] ?? '/admin/')));
            exit;
        }

        $attempt['count'] = (int)($attempt['count'] ?? 0) + 1;
        if ($attempt['count'] >= (int)$config['max_attempts']) {
            $attempt['until'] = time() + (int)$config['lock_seconds'];
            $error = '失败次数过多，后台已临时锁定。';
        } else {
            $error = '账号或密码错误。';
        }
        $_SESSION[$key] = $attempt;
    }
}

$created = isset($_GET['created']);
$csrf = csrf_token();

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>AI 站后台登录</title>
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px;background:radial-gradient(circle at 20% 10%,rgba(59,130,246,.18),transparent 32%),linear-gradient(135deg,#f8fafc,#eef2ff);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Microsoft YaHei",sans-serif;color:#0f172a}.card{width:min(420px,100%);padding:28px;border-radius:24px;background:rgba(255,255,255,.94);border:1px solid #e2e8f0;box-shadow:0 28px 70px rgba(15,23,42,.12)}.logo{width:54px;height:54px;display:grid;place-items:center;border-radius:18px;background:#0f172a;color:#fff;font-weight:900;margin-bottom:16px}h1{margin:0 0 8px;font-size:30px}p{margin:0 0 20px;color:#64748b;line-height:1.7}label{display:block;margin:14px 0 7px;font-weight:800}input{width:100%;height:48px;border:1px solid #cbd5e1;border-radius:14px;padding:0 14px;font-size:16px}button{width:100%;height:50px;margin-top:20px;border:0;border-radius:14px;background:#0f172a;color:white;font-weight:900;cursor:pointer}.error{padding:12px 14px;margin:0 0 16px;border-radius:14px;background:#fef2f2;color:#dc2626;line-height:1.6}.ok{padding:12px 14px;margin:0 0 16px;border-radius:14px;background:#ecfdf5;color:#047857;line-height:1.6}.tip{margin-top:16px;font-size:13px;color:#64748b}
</style>
</head>
<body>
<main class="card">
  <div class="logo">AI</div>
  <h1>后台登录</h1>
  <p>请输入管理员账号密码进入 AI 站后台。</p>

  <?php if ($created): ?><div class="ok">管理员创建成功，请登录。</div><?php endif; ?>
  <?php if ($error !== ''): ?><div class="error"><?= h($error) ?></div><?php endif; ?>

  <form method="post">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <label>管理员账号</label>
    <input name="username" autocomplete="username" required>
    <label>管理员密码</label>
    <input name="password" type="password" autocomplete="current-password" required>
    <button type="submit">进入后台</button>
  </form>
  <div class="tip">忘记密码：删除或重命名 config/admin_auth.local.php 后重新访问 /admin/setup.php。</div>
</main>
</body>
</html>
