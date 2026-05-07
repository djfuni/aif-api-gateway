<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
admin_require_csrf();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $users = users_all();
    try {
        if ($action === 'create') {
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            if ($username === '' || strlen($password) < 6) throw new RuntimeException('请填写用户名，且密码不少于 6 位。');
            foreach ($users as $u) if (($u['username'] ?? '') === $username) throw new RuntimeException('用户名已存在。');
            $row = user_defaults([
                'id' => next_id($users), 'username' => $username, 'nickname' => trim((string)($_POST['nickname'] ?? $username)),
                'email' => trim((string)($_POST['email'] ?? '')), 'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => (string)($_POST['role'] ?? 'user'), 'status' => (string)($_POST['status'] ?? 'active'),
                'points' => admin_int($_POST['points'] ?? 0), 'created_at' => admin_now(), 'updated_at' => admin_now(),
            ]);
            $users[] = $row; save_users($users); admin_log((int)$row['id'], '新增用户', $username); admin_flash('ok', '用户已创建。');
        } elseif ($action === 'save') {
            $id = admin_int($_POST['id'] ?? 0); $idx = find_user_index_by_id($users, $id); if ($idx < 0) throw new RuntimeException('用户不存在。');
            $users[$idx]['nickname'] = trim((string)($_POST['nickname'] ?? $users[$idx]['nickname'] ?? ''));
            $users[$idx]['email'] = trim((string)($_POST['email'] ?? ''));
            $users[$idx]['role'] = (string)($_POST['role'] ?? 'user');
            $users[$idx]['status'] = (string)($_POST['status'] ?? 'active');
            $users[$idx]['points'] = max(0, admin_int($_POST['points'] ?? 0));
            $users[$idx]['vip_until'] = trim((string)($_POST['vip_until'] ?? ''));
            $users[$idx]['ai_reasoner_daily_bonus'] = max(0, admin_int($_POST['ai_reasoner_daily_bonus'] ?? 0));
            $users[$idx]['ai_reasoner_extra_credits'] = max(0, admin_int($_POST['ai_reasoner_extra_credits'] ?? 0));
            $users[$idx]['ban_reason'] = trim((string)($_POST['ban_reason'] ?? ''));
            $pass = (string)($_POST['new_password'] ?? ''); if ($pass !== '') { if (strlen($pass) < 6) throw new RuntimeException('新密码至少 6 位。'); $users[$idx]['password_hash'] = password_hash($pass, PASSWORD_DEFAULT); }
            $users[$idx]['updated_at'] = admin_now(); save_users($users); admin_log($id, '编辑用户', (string)($users[$idx]['username'] ?? $id)); admin_flash('ok', '用户资料已保存。');
        } elseif ($action === 'delete') {
            $id = admin_int($_POST['id'] ?? 0); $users = array_values(array_filter($users, fn($u) => (int)($u['id'] ?? 0) !== $id)); save_users($users); admin_log($id, '删除用户', '管理员删除'); admin_flash('ok', '用户已删除。');
        } elseif ($action === 'notify') {
            $id = admin_int($_POST['id'] ?? 0); $title = trim((string)($_POST['title'] ?? '后台通知')); $content = trim((string)($_POST['content'] ?? ''));
            if ($id <= 0 || $content === '') throw new RuntimeException('请填写用户和通知内容。'); create_notification($id, $title, $content, 'admin', 'account.html'); admin_log($id, '发送通知', $title); admin_flash('ok', '通知已发送。');
        } elseif ($action === 'grant_tokens' && function_exists('ai_api_update_wallet')) {
            $id = admin_int($_POST['id'] ?? 0); $tokens = admin_int($_POST['tokens'] ?? 0); $note = trim((string)($_POST['note'] ?? '后台手动调整'));
            if ($id <= 0 || $tokens === 0) throw new RuntimeException('请填写用户 ID 和 Token 数量。'); ai_api_update_wallet($id, $tokens, 'admin_adjust', ['note' => $note, 'admin' => admin_username()]); admin_log($id, 'Token 调整', ($tokens > 0 ? '+' : '') . $tokens . ' ' . $note); admin_flash('ok', 'Token 已调整。');
        }
    } catch (Throwable $e) { admin_flash('error', $e->getMessage()); }
    admin_redirect('/admin/users.php');
}

