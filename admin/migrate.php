<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
admin_render_header('迁移工具','手动触发数据库连接和旧 JSON 数据迁移检查。');
$ok=false;$msg='';try{db();$ok=true;$msg='数据库连接正常，db.php 已自动确保表结构和旧 JSON 数据迁移标记。';}catch(Throwable $e){$msg=$e->getMessage();}
?>
<div class="notice <?= $ok?'':'warn' ?>"><?= h($msg) ?></div>
<div class="actions"><a class="btn blue" href="/admin/status.php">查看状态</a><a class="btn secondary" href="/admin/index.php">返回控制台</a></div>
<?php admin_render_footer(); ?>
