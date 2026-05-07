<?php
// 开发者激励计划后台审核页：用户提交申请后，管理员在这里选择套餐并发放 Token。
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

admin_require_csrf();

function admin_status_badge(string $status): string {
    if ($status === 'approved') return 'green';
    if ($status === 'rejected') return 'red';
    return 'amber';
}

function admin_status_label(string $status): string {
    return ['pending' => '待审核', 'approved' => '已通过', 'rejected' => '未通过'][$status] ?? $status;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!function_exists('ai_api_review_developer_application')) throw new RuntimeException('开发者激励模块不可用。');
        $action = (string)($_POST['action'] ?? '');
        $id = admin_int($_POST['id'] ?? 0);
        if ($id <= 0) throw new RuntimeException('缺少申请 ID。');
        if ($action === 'approve') {
            ai_api_review_developer_application($id, 'approved', 0, (string)($_POST['package_id'] ?? ''), (string)($_POST['note'] ?? ''));
            admin_flash('ok', '申请已通过，套餐 Token 已发放。');
        } elseif ($action === 'reject') {
            ai_api_review_developer_application($id, 'rejected', 0, '', (string)($_POST['note'] ?? ''));
            admin_flash('ok', '申请已标记为未通过。');
        } else {
            throw new RuntimeException('未知操作。');
        }
    } catch (Throwable $e) {
        admin_flash('error', $e->getMessage());
    }
    admin_redirect('/admin/developer-applications.php');
}

