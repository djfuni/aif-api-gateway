<?php
require_once __DIR__ . '/app_lib.php';
require_once __DIR__ . '/sponsor_payment_lib.php';

define('SPONSOR_ORDERS_FILE', DATA_DIR . '/sponsor_orders.json');

function sponsor_allowed_methods(): array {
    return [
        'alipay' => ['key' => 'alipay', 'label' => '支付宝', 'icon' => 'credit-card'],
        'wxpay' => ['key' => 'wxpay', 'label' => '微信支付', 'icon' => 'weixin'],
        'qqpay' => ['key' => 'qqpay', 'label' => 'QQ钱包', 'icon' => 'qq'],
    ];
}

function sponsor_method_meta(string $type): array {
    $items = sponsor_allowed_methods();
    return $items[$type] ?? ['key' => $type, 'label' => strtoupper($type), 'icon' => 'wallet'];
}

function sponsor_normalize_type(string $type): string {
    $type = trim(strtolower($type));
    return array_key_exists($type, sponsor_allowed_methods()) ? $type : '';
}

function sponsor_parse_amount(mixed $value): ?string {
    $raw = trim((string)$value);
    if ($raw === '' || !is_numeric($raw)) return null;
    $amount = round((float)$raw, 2);
    if ($amount < 1 || $amount > 9999) return null;
    return number_format($amount, 2, '.', '');
}

function sponsor_mask_name(string $name): string {
    $name = trim($name);
    if ($name === '') return '热心听友';
    $len = mb_strlen($name, 'UTF-8');
    if ($len <= 1) return $name;
    if ($len === 2) return mb_substr($name, 0, 1, 'UTF-8') . '*';
    return mb_substr($name, 0, 1, 'UTF-8') . str_repeat('*', max(1, $len - 2)) . mb_substr($name, -1, 1, 'UTF-8');
}

function sponsor_public_name(array $row): string {
    if (!empty($row['is_anonymous'])) return '匿名听友';
    $name = trim((string)($row['display_name'] ?? $row['donor_name'] ?? ''));
    return sponsor_mask_name($name);
}

function sponsor_order_defaults(array $row = []): array {
    $type = sponsor_normalize_type((string)($row['type'] ?? 'alipay')) ?: 'alipay';
    $method = sponsor_method_meta($type);
    $defaults = [
        'id' => 0,
        'out_trade_no' => '',
        'trade_no' => '',
        'user_id' => 0,
        'type' => $type,
        'type_label' => $method['label'],
        'type_icon' => $method['icon'],
        'amount' => '0.00',
        'name' => '赞助站长',
        'donor_name' => '',
        'display_name' => '',
        'message' => '',
        'is_anonymous' => false,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'paid_at' => '',
        'source' => 'sponsor',
    ];
    $merged = array_merge($defaults, $row);
    $merged['type'] = $type;
    $merged['type_label'] = $method['label'];
    $merged['type_icon'] = $method['icon'];
    $merged['amount'] = sponsor_parse_amount($merged['amount']) ?? '0.00';
    $merged['donor_name'] = mb_substr(trim((string)$merged['donor_name']), 0, 24, 'UTF-8');
    $merged['display_name'] = mb_substr(trim((string)$merged['display_name']), 0, 24, 'UTF-8');
    $merged['message'] = mb_substr(trim((string)$merged['message']), 0, 80, 'UTF-8');
    $merged['is_anonymous'] = !empty($merged['is_anonymous']);
    return $merged;
}

function sponsor_orders_all(): array {
    return array_map('sponsor_order_defaults', read_store(SPONSOR_ORDERS_FILE));
}

function sponsor_save_orders(array $rows): void {
    write_store(SPONSOR_ORDERS_FILE, array_map('sponsor_order_defaults', array_values($rows)));
}

function sponsor_find_order_index(array $rows, string $outTradeNo): int {
    foreach ($rows as $i => $row) {
        if (($row['out_trade_no'] ?? '') === $outTradeNo) return $i;
    }
    return -1;
}

function sponsor_find_order(string $outTradeNo): ?array {
    foreach (sponsor_orders_all() as $row) {
        if (($row['out_trade_no'] ?? '') === $outTradeNo) return $row;
    }
    return null;
}

function sponsor_public_order(array $row): array {
    $safe = sponsor_order_defaults($row);
    return [
        'out_trade_no' => $safe['out_trade_no'],
        'amount' => $safe['amount'],
        'status' => $safe['status'],
        'type' => $safe['type'],
        'type_label' => $safe['type_label'],
        'message' => $safe['message'],
        'display_name' => sponsor_public_name($safe),
        'created_at' => $safe['created_at'],
        'paid_at' => $safe['paid_at'],
    ];
}

