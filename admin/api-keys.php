<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
admin_require_csrf();
$newSecret = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $action = (string)($_POST['action'] ?? '');
        if (!function_exists('ai_api_keys_all')) throw new RuntimeException('AI API 模块不可用。');
        if ($action === 'create') {
            $uid = admin_int($_POST['user_id'] ?? 0); if (!find_user_by_id($uid)) throw new RuntimeException('用户不存在。');
            $res = ai_api_create_key($uid, (string)($_POST['name'] ?? '后台创建'));
            $_SESSION['ai_admin_new_secret'] = (string)($res['secret'] ?? ''); admin_flash('ok','API Key 已创建，请立即复制页面顶部显示的完整密钥。');
        } elseif ($action === 'revoke') {
            $id = admin_int($_POST['id'] ?? 0); ai_api_revoke_key(0, $id, true); admin_flash('ok','API Key 已停用。');
        }
    } catch (Throwable $e) { admin_flash('error', $e->getMessage()); }
    admin_redirect('/admin/api-keys.php');
}
$newSecret = (string)($_SESSION['ai_admin_new_secret'] ?? ''); unset($_SESSION['ai_admin_new_secret']);
$users = users_all(); $keys = function_exists('ai_api_keys_all') ? ai_api_keys_all() : [];
usort($keys, fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')));
admin_render_header('API Key 管理', '为用户创建、查看和停用 API Key；完整密钥只在创建后显示一次。');
admin_cards([['总 Key', count($keys), '当前全部记录'], ['启用中', count(array_filter($keys, fn($k)=>($k['status']??'active')==='active')), '可停用'], ['使用过', count(array_filter($keys, fn($k)=>trim((string)($k['last_used_at']??''))!=='')), '有调用记录'], ['用户数', count($users), '可绑定创建']]);
?>
<?php if ($newSecret !== ''): ?><div class="notice warn"><strong>新 Key：</strong><code><?= h($newSecret) ?></code><br>请马上复制保存，刷新后不会再显示完整密钥。</div><?php endif; ?>
<section class="panel"><h2>创建 API Key</h2><form class="form grid3" method="post"><input type="hidden" name="csrf" value="<?= h(admin_csrf_token()) ?>"><input type="hidden" name="action" value="create"><label>用户 ID<input name="user_id" type="number" required></label><label>名称<input name="name" value="后台创建"></label><div class="actions" style="align-self:end"><button class="btn green">创建 Key</button></div></form></section>
<div class="table-wrap"><table class="table"><thead><tr><th>ID</th><th>用户</th><th>名称</th><th>前缀</th><th>状态</th><th>创建/最后使用</th><th>IP</th><th>操作</th></tr></thead><tbody>
<?php foreach (array_slice($keys,0,200) as $k): ?><tr><td><?= h($k['id']??'') ?></td><td><?= h(admin_user_name((int)($k['user_id']??0), $users)) ?><br><span class="muted">#<?= h($k['user_id']??0) ?></span></td><td><?= h($k['name']??'') ?></td><td><code><?= h($k['key_prefix']??'') ?></code></td><td><span class="badge <?= (($k['status']??'active')==='active')?'green':'red' ?>"><?= h($k['status']??'active') ?></span></td><td><?= h($k['created_at']??'') ?><br><span class="muted"><?= h($k['last_used_at']??'未使用') ?></span></td><td><?= h($k['last_ip']??'') ?></td><td><?php if (($k['status']??'active')==='active'): ?><form method="post"><input type="hidden" name="csrf" value="<?= h(admin_csrf_token()) ?>"><input type="hidden" name="action" value="revoke"><input type="hidden" name="id" value="<?= h($k['id']??0) ?>"><button class="btn red" data-confirm="确认停用该 Key？">停用</button></form><?php endif; ?></td></tr><?php endforeach; if (!$keys): ?><tr><td colspan="8" class="muted">暂无 API Key</td></tr><?php endif; ?>
</tbody></table></div>
<?php admin_render_footer(); ?>