$users = users_all();
$packages = function_exists('ai_api_packages') ? ai_api_packages(true) : [];
$rows = function_exists('ai_api_developer_applications_all') ? ai_api_developer_applications_all() : [];
$stats = function_exists('ai_api_developer_application_stats') ? ai_api_developer_application_stats() : ['total' => count($rows), 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'granted_tokens' => 0];
$statusFilter = (string)($_GET['status'] ?? 'all');
usort($rows, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
if (in_array($statusFilter, ['pending', 'approved', 'rejected'], true)) {
    $rows = array_values(array_filter($rows, fn($row) => (string)($row['status'] ?? 'pending') === $statusFilter));
}

admin_render_header('开发者激励', '审核开发者申请，通过后选择 Token 套餐并自动发放到用户钱包。');
admin_cards([
    ['申请总数', (int)($stats['total'] ?? 0), '全部提交记录'],
    ['待审核', (int)($stats['pending'] ?? 0), '需要处理'],
    ['已通过', (int)($stats['approved'] ?? 0), '已发放权益'],
    ['累计发放', number_format((int)($stats['granted_tokens'] ?? 0)), 'Token'],
]);
?>
<div class="notice info">审核流程：用户在前台提交项目资料 → 管理员评估 → 通过时选择一个现有套餐 → 系统以套餐发放记录写入钱包流水，用户余额立即增加。</div>
<div class="module-head">
  <h2>申请列表</h2>
  <div class="actions">
    <a class="btn <?= $statusFilter === 'all' ? 'blue' : 'secondary' ?>" href="/admin/developer-applications.php">全部</a>
    <a class="btn <?= $statusFilter === 'pending' ? 'amber' : 'secondary' ?>" href="/admin/developer-applications.php?status=pending">待审核</a>
    <a class="btn <?= $statusFilter === 'approved' ? 'green' : 'secondary' ?>" href="/admin/developer-applications.php?status=approved">已通过</a>
    <a class="btn <?= $statusFilter === 'rejected' ? 'red' : 'secondary' ?>" href="/admin/developer-applications.php?status=rejected">未通过</a>
  </div>
</div>
<div class="table-wrap"><table class="table"><thead><tr><th>申请人</th><th>项目与材料</th><th>使用计划</th><th>期望套餐</th><th>状态</th><th>审核操作</th></tr></thead><tbody>
<?php foreach (array_slice($rows, 0, 200) as $row):
    $status = (string)($row['status'] ?? 'pending');
    $uid = (int)($row['user_id'] ?? 0);
    $expected = (string)($row['expected_package_id'] ?? '');
?>
<tr>
  <td>
    <strong><?= h(admin_user_name($uid, $users)) ?></strong><br>
    <span class="muted">UID #<?= h($uid) ?></span><br>
    <span class="muted"><?= h($row['contact_email'] ?? '') ?></span><br>
    <span class="badge"><?= h($row['applicant_type'] ?? '个人开发者') ?></span>
  </td>
  <td>
    <strong><?= h($row['project_name'] ?? '') ?></strong><br>
    <span class="muted">阶段：<?= h($row['project_stage'] ?? '') ?></span><br>
    <?php if (!empty($row['project_url'])): ?><a class="muted" href="<?= h($row['project_url']) ?>" target="_blank" rel="noreferrer">项目链接</a><br><?php endif; ?>
    <?php if (!empty($row['proof_url'])): ?><a class="muted" href="<?= h($row['proof_url']) ?>" target="_blank" rel="noreferrer">证明材料</a><br><?php endif; ?>
    <p style="margin:8px 0 0;color:#49454F;line-height:1.65;max-width:460px"><?= nl2br(h($row['project_desc'] ?? '')) ?></p>
    <?php if (!empty($row['ai_tools'])): ?><p style="margin:8px 0 0;color:#667085;line-height:1.6">工具/模型：<?= nl2br(h($row['ai_tools'])) ?></p><?php endif; ?>
  </td>
  <td><p style="margin:0;color:#49454F;line-height:1.65;max-width:420px"><?= nl2br(h($row['usage_plan'] ?? '')) ?></p></td>
  <td>
    <code><?= h($expected ?: '未选择') ?></code><br>
    <?php if (!empty($row['granted_package_id'])): ?><span class="muted">已发：<?= h($row['granted_package_title'] ?? $row['granted_package_id']) ?><br><?= h(number_format((int)($row['granted_tokens'] ?? 0))) ?> Token</span><?php endif; ?>
  </td>
  <td>
    <span class="badge <?= h(admin_status_badge($status)) ?>"><?= h(admin_status_label($status)) ?></span><br>
    <span class="muted">提交：<?= h($row['created_at'] ?? '') ?></span><br>
    <?php if (!empty($row['reviewed_at'])): ?><span class="muted">审核：<?= h($row['reviewed_at']) ?></span><?php endif; ?>
    <?php if (!empty($row['admin_note'])): ?><p class="muted" style="margin-top:8px">备注：<?= h($row['admin_note']) ?></p><?php endif; ?>
  </td>
  <td>
    <?php if ($status === 'pending'): ?>
      <form class="form" method="post" style="min-width:260px">
        <input type="hidden" name="csrf" value="<?= h(admin_csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= h($row['id'] ?? 0) ?>">
        <label>选择发放套餐
          <select name="package_id" required>
            <option value="">请选择套餐</option>
            <?php foreach ($packages as $pkg): $pid = (string)($pkg['id'] ?? ''); ?>
              <option value="<?= h($pid) ?>" <?= $pid === $expected ? 'selected' : '' ?>><?= h(($pkg['title'] ?? $pid) . ' · ' . number_format((int)($pkg['tokens'] ?? 0)) . ' Token' . (empty($pkg['enabled']) ? ' · 已下架' : '')) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>审核备注<textarea name="note" placeholder="可选：说明发放原因或拒绝原因"></textarea></label>
        <div class="actions">
          <button class="btn green" name="action" value="approve" data-confirm="确认通过并立即发放所选套餐 Token？">通过并发放</button>
          <button class="btn red" name="action" value="reject" formnovalidate data-confirm="确认拒绝这份申请？">拒绝</button>
        </div>
      </form>
    <?php else: ?>
      <span class="muted">已处理</span>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="6" class="muted">暂无匹配申请。</td></tr><?php endif; ?>
</tbody></table></div>
<?php admin_render_footer(); ?>
