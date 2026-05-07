<?php
declare(strict_types=1);

$configPath = dirname(__DIR__) . '/config/admin_auth.local.php';
$configDir = dirname($configPath);

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_config_exists(string $path): bool
{
    if (!is_file($path)) {
        return false;
    }
    $data = require $path;
    return is_array($data) && !empty($data['users']) && is_array($data['users']);
}

if (!is_dir($configDir)) {
    @mkdir($configDir, 0755, true);
}

$alreadyReady = admin_config_exists($configPath);
$error = '';
$manualConfig = '';

if ($alreadyReady) {
    header('Location: /admin/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password2'] ?? '');

    if ($username === '' || !preg_match('/^[a-zA-Z0-9_.@-]{3,40}$/', $username)) {
        $error = '管理员账号格式不正确，只能使用字母、数字、点、下划线、@ 和横线，长度 3-40。';
    } elseif (strlen($password) < 10) {
        $error = '密码至少 10 位，建议包含大小写字母、数字和符号。';
    } elseif ($password !== $password2) {
        $error = '两次输入的密码不一致。';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $content = "<?php\n"
            . "declare(strict_types=1);\n\n"
            . "return [\n"
            . "    'session_name' => 'AI_ADMIN_SESSID',\n"
            . "    'login_path' => '/admin/login.php',\n"
            . "    'setup_path' => '/admin/setup.php',\n"
            . "    'success_path' => '/admin/',\n"
            . "    'idle_timeout' => 1800,\n"
            . "    'absolute_timeout' => 43200,\n"
            . "    'max_attempts' => 6,\n"
            . "    'lock_seconds' => 900,\n"
            . "    'users' => [\n"
            . "        " . var_export($username, true) . " => " . var_export($hash, true) . ",\n"
            . "    ],\n"
            . "];\n";

        if (@file_put_contents($configPath, $content, LOCK_EX) !== false) {
            @chmod($configPath, 0640);
            header('Location: /admin/login.php?created=1');
            exit;
        }

        $manualConfig = $content;
        $error = '服务器无法自动写入配置文件。请手动创建 config/admin_auth.local.php，并粘贴下方配置。';
    }
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>初始化 AI 站后台</title>
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px;background:linear-gradient(135deg,#f8fafc,#eef2ff);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Microsoft YaHei",sans-serif;color:#0f172a}.card{width:min(520px,100%);padding:28px;border-radius:24px;background:rgba(255,255,255,.94);border:1px solid #e2e8f0;box-shadow:0 28px 70px rgba(15,23,42,.12)}.tag{display:inline-flex;padding:6px 10px;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:12px;font-weight:800;margin-bottom:12px}h1{margin:0 0 8px;font-size:30px}p{margin:0 0 20px;color:#64748b;line-height:1.7}label{display:block;margin:14px 0 7px;font-weight:800}input{width:100%;height:48px;border:1px solid #cbd5e1;border-radius:14px;padding:0 14px;font-size:16px}button{width:100%;height:50px;margin-top:20px;border:0;border-radius:14px;background:#0f172a;color:white;font-weight:900;cursor:pointer}.error{padding:12px 14px;margin:0 0 16px;border-radius:14px;background:#fef2f2;color:#dc2626;line-height:1.6}.tip{margin-top:16px;font-size:13px;color:#64748b}.code{max-height:260px;overflow:auto;white-space:pre-wrap;background:#0f172a;color:#e2e8f0;padding:14px;border-radius:14px;font-size:12px;line-height:1.6}
</style>
</head>
<body>
<main class="card">
  <span class="tag">首次设置</span>
  <h1>初始化 AI 站后台</h1>
  <p>只需要设置一次管理员账号和密码。设置完成后，后台会自动启用登录保护。</p>

  <?php if ($error !== ''): ?>
    <div class="error"><?= h($error) ?></div>
  <?php endif; ?>

  <?php if ($manualConfig !== ''): ?>
    <pre class="code"><?= h($manualConfig) ?></pre>
  <?php else: ?>
    <form method="post">
      <label>管理员账号</label>
      <input name="username" value="admin" autocomplete="username" required>

      <label>管理员密码</label>
      <input name="password" type="password" autocomplete="new-password" required>

      <label>再次输入密码</label>
      <input name="password2" type="password" autocomplete="new-password" required>

      <button type="submit">创建后台管理员</button>
    </form>
    <div class="tip">创建成功后会自动跳转到登录页；不要使用弱密码。</div>
  <?php endif; ?>
</main>
</body>
</html>
