<?php
require_once __DIR__ . '/ai_api_gateway_lib.php';

$outTradeNo = trim((string)($_GET['out_trade_no'] ?? $_POST['out_trade_no'] ?? ''));
if ($outTradeNo === '') {
    http_response_code(400);
    echo '缺少订单号';
    exit;
}

$order = ai_api_find_order_by_no($outTradeNo);
if (!$order) {
    http_response_code(404);
    echo '订单不存在';
    exit;
}

if (($order['status'] ?? '') === 'paid') {
    header('Location: ai_api_console.html?payment_result=success&message=' . rawurlencode('该订单已支付，Token 已到账') . '&order=' . rawurlencode($outTradeNo));
    exit;
}

try {
    $gateway = sponsor_gateway();
    $endpoint = $gateway->submitEndpoint();
    $paymentType = ai_api_normalize_payment_type((string)($order['payment_type'] ?? 'alipay')) ?: 'alipay';
    $payload = $gateway->buildPayPayload([
        'type' => $paymentType,
        'notify_url' => sponsor_absolute_url('ai_api_pay_notify.php'),
        'return_url' => sponsor_absolute_url('ai_api_pay_return.php'),
        'out_trade_no' => (string)($order['order_no'] ?? ''),
        'name' => 'AI API Token 包 - ' . (string)($order['title'] ?? 'Token 套餐'),
        'money' => number_format((float)($order['price'] ?? 0), 2, '.', ''),
        'param' => (string)($order['order_no'] ?? ''),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo '支付参数生成失败：' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}
?><!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>正在跳转支付</title>
  <style>
    body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f8fafc;color:#0f172a;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
    .card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 10px 30px rgba(15,23,42,.08);padding:28px;max-width:480px;width:calc(100% - 32px)}
    h1{font-size:20px;margin:0 0 10px}
    p{font-size:14px;line-height:1.7;color:#475569;margin:0 0 16px}
    button{appearance:none;border:0;border-radius:10px;background:#2563eb;color:#fff;padding:12px 18px;font-size:14px;cursor:pointer}
    .muted{font-size:12px;color:#64748b}
    footer{position:fixed;left:0;right:0;bottom:12px;text-align:center}
  </style>
</head>
<body>
  <div class="card">
    <h1>正在跳转到支付页面</h1>
    <p>订单将通过站内已配置的支付网关提交。若没有自动跳转，请点击下面按钮继续。</p>
    <form id="payForm" method="post" action="<?= htmlspecialchars($endpoint, ENT_QUOTES, 'UTF-8') ?>">
      <?php foreach ($payload as $key => $value): ?>
        <input type="hidden" name="<?= htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') ?>">
      <?php endforeach; ?>
      <button type="submit">立即前往支付</button>
    </form>
    <p class="muted">订单号：<?= htmlspecialchars($outTradeNo, ENT_QUOTES, 'UTF-8') ?></p>
  </div>
  <footer>
    <a href="https://scdn.host" class="active">
        <img src="https://img.shields.io/badge/本站由 ScdnHost CDN 提供网站加速与安全防护-green?style=flat&logo=Claris">
    </a>
</footer>
  <script>document.getElementById('payForm').submit();</script>
</body>
</html>
