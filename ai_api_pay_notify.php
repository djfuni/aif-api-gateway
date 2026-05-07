<?php
require_once __DIR__ . '/ai_api_gateway_lib.php';

try {
    $verified = sponsor_gateway()->verify($_GET);
    if (!$verified) {
        echo 'fail';
        exit;
    }
    if (($_GET['trade_status'] ?? '') === 'TRADE_SUCCESS') {
        ai_api_mark_order_paid_from_callback($_GET, 'notify');
    }
    echo 'success';
} catch (Throwable $e) {
    echo 'fail';
}
