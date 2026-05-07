<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

$users = users_all();
$logs = read_store(USER_LOGS_FILE);
$summary = function_exists('ai_api_admin_summary') ? ai_api_admin_summary() : [];
$totalPoints = array_sum(array_map(fn($u) => (int)($u['points'] ?? 0), $users));
$activeUsers = count(array_filter($users, fn($u) => (($u['status'] ?? 'active') === 'active') && empty($u['is_banned'])));
$pendingOrders = (int)($summary['pending_orders'] ?? 0);
$devStats = function_exists('ai_api_developer_application_stats') ? ai_api_developer_application_stats() : ['pending' => 0];
$balance = (int)($summary['total_balance_tokens'] ?? 0);

admin_render_header('控制台', '已接入当前网站 db.php / MySQL JSON 存储层，后台入口全部是真实页面。');
admin_cards([
    ['总用户', count($users), '活跃 ' . $activeUsers],
    ['用户积分池', $totalPoints, '来自现有用户表'],
    ['API Key', (int)($summary['total_keys'] ?? 0), '启用 ' . (int)($summary['active_keys'] ?? 0)],
    ['Token 余额', $balance, '待处理订单 ' . $pendingOrders],
    ['开发者申请', (int)($devStats['pending'] ?? 0), '待审核'],
]);
?>
<div class="notice info">这版已重新应用：后台登录保护、首次初始化、按钮修复、数据库版管理、用户/API/订单/套餐/模型/内容/设置/日志页面，以及注册奖励缺失函数修复。</div>
<section class="cards">
  <article class="card"><h2>用户与额度</h2><p>管理用户状态、积分、VIP、Reasoner 每日额度和 Token 钱包。</p><div class="actions"><a class="btn blue" href="/admin/users.php">用户管理</a><a class="btn green" href="/admin/orders.php">订单钱包</a></div></article>
  <article class="card"><h2>API 商业化</h2><p>查看密钥、手动创建 Key、审核订单、配置 Token 套餐与兑换码。</p><div class="actions"><a class="btn blue" href="/admin/api-keys.php">API Key</a><a class="btn green" href="/admin/developer-applications.php">开发者激励</a><a class="btn secondary" href="/admin/redeem-codes.php">兑换码</a><a class="btn secondary" href="/admin/packages.php">套餐管理</a></div></article>
  <article class="card"><h2>模型与内容</h2><p>查看已发布模型，维护公告与站内内容。</p><div class="actions"><a class="btn blue" href="/admin/models.php">模型管理</a><a class="btn green" href="/admin/content.php">内容公告</a></div></article>
  <article class="card"><h2>系统工具</h2><p>查看数据库状态、PHP 环境、操作日志与安全配置。</p><div class="actions"><a class="btn blue" href="/admin/status.php">状态工具</a><a class="btn secondary" href="/admin/logs.php">日志审计</a></div></article>
</section>
<div class="module-head"><h2>最近操作</h2><a class="btn secondary" href="/admin/logs.php">查看全部</a></div>
<div class="table-wrap"><table class="table"><thead><tr><th>时间</th><th>用户</th><th>操作</th><th>详情</th></tr></thead><tbody>
<?php foreach (array_slice(array_reverse($logs), 0, 10) as $row): ?>
<tr><td><?= h($row['created_at'] ?? '') ?></td><td><?= h(admin_user_name((int)($row['user_id'] ?? 0), $users)) ?></td><td><?= h($row['action'] ?? '') ?></td><td><?= h($row['detail'] ?? '') ?></td></tr>
<?php endforeach; if (!$logs): ?><tr><td colspan="4" class="muted">暂无日志</td></tr><?php endif; ?>
</tbody></table></div>
<?php admin_render_footer(); ?>
