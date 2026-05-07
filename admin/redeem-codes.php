<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
admin_require_csrf();

function admin_redeem_fmt_tokens(int $tokens): string {
    return number_format($tokens);
}

function admin_redeem_status_badge(array $row): string {
    $status = (string)($row['status'] ?? 'active');
    $expires = trim((string)($row['expires_at'] ?? ''));
    if ($expires !== '' && (strtotime($expires) ?: 0) < time()) return '<span class="badge amber">expired</span>';
    if ($status === 'active') return '<span class="badge green">active</span>';
    if ($status === 'depleted') return '<span class="badge amber">depleted</span>';
    return '<span class="badge red">disabled</span>';
}

if (isset($_GET['download_generated']) && !empty($_SESSION['ai_admin_generated_redeem_codes'])) {
    $generated = (array)$_SESSION['ai_admin_generated_redeem_codes'];
    $filename = 'redeem-codes-' . date('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "code,tokens,batch_id\n";
    foreach (($generated['codes'] ?? []) as $code) {
        echo '"' . str_replace('"', '""', (string)$code) . '",' . (int)($generated['tokens'] ?? 0) . ',"' . str_replace('"', '""', (string)($generated['batch_id'] ?? '')) . '"' . "\n";
    }
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'generate') {
            $count = max(1, min(500, admin_int($_POST['count'] ?? 1)));
            $tokens = max(1, admin_int($_POST['tokens'] ?? 0));
            if ($tokens <= 0) throw new RuntimeException('Token 数量必须大于 0。');
            $result = ai_api_create_redeem_codes($count, $tokens, [
                'prefix' => (string)($_POST['prefix'] ?? 'AIF'),
                'title' => (string)($_POST['title'] ?? 'Token 兑换码'),
                'note' => (string)($_POST['note'] ?? ''),
                'max_uses' => admin_int($_POST['max_uses'] ?? 1),
                'per_user_limit' => admin_int($_POST['per_user_limit'] ?? 1),
                'starts_at' => trim((string)($_POST['starts_at'] ?? '')),
                'expires_at' => trim((string)($_POST['expires_at'] ?? '')),
                'batch_id' => trim((string)($_POST['batch_id'] ?? '')),
                'created_by' => admin_username(),
            ]);
            $_SESSION['ai_admin_generated_redeem_codes'] = $result;
            admin_log(0, '生成兑换码', (int)$result['count'] . ' 个，每个 +' . (int)$result['tokens'] . ' Token，批次 ' . (string)$result['batch_id']);
            admin_flash('ok', '已生成 ' . (int)$result['count'] . ' 个兑换码，请立即复制或下载 CSV。');
        } elseif ($action === 'update') {
            $id = admin_int($_POST['id'] ?? 0);
            $rows = ai_api_redeem_codes_all();
            $found = false;
            foreach ($rows as &$row) {
                if ((int)($row['id'] ?? 0) !== $id) continue;
                $found = true;
                $row['title'] = mb_substr(trim((string)($_POST['title'] ?? $row['title'] ?? 'Token 兑换码')), 0, 80, 'UTF-8');
                $row['tokens'] = max(1, admin_int($_POST['tokens'] ?? ($row['tokens'] ?? 0)));
                $row['status'] = in_array((string)($_POST['status'] ?? 'active'), ['active','disabled','depleted'], true) ? (string)$_POST['status'] : 'active';
                $row['max_uses'] = max(1, admin_int($_POST['max_uses'] ?? ($row['max_uses'] ?? 1)));
                $row['per_user_limit'] = max(1, admin_int($_POST['per_user_limit'] ?? ($row['per_user_limit'] ?? 1)));
                $row['starts_at'] = trim((string)($_POST['starts_at'] ?? ''));
                $row['expires_at'] = trim((string)($_POST['expires_at'] ?? ''));
                $row['note'] = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 300, 'UTF-8');
                if ($row['expires_at'] !== '' && strtotime((string)$row['expires_at']) === false) throw new RuntimeException('过期时间格式不正确。');
                if ($row['starts_at'] !== '' && strtotime((string)$row['starts_at']) === false) throw new RuntimeException('生效时间格式不正确。');
                $row['updated_at'] = admin_now();
                break;
            }
            unset($row);
            if (!$found) throw new RuntimeException('兑换码不存在。');
            ai_api_save_redeem_codes($rows);
            admin_log(0, '编辑兑换码', '#' . $id);
            admin_flash('ok', '兑换码已保存。');
        } elseif ($action === 'set_status') {
            $id = admin_int($_POST['id'] ?? 0);
            $status = (string)($_POST['status'] ?? 'disabled');
            if (!in_array($status, ['active','disabled','depleted'], true)) $status = 'disabled';
            $rows = ai_api_redeem_codes_all();
            $found = false;
            foreach ($rows as &$row) {
                if ((int)($row['id'] ?? 0) !== $id) continue;
                $row['status'] = $status;
                $row['updated_at'] = admin_now();
                $found = true;
                break;
            }
            unset($row);
            if (!$found) throw new RuntimeException('兑换码不存在。');
            ai_api_save_redeem_codes($rows);
            admin_log(0, '更新兑换码状态', '#' . $id . ' => ' . $status);
            admin_flash('ok', '状态已更新。');
        } elseif ($action === 'delete') {
            $id = admin_int($_POST['id'] ?? 0);
            $rows = ai_api_redeem_codes_all();
            $records = ai_api_redeem_records_all();
            foreach ($records as $record) {
                if ((int)($record['code_id'] ?? 0) === $id) throw new RuntimeException('该兑换码已有兑换记录，建议禁用而不是删除。');
            }
            $before = count($rows);
            $rows = array_values(array_filter($rows, fn($row) => (int)($row['id'] ?? 0) !== $id));
            if (count($rows) === $before) throw new RuntimeException('兑换码不存在。');
            ai_api_save_redeem_codes($rows);
            admin_log(0, '删除兑换码', '#' . $id);
            admin_flash('ok', '兑换码已删除。');
        }
    } catch (Throwable $e) {
        admin_flash('error', $e->getMessage());
    }
    admin_redirect('/admin/redeem-codes.php');
}

