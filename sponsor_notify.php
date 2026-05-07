<?php
require_once __DIR__ . '/sponsor_common.php';

try {
    $verified = sponsor_gateway()->verify($_GET);
} catch (Throwable $e) {
    $verified = false;
}

if (!$verified) {
    echo 'fail';
    exit;
}

if (($_GET['trade_status'] ?? '') === 'TRADE_SUCCESS') {
    sponsor_mark_paid_from_callback($_GET, 'notify');
}

echo 'success';
