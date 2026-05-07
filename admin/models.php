<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
$models=function_exists('ai_api_public_models')?ai_api_public_models():[]; $cfg=function_exists('ai_api_load_model_config')?ai_api_load_model_config():[];
$q=trim((string)($_GET['q']??'')); if($q!=='') $models=array_values(array_filter($models,fn($m)=>str_contains(strtolower(json_encode($m,JSON_UNESCAPED_UNICODE)),strtolower($q))));
admin_render_header('模型管理','查看当前模型注册表、供应商、倍率、是否免费和前台展示信息。');
admin_cards([['可用模型',count($models),'当前筛选'],['供应商',count($cfg['providers']??[]),'来自配置注册表'],['免费模型',count(array_filter($models,fn($m)=>!empty($m['zero_token'])||!empty($m['is_free']))),'0 Token'],['配置版本',(string)($cfg['version']??'unknown'),'config/ai_model_registry.php']]);
?>
<div class="module-head"><form class="inline-form" method="get"><input name="q" value="<?= h($q) ?>" placeholder="搜索模型/供应商"><button class="btn blue">搜索</button><a class="btn secondary" href="/admin/models.php">重置</a></form></div>
<div class="notice info">模型数据来自 <code>config/ai_model_registry.php</code> 和运行时模型聚合逻辑。此页默认安全展示，不直接在网页覆盖 PHP 配置，避免误写导致 API 不可用。</div>
<div class="table-wrap"><table class="table"><thead><tr><th>模型 Key</th><th>名称</th><th>供应商</th><th>类型/上下文</th><th>倍率/价格</th><th>标签</th><th>状态</th></tr></thead><tbody><?php foreach(array_slice($models,0,300) as $m): ?><tr><td><code><?= h($m['id']??$m['key']??'') ?></code></td><td><strong><?= h($m['label']??$m['name']??'') ?></strong><br><span class="muted"><?= h($m['description']??'') ?></span></td><td><?= h($m['provider_label']??$m['provider']??'') ?></td><td><?= h($m['type']??'chat') ?><br><span class="muted"><?= h($m['context']??'') ?></span></td><td><?= h($m['multiplier']??$m['price_label']??'') ?></td><td><?= h(implode(' / ',(array)($m['tags']??[]))) ?></td><td><?= !empty($m['zero_token'])||!empty($m['is_free'])?'<span class="badge green">免费</span>':'<span class="badge">计费</span>' ?> <?= !empty($m['thinking'])?'<span class="badge amber">思考</span>':'' ?></td></tr><?php endforeach; if(!$models): ?><tr><td colspan="7" class="muted">暂无模型</td></tr><?php endif; ?></tbody></table></div>
<?php admin_render_footer(); ?>
