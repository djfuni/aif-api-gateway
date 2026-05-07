<?php
require_once __DIR__ . '/sponsor_common.php';

$outTradeNo = trim((string)($_GET['out_trade_no'] ?? ''));
$result = 'pending';
$message = '正在确认支付状态';

try {
    $verified = sponsor_gateway()->verify($_GET);
} catch (Throwable $e) {
    $verified = false;
}

if ($verified && (($_GET['trade_status'] ?? '') === 'TRADE_SUCCESS')) {
    sponsor_mark_paid_from_callback($_GET, 'return');
    $result = 'success';
    $message = '支付成功，感谢你的支持';
} elseif ($verified) {
    $order = $outTradeNo !== '' ? sponsor_refresh_order_from_gateway($outTradeNo) : null;
    if (($order['status'] ?? '') === 'paid') {
        $result = 'success';
        $message = '支付成功，感谢你的支持';
    } else {
        $result = 'processing';
        $message = '支付结果处理中，请稍后刷新查看';
    }
} else {
    $order = $outTradeNo !== '' ? sponsor_refresh_order_from_gateway($outTradeNo) : null;
    if (($order['status'] ?? '') === 'paid') {
        $result = 'success';
        $message = '支付成功，感谢你的支持';
    } else {
        $result = 'verify_failed';
        $message = '返回校验未通过，请以异步通知或订单状态为准';
    }
}

$target = 'sponsor.html?result=' . rawurlencode($result) . '&message=' . rawurlencode($message);
if ($outTradeNo !== '') $target .= '&order=' . rawurlencode($outTradeNo);
header('Location: ' . $target);
exit;
