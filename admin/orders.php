<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
admin_require_csrf();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!function_exists('ai_api_orders_all')) throw new RuntimeException('AI API 模块不可用。');
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'approve') { ai_api_approve_order(admin_int($_POST['id'] ?? 0), 0); admin_flash('ok','订单已确认并发放 Token。'); }
        elseif ($action === 'reject') { ai_api_reject_order(admin_int($_POST['id'] ?? 0), (string)($_POST['note'] ?? '后台取消')); admin_flash('ok','订单已取消。'); }
        elseif ($action === 'grant') { $uid=admin_int($_POST['user_id']??0); $tokens=admin_int($_POST['tokens']??0); if($uid<=0||$tokens===0) throw new RuntimeException('请填写用户和 Token 数量。'); ai_api_update_wallet($uid,$tokens,'admin_adjust',['note'=>(string)($_POST['note']??'后台调整')]); admin_log($uid,'钱包调整',($tokens>0?'+':'').$tokens); admin_flash('ok','钱包已调整。'); }
    } catch (Throwable $e) { admin_flash('error',$e->getMessage()); }
    admin_redirect('/admin/orders.php');
}
$users=users_all(); $summary=function_exists('ai_api_admin_summary')?ai_api_admin_summary():[]; $orders=function_exists('ai_api_orders_all')?ai_api_orders_all():[]; usort($orders,fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')));
$wallets=$summary['wallets']??[]; $ledger=function_exists('ai_api_ledger_all')?ai_api_ledger_all():($summary['ledger']??[]);
admin_render_header('订单钱包', '审核 Token 套餐订单、查看钱包余额并手动补发/扣减 Token。');
admin_cards([['待处理订单',(int)($summary['pending_orders']??0),'pending'],['已支付订单',(int)($summary['paid_orders']??0),'paid'],['总收入',(string)($summary['revenue']??0),'CNY'],['总余额',(int)($summary['total_balance_tokens']??0),'tokens']]);
?>
<section class="panel"><h2>手动调整 Token</h2><form class="form grid3" method="post"><input type="hidden" name="csrf" value="<?= h(admin_csrf_token()) ?>"><input type="hidden" name="action" value="grant"><label>用户 ID<input name="user_id" type="number" required></label><label>Token 数量<input name="tokens" type="number" placeholder="正数补发，负数扣减" required></label><label>备注<input name="note" value="后台手动调整"></label><div class="actions" style="align-self:end"><button class="btn green">提交调整</button></div></form></section>
<div class="module-head"><h2>订单列表</h2></div><div class="table-wrap"><table class="table"><thead><tr><th>ID/订单号</th><th>用户</th><th>套餐</th><th>金额</th><th>Token</th><th>状态</th><th>时间</th><th>操作</th></tr></thead><tbody>
<?php foreach (array_slice($orders,0,160) as $o): ?><tr><td>#<?= h($o['id']??'') ?><br><code><?= h($o['order_no']??'') ?></code></td><td><?= h(admin_user_name((int)($o['user_id']??0),$users)) ?></td><td><?= h($o['title']??$o['package_id']??'') ?></td><td><?= h($o['price']??0) ?> <?= h($o['currency']??'CNY') ?></td><td><?= h($o['tokens']??0) ?></td><td><span class="badge <?= (($o['status']??'')==='paid')?'green':((($o['status']??'')==='pending')?'amber':'red') ?>"><?= h($o['status']??'') ?></span></td><td><?= h($o['created_at']??'') ?><br><span class="muted"><?= h($o['paid_at']??'') ?></span></td><td><?php if (($o['status']??'')==='pending'): ?><div class="actions"><form method="post"><input type="hidden" name="csrf" value="<?= h(admin_csrf_token()) ?>"><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= h($o['id']??0) ?>"><button class="btn green" data-confirm="确认发放 Token？">确认</button></form><form method="post"><input type="hidden" name="csrf" value="<?= h(admin_csrf_token()) ?>"><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?= h($o['id']??0) ?>"><button class="btn red" data-confirm="确认取消订单？">取消</button></form></div><?php endif; ?></td></tr><?php endforeach; if (!$orders): ?><tr><td colspan="8" class="muted">暂无订单</td></tr><?php endif; ?>
</tbody></table></div>
<div class="module-head"><h2>钱包余额</h2></div><div class="table-wrap"><table class="table"><thead><tr><th>用户</th><th>余额</th><th>累计获得</th><th>累计消耗</th><th>更新时间</th></tr></thead><tbody><?php foreach (array_slice($wallets,0,120) as $w): ?><tr><td><?= h($w['username']??admin_user_name((int)($w['user_id']??0),$users)) ?> #<?= h($w['user_id']??0) ?></td><td><?= h($w['balance_tokens']??0) ?></td><td><?= h($w['total_granted_tokens']??0) ?></td><td><?= h($w['total_used_tokens']??0) ?></td><td><?= h($w['updated_at']??'') ?></td></tr><?php endforeach; if (!$wallets): ?><tr><td colspan="5" class="muted">暂无钱包记录</td></tr><?php endif; ?></tbody></table></div>
<?php admin_render_footer(); ?>