$q = trim((string)($_GET['q'] ?? ''));
$users = users_all();
if ($q !== '') $users = array_values(array_filter($users, fn($u) => str_contains(strtolower((string)($u['username'] ?? '') . ' ' . (string)($u['email'] ?? '') . ' ' . (string)($u['nickname'] ?? '')), strtolower($q))));
$editId = admin_int($_GET['edit'] ?? 0);
$editUser = $editId ? find_user_by_id($editId) : null;
admin_render_header('用户管理', '真实读取并写入当前站内用户数据，可管理积分、VIP、状态、额度与通知。');
admin_cards([
    ['用户数', count(users_all()), '当前筛选 ' . count($users)],
    ['封禁/停用', count(array_filter(users_all(), fn($u)=>($u['status'] ?? 'active') !== 'active' || !empty($u['is_banned']))), '可在编辑里恢复'],
    ['积分总量', array_sum(array_map(fn($u)=>(int)($u['points'] ?? 0), users_all())), '注册奖励已修复'],
    ['VIP 用户', count(array_filter(users_all(), fn($u)=>trim((string)($u['vip_until'] ?? '')) !== '')), '含过期记录'],
]);
?>
<div class="module-head"><form class="inline-form" method="get"><input name="q" placeholder="搜索用户名/邮箱" value="<?= h($q) ?>"><button class="btn blue">搜索</button><a class="btn secondary" href="/admin/users.php">重置</a></form><a class="btn green" href="#create">新增用户</a></div>
<?php if ($editUser): ?>
<section class="panel"><h2>编辑用户 #<?= h($editUser['id'] ?? '') ?></h2><form class="form grid3" method="post"><input type="hidden" name="csrf" value="<?= h(admin_csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= h($editUser['id'] ?? '') ?>"><label>昵称<input name="nickname" value="<?= h($editUser['nickname'] ?? '') ?>"></label><label>邮箱<input name="email" value="<?= h($editUser['email'] ?? '') ?>"></label><label>角色<select name="role"><option value="user" <?= (($editUser['role'] ?? '')==='user')?'selected':'' ?>>user</option><option value="admin" <?= (($editUser['role'] ?? '')==='admin')?'selected':'' ?>>admin</option></select></label><label>状态<select name="status"><option value="active" <?= (($editUser['status'] ?? '')==='active')?'selected':'' ?>>active</option><option value="disabled" <?= (($editUser['status'] ?? '')==='disabled')?'selected':'' ?>>disabled</option><option value="banned" <?= (($editUser['status'] ?? '')==='banned')?'selected':'' ?>>banned</option></select></label><label>积分<input name="points" type="number" value="<?= h($editUser['points'] ?? 0) ?>"></label><label>VIP 到期<input name="vip_until" value="<?= h($editUser['vip_until'] ?? '') ?>" placeholder="YYYY-mm-dd HH:ii:ss"></label><label>每日额外额度<input name="ai_reasoner_daily_bonus" type="number" value="<?= h($editUser['ai_reasoner_daily_bonus'] ?? 0) ?>"></label><label>额外次数<input name="ai_reasoner_extra_credits" type="number" value="<?= h($editUser['ai_reasoner_extra_credits'] ?? 0) ?>"></label><label>新密码<input name="new_password" placeholder="不修改请留空"></label><label style="grid-column:1/-1">限制原因<textarea name="ban_reason"><?= h($editUser['ban_reason'] ?? '') ?></textarea></label><div class="actions" style="grid-column:1/-1"><button class="btn blue">保存用户</button><a class="btn secondary" href="/admin/users.php">取消</a></div></form></section>
<?php endif; ?>
<div class="table-wrap"><table class="table"><thead><tr><th>ID</th><th>用户</th><th>邮箱</th><th>状态</th><th>积分/等级</th><th>VIP/额度</th><th>操作</th></tr></thead><tbody>
<?php foreach (array_slice($users, 0, 200) as $u): $uid=(int)($u['id']??0); ?>
<tr><td><?= h($uid) ?></td><td><strong><?= h($u['username'] ?? '') ?></strong><br><span class="muted"><?= h($u['nickname'] ?? '') ?></span></td><td><?= h($u['email'] ?? '') ?></td><td><span class="badge <?= (($u['status'] ?? 'active')==='active')?'green':'red' ?>"><?= h($u['status'] ?? 'active') ?></span></td><td><?= h($u['points'] ?? 0) ?><br><span class="muted"><?= h($u['level_title'] ?? '') ?></span></td><td><?= h($u['vip_until'] ?? '无') ?><br><span class="muted">日额外 <?= h($u['ai_reasoner_daily_bonus'] ?? 0) ?> / 额外 <?= h($u['ai_reasoner_extra_credits'] ?? 0) ?></span></td><td><div class="actions"><a class="btn secondary" href="/admin/users.php?edit=<?= h($uid) ?>">编辑</a><form class="inline-form" method="post"><input type="hidden" name="csrf" value="<?= h(admin_csrf_token()) ?>"><input type="hidden" name="action" value="grant_tokens"><input type="hidden" name="id" value="<?= h($uid) ?>"><input name="tokens" type="number" placeholder="Token" required><button class="btn green">调Token</button></form><form class="inline-form" method="post"><input type="hidden" name="csrf" value="<?= h(admin_csrf_token()) ?>"><input type="hidden" name="action" value="notify"><input type="hidden" name="id" value="<?= h($uid) ?>"><input name="content" placeholder="通知内容" required><button class="btn blue">通知</button></form></div></td></tr>
<?php endforeach; if (!$users): ?><tr><td colspan="7" class="muted">没有匹配用户</td></tr><?php endif; ?>
</tbody></table></div>
<section id="create" class="panel"><h2>新增用户</h2><form class="form grid3" method="post"><input type="hidden" name="csrf" value="<?= h(admin_csrf_token()) ?>"><input type="hidden" name="action" value="create"><label>用户名<input name="username" required></label><label>昵称<input name="nickname"></label><label>邮箱<input name="email"></label><label>初始密码<input name="password" required></label><label>角色<select name="role"><option value="user">user</option><option value="admin">admin</option></select></label><label>状态<select name="status"><option value="active">active</option><option value="disabled">disabled</option></select></label><label>初始积分<input name="points" type="number" value="0"></label><div class="actions" style="align-self:end"><button class="btn green">创建用户</button></div></form></section>
<?php admin_render_footer(); ?>