function sponsor_order_for_owner(array $row): array {
    $safe = sponsor_order_defaults($row);
    unset($safe['user_id']);
    return $safe;
}

function sponsor_stats_summary(): array {
    $paidCount = 0;
    $total = 0.0;
    $latestPaidAt = '';
    foreach (sponsor_orders_all() as $row) {
        if (($row['status'] ?? '') !== 'paid') continue;
        $paidCount++;
        $total += (float)($row['amount'] ?? 0);
        $paidAt = (string)($row['paid_at'] ?: $row['updated_at'] ?? '');
        if ($paidAt > $latestPaidAt) $latestPaidAt = $paidAt;
    }
    return [
        'paid_count' => $paidCount,
        'paid_total' => number_format($total, 2, '.', ''),
        'latest_paid_at' => $latestPaidAt,
    ];
}

function sponsor_recent_public_orders(int $limit = 10): array {
    $items = array_values(array_filter(sponsor_orders_all(), fn($row) => ($row['status'] ?? '') === 'paid'));
    usort($items, fn($a, $b) => strcmp((string)($b['paid_at'] ?: $b['updated_at'] ?? ''), (string)($a['paid_at'] ?: $a['updated_at'] ?? '')));
    return array_map('sponsor_public_order', array_slice($items, 0, max(1, $limit)));
}

function sponsor_generate_trade_no(): string {
    return 'SP' . date('YmdHis') . mt_rand(1000, 9999);
}

function sponsor_mark_paid_from_callback(array $callback, string $source = 'notify'): ?array {
    $outTradeNo = trim((string)($callback['out_trade_no'] ?? ''));
    if ($outTradeNo === '') return null;
    $rows = sponsor_orders_all();
    $idx = sponsor_find_order_index($rows, $outTradeNo);
    if ($idx < 0) return null;

    $rows[$idx]['trade_no'] = trim((string)($callback['trade_no'] ?? $rows[$idx]['trade_no'] ?? ''));
    $rows[$idx]['status'] = (($callback['trade_status'] ?? '') === 'TRADE_SUCCESS') ? 'paid' : (($callback['trade_status'] ?? '') ?: $rows[$idx]['status']);
    $rows[$idx]['updated_at'] = date('Y-m-d H:i:s');
    if ($rows[$idx]['status'] === 'paid' && empty($rows[$idx]['paid_at'])) {
        $rows[$idx]['paid_at'] = date('Y-m-d H:i:s');
        $userId = (int)($rows[$idx]['user_id'] ?? 0);
        if ($userId > 0) {
            create_notification($userId, '赞助已到账', '感谢你的支持，订单 ' . $outTradeNo . ' 已支付成功。', 'sponsor', 'sponsor.html?order=' . rawurlencode($outTradeNo), ['amount' => $rows[$idx]['amount'], 'source' => $source]);
            add_user_log($userId, '赞助站长', '订单 ' . $outTradeNo . ' 支付成功，金额 ¥' . $rows[$idx]['amount']);
        }
    }
    sponsor_save_orders($rows);
    return sponsor_order_defaults($rows[$idx]);
}


function sponsor_refresh_order_from_gateway(string $outTradeNo): ?array {
    $outTradeNo = trim($outTradeNo);
    if ($outTradeNo === '') {
        return null;
    }
    $local = sponsor_find_order($outTradeNo);
    if (!$local) {
        return null;
    }
    if (($local['status'] ?? '') === 'paid') {
        return $local;
    }

    try {
        $result = sponsor_gateway()->queryOrder($outTradeNo);
    } catch (Throwable $e) {
        return $local;
    }

    $code = (string)($result['code'] ?? '');
    $status = (string)($result['status'] ?? '');
    if ($code !== '1' && $status !== '1') {
        return $local;
    }

    if ($status === '1') {
        $callback = [
            'out_trade_no' => $outTradeNo,
            'trade_no' => (string)($result['trade_no'] ?? ''),
            'trade_status' => 'TRADE_SUCCESS',
            'type' => (string)($result['type'] ?? ($local['type'] ?? '')),
            'money' => (string)($result['money'] ?? ($local['amount'] ?? '0.00')),
        ];
        $updated = sponsor_mark_paid_from_callback($callback, 'query');
        if ($updated && !empty($result['endtime'])) {
            $rows = sponsor_orders_all();
            $idx = sponsor_find_order_index($rows, $outTradeNo);
            if ($idx >= 0) {
                $rows[$idx]['paid_at'] = trim((string)$result['endtime']);
                $rows[$idx]['updated_at'] = date('Y-m-d H:i:s');
                sponsor_save_orders($rows);
                return sponsor_order_defaults($rows[$idx]);
            }
        }
        return $updated;
    }

    return $local;
}
