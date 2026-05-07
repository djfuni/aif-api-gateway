<?php
require_once __DIR__ . '/ai_api_gateway_lib.php';

$outTradeNo = trim((string)($_GET['out_trade_no'] ?? ''));
$result = 'processing';
$message = '支付结果处理中，请稍后刷新 API 开放平台';

try {
    $verified = sponsor_gateway()->verify($_GET);
} catch (Throwable $e) {
    $verified = false;
}

if ($verified && (($_GET['trade_status'] ?? '') === 'TRADE_SUCCESS')) {
    try {
        ai_api_mark_order_paid_from_callback($_GET, 'return');
        $result = 'success';
        $message = '支付成功，Token 已自动到账';
    } catch (Throwable $e) {
        $result = 'processing';
        $message = $e->getMessage();
    }
} elseif ($outTradeNo !== '') {
    $order = ai_api_refresh_order_from_gateway($outTradeNo);
    if (($order['status'] ?? '') === 'paid') {
        $result = 'success';
        $message = '支付成功，Token 已自动到账';
    }
}

$target = 'ai_api_console.html?payment_result=' . rawurlencode($result) . '&message=' . rawurlencode($message);
if ($outTradeNo !== '') $target .= '&order=' . rawurlencode($outTradeNo);
header('Location: ' . $target);
exit;
