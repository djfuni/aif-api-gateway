<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
admin_require_csrf();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $settings=admin_points_settings();
        $settings['enabled']=!empty($_POST['enabled']);
        $settings['rewards']['register']=max(0,admin_int($_POST['reward_register']??0));
        $settings['rewards']['invite']=max(0,admin_int($_POST['reward_invite']??0));
        $settings['rewards']['daily_checkin']=max(0,admin_int($_POST['reward_daily_checkin']??0));
        $settings['costs']['default']=max(0,admin_int($_POST['cost_default']??1));
        $settings['advanced_model_multiplier']=max(1,admin_int($_POST['advanced_model_multiplier']??3));
        admin_save_points_settings($settings); admin_flash('ok','积分与奖励设置已保存。');
    } catch(Throwable $e){ admin_flash('error',$e->getMessage()); }
    admin_redirect('/admin/settings.php');
}
$settings=admin_points_settings(); $authFile=dirname(__DIR__).'/config/admin_auth.local.php'; $dbOk=false; $dbError=''; try{ db(); $dbOk=true; }catch(Throwable $e){$dbError=$e->getMessage();}
admin_render_header('系统设置','管理注册奖励、邀请奖励、积分成本，并查看后台安全配置。');
?>
<section class="split"><div class="panel"><h2>积分与注册奖励</h2><form class="form grid2" method="post"><input type="hidden" name="csrf" value="<?= h(admin_csrf_token()) ?>"><label><input type="checkbox" name="enabled" value="1" <?= !empty($settings['enabled'])?'checked':'' ?>> 启用积分系统</label><label>注册奖励<input name="reward_register" type="number" value="<?= h($settings['rewards']['register']??0) ?>"></label><label>邀请奖励<input name="reward_invite" type="number" value="<?= h($settings['rewards']['invite']??0) ?>"></label><label>签到奖励<input name="reward_daily_checkin" type="number" value="<?= h($settings['rewards']['daily_checkin']??0) ?>"></label><label>默认消耗<input name="cost_default" type="number" value="<?= h($settings['costs']['default']??1) ?>"></label><label>高级模型倍率<input name="advanced_model_multiplier" type="number" value="<?= h($settings['advanced_model_multiplier']??3) ?>"></label><div class="actions" style="grid-column:1/-1"><button class="btn green">保存设置</button></div></form></div><div class="panel"><h2>后台安全</h2><table class="table"><tr><th>管理员配置</th><td><?= is_file($authFile)?'已存在':'未初始化' ?></td></tr><tr><th>登录保护</th><td>已启用 /admin/_auth.php</td></tr><tr><th>数据库</th><td><?= $dbOk?'连接正常':h($dbError) ?></td></tr><tr><th>会话</th><td><?= session_status()===PHP_SESSION_ACTIVE?'已启动':'未启动' ?></td></tr></table><p class="muted">忘记后台密码时，可以删除 <code>config/admin_auth.local.php</code> 后重新访问 <code>/admin/setup.php</code> 初始化。</p></div></section>
<section class="panel"><h2>原始积分配置</h2><pre class="code"><?= h(json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre></section>
<?php admin_render_footer(); ?>
