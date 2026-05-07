<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
$dbOk=false;$dbError='';try{db();$dbOk=true;}catch(Throwable $e){$dbError=$e->getMessage();}
$summary=function_exists('ai_api_admin_summary')?ai_api_admin_summary():[];
admin_render_header('状态工具','检查 PHP、MySQL、关键文件、后台守卫和 API 运行状态。');
admin_cards([['PHP',PHP_VERSION,PHP_SAPI],['数据库',$dbOk?'正常':'异常',$dbOk?DB_NAME:$dbError],['用户',count(users_all()),'users_all()'],['API Key',(int)($summary['total_keys']??0),'gateway']]);
$checks=[['项目','状态','说明'],['后台守卫',is_file(__DIR__.'/_auth.php')?'正常':'缺失','/admin/_auth.php'],['自动前置',is_file(__DIR__.'/.user.ini')?'已配置':'未配置','/admin/.user.ini'],['管理员配置',is_file(dirname(__DIR__).'/config/admin_auth.local.php')?'已初始化':'未初始化','首次访问 /admin/setup.php'],['db.php',is_file(dirname(__DIR__).'/db.php')?'存在':'缺失','当前站数据库层'],['AI API 网关',function_exists('ai_api_admin_summary')?'已加载':'未加载','ai_api_gateway_lib.php'],['注册奖励函数',function_exists('points_apply_register_reward_to_user')?'已修复':'缺失','auth.php 注册调用依赖'],['data 可写',is_writable(dirname(__DIR__).'/data')?'可写':'不可写','缓存和 JSON 兼容文件']];
?>
<div class="table-wrap"><table class="table"><tbody><?php foreach($checks as $r): ?><tr><th><?= h($r[0]) ?></th><td><?= h($r[1]) ?></td><td><?= h($r[2]) ?></td></tr><?php endforeach; ?></tbody></table></div>
<section class="panel"><h2>API 汇总</h2><pre class="code"><?= h(json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre></section>
<?php admin_render_footer(); ?>
