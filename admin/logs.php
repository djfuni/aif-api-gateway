<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
$users=users_all(); $logs=read_store(USER_LOGS_FILE); usort($logs,fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??''))); $q=trim((string)($_GET['q']??'')); if($q!=='')$logs=array_values(array_filter($logs,fn($r)=>str_contains(strtolower(json_encode($r,JSON_UNESCAPED_UNICODE)),strtolower($q))));
admin_render_header('日志审计','查看用户行为、后台操作、积分和 API Token 调整记录。');
admin_cards([['日志总数',count(read_store(USER_LOGS_FILE)),'当前筛选 '.count($logs)],['涉及用户',count(array_unique(array_map(fn($r)=>(int)($r['user_id']??0),$logs))),'user_id'],['后台记录',count(array_filter($logs,fn($r)=>str_contains((string)($r['action']??''),'[后台]'))),'admin'],['最新时间',(string)($logs[0]['created_at']??'无'),'recent']]);
?>
<div class="module-head"><form class="inline-form" method="get"><input name="q" value="<?= h($q) ?>" placeholder="搜索日志"><button class="btn blue">搜索</button><a class="btn secondary" href="/admin/logs.php">重置</a></form></div>
<div class="table-wrap"><table class="table"><thead><tr><th>时间</th><th>用户</th><th>操作</th><th>详情</th><th>IP</th></tr></thead><tbody><?php foreach(array_slice($logs,0,300) as $r): ?><tr><td><?= h($r['created_at']??'') ?></td><td><?= h(admin_user_name((int)($r['user_id']??0),$users)) ?> #<?= h($r['user_id']??0) ?></td><td><?= h($r['action']??'') ?></td><td><?= h($r['detail']??'') ?></td><td><?= h($r['ip']??'') ?></td></tr><?php endforeach; if(!$logs): ?><tr><td colspan="5" class="muted">暂无日志</td></tr><?php endif; ?></tbody></table></div>
<?php admin_render_footer(); ?>