$generated = (array)($_SESSION['ai_admin_generated_redeem_codes'] ?? []);
$codes = array_map('ai_api_normalize_redeem_code_row', ai_api_redeem_codes_all());
$records = ai_api_redeem_records_all();
$users = ai_api_admin_user_map();
$q = trim((string)($_GET['q'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
if ($q !== '') {
    $needle = mb_strtolower($q, 'UTF-8');
    $codes = array_values(array_filter($codes, function($row) use ($needle) {
        $text = mb_strtolower((string)($row['code_preview'] ?? '') . ' ' . (string)($row['title'] ?? '') . ' ' . (string)($row['batch_id'] ?? '') . ' ' . (string)($row['note'] ?? ''), 'UTF-8');
        return str_contains($text, $needle);
    }));
}
if ($statusFilter !== '') {
    $codes = array_values(array_filter($codes, fn($row) => (string)($row['status'] ?? 'active') === $statusFilter));
}
usort($codes, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
usort($records, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
$editId = admin_int($_GET['edit'] ?? 0);
$editCode = null;
foreach ($codes as $row) if ((int)($row['id'] ?? 0) === $editId) { $editCode = $row; break; }
$summary = ai_api_admin_redeem_summary();

admin_render_header('兑换码系统', '批量生成兑换码，用户在控制台输入后自动兑换为 Token 钱包余额。');
admin_cards([
    ['兑换码总数', (int)$summary['total_codes'], '可筛选、禁用、编辑'],
    ['可用兑换码', (int)$summary['active_codes'], '未使用 ' . (int)$summary['unused_codes']],
    ['兑换次数', (int)$summary['redeem_records'], '已发放 ' . admin_redeem_fmt_tokens((int)$summary['redeem_tokens_issued']) . ' Token'],
    ['过期/领完', (int)$summary['expired_codes'] . ' / ' . (int)$summary['depleted_codes'], '建议定期清理'],
]);
?>
<?php if (!empty($generated['codes'])): ?>
<section class="panel">
  <div class="module-head"><div><h2>刚生成的兑换码</h2><p>明文只在本次生成后显示，请立即复制保存；后台列表仅保留脱敏预览。</p></div><a class="btn green" href="/admin/redeem-codes.php?download_generated=1">下载 CSV</a></div>
  <textarea class="code" style="width:100%;min-height:220px;background:#0f172a;color:#e5e7eb;border:0" readonly><?= h(implode("\n", array_map('strval', $generated['codes']))) ?></textarea>
  <p class="muted">批次：<?= h($generated['batch_id'] ?? '') ?> · 每个 <?= h(admin_redeem_fmt_tokens((int)($generated['tokens'] ?? 0))) ?> Token</p>
</section>
<?php endif; ?>

<section class="panel">
  <h2>批量生成兑换码</h2>
  <form class="form grid3" method="post">
    <input type="hidden" name="csrf" value="<?= h(admin_csrf_token()) ?>">
    <input type="hidden" name="action" value="generate">
    <label>标题<input name="title" value="Token 兑换码" maxlength="80"></label>
    <label>数量<input name="count" type="number" min="1" max="500" value="10" required></label>
    <label>每个码 Token 数<input name="tokens" type="number" min="1" value="100000" required></label>
    <label>前缀<input name="prefix" value="AIF" maxlength="12" placeholder="例如 AIF"></label>
    <label>总可用次数<input name="max_uses" type="number" min="1" value="1"><span class="muted">普通一次性码填 1；活动码可填更大。</span></label>
    <label>每用户可兑次数<input name="per_user_limit" type="number" min="1" value="1"></label>
    <label>生效时间<input name="starts_at" placeholder="YYYY-mm-dd HH:ii:ss"></label>
    <label>过期时间<input name="expires_at" placeholder="YYYY-mm-dd HH:ii:ss"></label>
    <label>批次 ID<input name="batch_id" placeholder="留空自动生成"></label>
    <label style="grid-column:1/-1">备注<textarea name="note" placeholder="内部备注，用户不可见"></textarea></label>
    <div class="actions" style="grid-column:1/-1"><button class="btn green">生成兑换码</button><a class="btn secondary" href="/admin/redeem-codes.php">刷新列表</a></div>
  </form>
</section>

<?php if ($editCode): ?>
<section class="panel">
  <h2>编辑兑换码 #<?= h($editCode['id'] ?? '') ?></h2>
  <form class="form grid3" method="post">
    <input type="hidden" name="csrf" value="<?= h(admin_csrf_token()) ?>">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="id" value="<?= h($editCode['id'] ?? '') ?>">
    <label>标题<input name="title" value="<?= h($editCode['title'] ?? '') ?>"></label>
    <label>Token 数<input name="tokens" type="number" min="1" value="<?= h($editCode['tokens'] ?? 0) ?>"></label>
    <label>状态<select name="status"><option value="active" <?= (($editCode['status'] ?? '')==='active')?'selected':'' ?>>active</option><option value="disabled" <?= (($editCode['status'] ?? '')==='disabled')?'selected':'' ?>>disabled</option><option value="depleted" <?= (($editCode['status'] ?? '')==='depleted')?'selected':'' ?>>depleted</option></select></label>
    <label>总可用次数<input name="max_uses" type="number" min="1" value="<?= h($editCode['max_uses'] ?? 1) ?>"></label>
    <label>每用户可兑次数<input name="per_user_limit" type="number" min="1" value="<?= h($editCode['per_user_limit'] ?? 1) ?>"></label>
    <label>已使用次数<input disabled value="<?= h($editCode['used_count'] ?? 0) ?>"></label>
    <label>生效时间<input name="starts_at" value="<?= h($editCode['starts_at'] ?? '') ?>"></label>
    <label>过期时间<input name="expires_at" value="<?= h($editCode['expires_at'] ?? '') ?>"></label>
    <label>脱敏预览<input disabled value="<?= h($editCode['code_preview'] ?? '') ?>"></label>
    <label style="grid-column:1/-1">备注<textarea name="note"><?= h($editCode['note'] ?? '') ?></textarea></label>
    <div class="actions" style="grid-column:1/-1"><button class="btn blue">保存修改</button><a class="btn secondary" href="/admin/redeem-codes.php">取消</a></div>
  </form>
</section>
<?php endif; ?>

<div class="module-head">
  <form class="inline-form" method="get">
    <input name="q" placeholder="搜索标题/批次/备注" value="<?= h($q) ?>">
    <select name="status"><option value="">全部状态</option><option value="active" <?= $statusFilter==='active'?'selected':'' ?>>active</option><option value="disabled" <?= $statusFilter==='disabled'?'selected':'' ?>>disabled</option><option value="depleted" <?= $statusFilter==='depleted'?'selected':'' ?>>depleted</option></select>
    <button class="btn blue">筛选</button><a class="btn secondary" href="/admin/redeem-codes.php">重置</a>
  </form>
  <a class="btn secondary" href="#records">查看兑换记录</a>
</div>

<div class="table-wrap"><table class="table"><thead><tr><th>ID</th><th>兑换码</th><th>标题/批次</th><th>Token</th><th>使用</th><th>有效期</th><th>状态</th><th>操作</th></tr></thead><tbody>
<?php foreach (array_slice($codes, 0, 300) as $row): $id=(int)($row['id']??0); ?>
<tr>
  <td><?= h($id) ?></td>
  <td><strong><?= h($row['code_preview'] ?? '') ?></strong><br><span class="muted">创建：<?= h($row['created_at'] ?? '') ?></span></td>
  <td><?= h($row['title'] ?? '') ?><br><span class="muted"><?= h($row['batch_id'] ?? '') ?></span></td>
  <td><?= h(admin_redeem_fmt_tokens((int)($row['tokens'] ?? 0))) ?></td>
  <td><?= h((int)($row['used_count'] ?? 0)) ?> / <?= h((int)($row['max_uses'] ?? 1)) ?><br><span class="muted">每用户 <?= h((int)($row['per_user_limit'] ?? 1)) ?></span></td>
  <td><span class="muted">起：</span><?= h($row['starts_at'] ?: '立即') ?><br><span class="muted">止：</span><?= h($row['expires_at'] ?: '长期') ?></td>
  <td><?= admin_redeem_status_badge($row) ?></td>
  <td><div class="actions"><a class="btn secondary" href="/admin/redeem-codes.php?edit=<?= h($id) ?>">编辑</a>
    <?php if (($row['status'] ?? 'active') === 'active'): ?>
      <form class="inline-form" method="post"><input type="hidden" name="csrf" value="<?= h(admin_csrf_token()) ?>"><input type="hidden" name="action" value="set_status"><input type="hidden" name="id" value="<?= h($id) ?>"><input type="hidden" name="status" value="disabled"><button class="btn amber">禁用</button></form>
    <?php else: ?>
      <form class="inline-form" method="post"><input type="hidden" name="csrf" value="<?= h(admin_csrf_token()) ?>"><input type="hidden" name="action" value="set_status"><input type="hidden" name="id" value="<?= h($id) ?>"><input type="hidden" name="status" value="active"><button class="btn green">启用</button></form>
    <?php endif; ?>
    <?php if ((int)($row['used_count'] ?? 0) === 0): ?><form class="inline-form" method="post" data-confirm="确认删除这个未使用兑换码？"><input type="hidden" name="csrf" value="<?= h(admin_csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= h($id) ?>"><button class="btn red">删除</button></form><?php endif; ?>
  </div></td>
</tr>
<?php endforeach; if (!$codes): ?><tr><td colspan="8" class="muted">没有匹配的兑换码。</td></tr><?php endif; ?>
</tbody></table></div>

<section id="records" class="panel"><div class="module-head"><div><h2>最近兑换记录</h2><p>用于排查用户反馈和核对 Token 到账。</p></div></div>
<div class="table-wrap"><table class="table"><thead><tr><th>时间</th><th>用户</th><th>兑换码</th><th>标题</th><th>Token</th><th>IP</th></tr></thead><tbody>
<?php foreach (array_slice($records, 0, 120) as $record): $u=ai_api_admin_user_brief((int)($record['user_id']??0), $users); ?>
<tr><td><?= h($record['created_at'] ?? '') ?></td><td><strong><?= h($u['username'] ?? '') ?></strong><br><span class="muted">UID <?= h($record['user_id'] ?? '') ?></span></td><td><?= h($record['code_preview'] ?? '') ?></td><td><?= h($record['title'] ?? '') ?></td><td><?= h(admin_redeem_fmt_tokens((int)($record['tokens'] ?? 0))) ?></td><td><?= h($record['ip'] ?? '') ?></td></tr>
<?php endforeach; if (!$records): ?><tr><td colspan="6" class="muted">暂无兑换记录。</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php admin_render_footer(); ?>
