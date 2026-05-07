<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once dirname(__DIR__) . '/db.php';
if (is_file(dirname(__DIR__) . '/ai_api_gateway_lib.php')) {
    require_once dirname(__DIR__) . '/ai_api_gateway_lib.php';
}

function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function admin_username(): string { return (string)($_SESSION['ai_admin_user'] ?? 'admin'); }

function admin_csrf_token(): string {
    if (empty($_SESSION['ai_admin_panel_csrf'])) $_SESSION['ai_admin_panel_csrf'] = bin2hex(random_bytes(24));
    return (string)$_SESSION['ai_admin_panel_csrf'];
}

function admin_require_csrf(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;
    $token = (string)($_POST['csrf'] ?? '');
    if ($token === '' || !hash_equals((string)($_SESSION['ai_admin_panel_csrf'] ?? ''), $token)) {
        admin_flash('error', '请求已过期，请刷新页面后重试。');
        admin_redirect((string)($_SERVER['REQUEST_URI'] ?? '/admin/'));
    }
}

function admin_flash(string $type = '', string $message = ''): array {
    if ($message !== '') {
        $_SESSION['ai_admin_flash'] = ['type' => $type, 'message' => $message];
        return [];
    }
    $flash = $_SESSION['ai_admin_flash'] ?? [];
    unset($_SESSION['ai_admin_flash']);
    return is_array($flash) ? $flash : [];
}

function admin_redirect(string $url): never {
    if (!headers_sent()) header('Location: ' . $url);
    exit;
}

function admin_int($value, int $default = 0): int {
    if (is_numeric($value)) return (int)$value;
    return $default;
}

function admin_now(): string { return date('Y-m-d H:i:s'); }

function admin_store_document(string $key, mixed $fallback = []): mixed {
    try { return db_read_document($key, $fallback); } catch (Throwable $e) { return $fallback; }
}
function admin_write_document(string $key, mixed $value): void { db_write_document($key, $value); }

function admin_points_settings(): array {
    return points_settings_compat();
}
function admin_save_points_settings(array $settings): void {
    admin_write_document('data/points_settings.json', $settings);
    $file = dirname(__DIR__) . '/data/points_settings.json';
    @file_put_contents($file, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function admin_announcements(): array {
    $rows = admin_store_document('config/announcements.json', []);
    return is_array($rows) ? $rows : [];
}
function admin_save_announcements(array $rows): void { admin_write_document('config/announcements.json', array_values($rows)); }

function admin_contents(): array {
    $data = admin_store_document('data/admin_contents.json', ['items' => []]);
    if (!is_array($data)) $data = ['items' => []];
    if (!isset($data['items']) || !is_array($data['items'])) $data['items'] = [];
    return $data;
}
function admin_save_contents(array $data): void { admin_write_document('data/admin_contents.json', $data); }

function admin_user_name(int $userId, ?array $users = null): string {
    $users = $users ?? users_all();
    foreach ($users as $u) if ((int)($u['id'] ?? 0) === $userId) return (string)($u['username'] ?? ('#' . $userId));
    return '#' . $userId;
}

function admin_log(int $userId, string $action, string $detail = ''): void {
    try { add_user_log($userId, '[后台] ' . $action, $detail); } catch (Throwable $e) { error_log('[admin log] ' . $e->getMessage()); }
}

function admin_cards(array $items): void {
    echo '<section class="grid">';
    foreach ($items as $item) {
        echo '<div class="stat"><small>' . h($item[0] ?? '') . '</small><strong>' . h($item[1] ?? '') . '</strong>';
        if (!empty($item[2])) echo '<em>' . h($item[2]) . '</em>';
        echo '</div>';
    }
    echo '</section>';
}

function admin_render_header(string $title, string $desc = ''): void {
    $current = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    $nav = [
        'index.php' => '首页',
        'users.php' => '用户管理',
        'api-keys.php' => '令牌管理',
        'orders.php' => '订单钱包',
        'developer-applications.php' => '开发者激励',
        'redeem-codes.php' => '兑换码',
        'packages.php' => '套餐管理',
        'models.php' => '渠道模型',
        'content.php' => '内容公告',
        'settings.php' => '系统设置',
        'logs.php' => '日志审计',
        'status.php' => '状态工具',
    ];
    $flash = admin_flash();
    $user = admin_username();
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= h($title) ?> - NewAPI M3 后台</title>
<link rel="stylesheet" href="/admin/assets/admin-panel.css">
</head>
<body>
<div class="layout">
  <aside class="side">
    <div class="brand"><div class="logo">M3</div><div><strong>NewAPI M3 后台</strong><small>Channel · Token · User</small></div></div>
    <nav class="nav">
      <?php foreach ($nav as $file => $label): ?>
        <a class="<?= $file === $current ? 'active' : '' ?>" href="/admin/<?= h($file) ?>"><?= h($label) ?></a>
      <?php endforeach; ?>
    </nav>
  </aside>
  <main class="main">
    <div class="top">
      <div><h1><?= h($title) ?></h1><p><?= h($desc) ?> 当前后台用户：<?= h($user) ?></p></div>
      <div class="actions"><a class="btn secondary" href="/">打开首页</a><a class="btn secondary" href="/admin/index.php">控制台</a><a class="btn red" href="/admin/logout.php">退出登录</a></div>
    </div>
    <?php if (!empty($flash['message'])): ?><div class="flash <?= h($flash['type'] ?? 'ok') ?>"><?= h($flash['message']) ?></div><?php endif; ?>
    <?php
}

function admin_render_footer(): void { ?>
  </main>
</div>
<script>
document.addEventListener('click', function(e){
  const el = e.target.closest('[data-confirm]');
  if (el && !confirm(el.getAttribute('data-confirm') || '确认执行？')) e.preventDefault();
});
</script>
</body>
</html>
<?php }
