<?php
/** Reusable sidebar. $activeView may be home/console/models/chat. */
$activeView = $activeView ?? 'home';
function aif_nav_active(string $view, string $activeView): string { return $view === $activeView ? ' is-active' : ''; }
?>
<aside aria-label="站点导航" class="aif-sidebar" id="siteSidebar">
  <a class="aif-brand" href="index.html#home"><span class="aif-brand-mark"><i class="fa fa-bolt"></i></span><span><strong>NewAPI M3</strong><small>NewAPI M3 Console</small></span></a>
  <nav class="aif-nav">
    <div class="aif-nav-title">工作台</div>
    <button class="aif-nav-item<?= aif_nav_active('home', $activeView) ?>" data-view="home" type="button"><i class="fa fa-tachometer"></i><span>首页</span></button>
    <button class="aif-nav-item<?= aif_nav_active('console', $activeView) ?>" data-view="console" type="button"><i class="fa fa-key"></i><span>令牌管理</span></button>
    <button class="aif-nav-item<?= aif_nav_active('models', $activeView) ?>" data-view="models" type="button"><i class="fa fa-random"></i><span>渠道管理</span></button>
    <button class="aif-nav-item<?= aif_nav_active('chat', $activeView) ?>" data-view="chat" type="button"><i class="fa fa-flask"></i><span>模型测试</span></button>
    <div class="aif-nav-title">财务</div>
    <a class="aif-nav-item" href="redeem.html"><i class="fa fa-ticket"></i><span>兑换码</span></a>
    <a class="aif-nav-item" href="account.html"><i class="fa fa-user-circle-o"></i><span>用户中心</span></a>
    <a class="aif-nav-item" href="developer-plan.html"><i class="fa fa-rocket"></i><span>激励计划</span></a>
  </nav>
</aside>
