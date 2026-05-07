<?php
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/ai_api_gateway_lib.php';

$action = $_GET['action'] ?? 'overview';
$body = api_json_body();

function ai_api_console_user_payload(?array $user): ?array {
    if (!$user) return null;
    return [
        'id' => (int)($user['id'] ?? 0),
        'username' => (string)($user['username'] ?? ''),
        'nickname' => (string)($user['nickname'] ?? $user['username'] ?? ''),
        'is_admin' => !empty($user['is_admin']) || (($user['role'] ?? 'user') === 'admin'),
        'points' => (int)($user['points'] ?? 0),
        'level' => (int)($user['level'] ?? 1),
        'level_title' => (string)($user['level_title'] ?? ''),
        'email' => (string)($user['email'] ?? ''),
    ];
}

function ai_api_console_overview(): void {
    $user = current_user();
    $payload = [
        'ok' => true,
        'base_url' => ai_api_base_url(),
        'logged_in' => (bool)$user,
        'user' => ai_api_console_user_payload($user),
        'models' => ai_api_public_models(),
        'packages' => ai_api_packages(false),
        'payment_methods' => ai_api_payment_methods(),
        'payment_gateway' => sponsor_gateway_summary(),
        'docs' => [
            'python' => implode("\n", [
                'from openai import OpenAI',
                '',
                'client = OpenAI(api_key="sk-kd-...", base_url="' . ai_api_base_url() . '")',
                'resp = client.chat.completions.create(',
                '    model="openrouter::openrouter/free",',
                '    messages=[{"role": "user", "content": "你好"}],',
                '    max_completion_tokens=512',
                ')',
                'print(resp.choices[0].message.content)',
            ]),
            'responses' => implode("\n", [
                'from openai import OpenAI',
                '',
                'client = OpenAI(api_key="sk-kd-...", base_url="' . ai_api_base_url() . '")',
                'resp = client.responses.create(',
                '    model="openrouter::openrouter/free",',
                '    input="你好"',
                ')',
                'print(resp.output_text)',
            ]),
            'curl' => implode("\n", [
                'curl ' . ai_api_base_url() . '/chat/completions \\',
                '  -H "Authorization: Bearer sk-kd-..." \\',
                '  -H "Content-Type: application/json" \\',
                '  -d \'{"model":"openrouter::openrouter/free","messages":[{"role":"user","content":"你好"}],"max_completion_tokens":512}\'',
            ]),
            'curl_x_api_key' => implode("\n", [
                'curl ' . ai_api_base_url() . '/chat/completions \\',
                '  -H "X-API-Key: sk-kd-..." \\',
                '  -H "Content-Type: application/json" \\',
                '  -d \'{"model":"openrouter::openrouter/free","messages":[{"role":"user","content":"你好"}]}\'',
            ]),
        ],
    ];
    if ($user) {
        $uid = (int)($user['id'] ?? 0);
        $payload['wallet'] = ai_api_wallet_for_user($uid);
        $payload['keys'] = ai_api_user_keys($uid);
        $payload['orders'] = ai_api_user_orders($uid, 20);
        $payload['usage'] = ai_api_user_usage($uid, 30);
        $payload['ledger'] = ai_api_user_ledger($uid, 20);
        $payload['redeem_records'] = ai_api_user_redeem_records($uid, 10);
        $payload['subscriptions'] = ai_api_user_subscriptions($uid);
        $payload['developer_applications'] = function_exists('ai_api_user_developer_applications') ? ai_api_user_developer_applications($uid, 10) : [];
        if (!empty($user['is_admin'])) $payload['admin'] = ai_api_admin_summary();
    }
    ai_api_json($payload);
}

try {
    if ($action === 'overview') {
        ai_api_console_overview();
    }

    if ($action === 'admin_overview') {
        api_require_method(['GET', 'POST']);
        auth_require_admin();
        ai_api_json([
            'ok' => true,
            'base_url' => ai_api_base_url(),
            'models' => ai_api_public_models(),
            'packages' => ai_api_packages(true),
            'payment_methods' => ai_api_payment_methods(),
            'payment_gateway' => sponsor_gateway_summary(),
            'admin' => ai_api_admin_summary(),
        ]);
    }



    if ($action === 'developer_plan_overview') {
        api_require_method(['GET', 'POST']);
        $user = current_user();
        $uid = $user ? (int)($user['id'] ?? 0) : 0;
        ai_api_json([
            'ok' => true,
            'logged_in' => (bool)$user,
            'user' => ai_api_console_user_payload($user),
            'packages' => ai_api_packages(false),
            'applications' => $uid > 0 ? ai_api_user_developer_applications($uid, 20) : [],
            'stats' => ai_api_developer_application_stats(),
        ]);
    }

    if ($action === 'submit_developer_application') {
        api_require_method(['POST']);
        $user = require_active_user();
        $app = ai_api_submit_developer_application($user, $body);
        ai_api_json([
            'ok' => true,
            'msg' => '申请已提交，管理员审核通过后会选择套餐并发放 Token。',
            'application' => $app,
            'applications' => ai_api_user_developer_applications((int)$user['id'], 20),
            'stats' => ai_api_developer_application_stats(),
        ]);
    }

    if ($action === 'admin_review_developer_application') {
        api_require_method(['POST']);
        $admin = auth_require_admin();
        $app = ai_api_review_developer_application(
            (int)($body['id'] ?? 0),
            (string)($body['decision'] ?? ''),
            (int)($admin['id'] ?? 0),
            (string)($body['package_id'] ?? ''),
            (string)($body['note'] ?? '')
        );
        ai_api_json(['ok' => true, 'msg' => '开发者激励申请已处理。', 'application' => $app, 'stats' => ai_api_developer_application_stats()]);
    }

    if ($action === 'generate_key') {
        api_require_method(['POST']);
        $user = require_active_user();
        $created = ai_api_create_key((int)$user['id'], (string)($body['name'] ?? ''));
        ai_api_json(['ok' => true, 'msg' => 'API Key 已创建，请立即复制保存，刷新后不再显示完整密钥。', 'data' => $created, 'wallet' => ai_api_wallet_for_user((int)$user['id']), 'keys' => ai_api_user_keys((int)$user['id'])]);
    }

    if ($action === 'revoke_key') {
        api_require_method(['POST']);
        $user = require_active_user();
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) ai_api_json(['ok' => false, 'msg' => '缺少 Key ID'], 400);
        $ok = ai_api_revoke_key((int)$user['id'], $id, !empty($user['is_admin']));
        ai_api_json(['ok' => $ok, 'msg' => $ok ? 'API Key 已撤销' : '未找到可撤销的 API Key', 'keys' => ai_api_user_keys((int)$user['id'])]);
    }

    if ($action === 'redeem_code') {
        api_require_method(['POST']);
        $user = require_active_user();
        $code = trim((string)($body['code'] ?? ''));
        if ($code === '') ai_api_json(['ok' => false, 'msg' => '请输入兑换码'], 400);
        $result = ai_api_redeem_code((int)$user['id'], $code, [
            'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
        ]);
        ai_api_json([
            'ok' => true,
            'msg' => '兑换成功，+' . number_format((int)($result['record']['tokens'] ?? 0)) . ' Token 已到账',
            'data' => $result,
            'wallet' => ai_api_wallet_for_user((int)$user['id']),
            'ledger' => ai_api_user_ledger((int)$user['id'], 20),
            'redeem_records' => ai_api_user_redeem_records((int)$user['id'], 10),
        ]);
    }

    if ($action === 'create_order') {
        api_require_method(['POST']);
        $user = require_active_user();
        $packageId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($body['package_id'] ?? ''));
        if ($packageId === '') ai_api_json(['ok' => false, 'msg' => '缺少套餐 ID'], 400);
        $paymentType = ai_api_normalize_payment_type((string)($body['payment_type'] ?? $body['type'] ?? 'alipay')) ?: 'alipay';
        $result = ai_api_create_order((int)$user['id'], $packageId, $paymentType);
        ai_api_json(['ok' => true, 'msg' => $result['status'] === 'paid' ? 'Token 已到账' : '订单已创建，请在新窗口完成支付', 'data' => $result, 'pay_url' => $result['pay_url'] ?? '', 'wallet' => ai_api_wallet_for_user((int)$user['id']), 'orders' => ai_api_user_orders((int)$user['id'])]);
    }

    if ($action === 'check_order') {
        api_require_method(['POST']);
        $user = require_active_user();
        $orderNo = trim((string)($body['order_no'] ?? ''));
        if ($orderNo === '') ai_api_json(['ok' => false, 'msg' => '缺少订单号'], 400);
        $order = ai_api_find_order_by_no($orderNo);
        if (!$order || (int)($order['user_id'] ?? 0) !== (int)$user['id']) ai_api_json(['ok' => false, 'msg' => '订单不存在'], 404);
        if (($order['status'] ?? '') !== 'paid') $order = ai_api_refresh_order_from_gateway($orderNo) ?: $order;
        ai_api_json(['ok' => true, 'order' => ai_api_public_order($order), 'wallet' => ai_api_wallet_for_user((int)$user['id']), 'orders' => ai_api_user_orders((int)$user['id'])]);
    }
    if ($action === 'admin_approve_order') {
        api_require_method(['POST']);
        $admin = auth_require_admin();
        $order = ai_api_approve_order((int)($body['order_id'] ?? 0), (int)($admin['id'] ?? 0));
        ai_api_json(['ok' => true, 'msg' => '订单已确认并发放 Token', 'data' => $order, 'admin' => ai_api_admin_summary()]);
    }

    if ($action === 'admin_reject_order') {
        api_require_method(['POST']);
        auth_require_admin();
        $order = ai_api_reject_order((int)($body['order_id'] ?? 0), (string)($body['note'] ?? ''));
        ai_api_json(['ok' => true, 'msg' => '订单已取消', 'data' => $order, 'admin' => ai_api_admin_summary()]);
    }

    if ($action === 'admin_grant_tokens') {
        api_require_method(['POST']);
        $admin = auth_require_admin();
        $userId = (int)($body['user_id'] ?? 0);
        $tokens = (int)($body['tokens'] ?? 0);
        $note = mb_substr(trim((string)($body['note'] ?? '管理员手动补发')), 0, 120, 'UTF-8');
        if ($userId <= 0 || $tokens === 0) ai_api_json(['ok' => false, 'msg' => '请填写用户 ID 和 Token 数量'], 400);
        if (!find_user_by_id($userId)) ai_api_json(['ok' => false, 'msg' => '用户不存在'], 404);
        $wallet = ai_api_update_wallet($userId, $tokens, 'admin_adjust', ['note' => $note, 'admin_id' => (int)($admin['id'] ?? 0)]);
        add_user_log($userId, 'AI API Token 调整', $note . ' ' . ($tokens > 0 ? '+' : '') . $tokens);
        ai_api_json(['ok' => true, 'msg' => 'Token 已调整', 'wallet' => $wallet, 'admin' => ai_api_admin_summary()]);
    }

    if ($action === 'admin_revoke_key') {
        api_require_method(['POST']);
        $admin = auth_require_admin();
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) ai_api_json(['ok' => false, 'msg' => '缺少 Key ID'], 400);
        $ok = ai_api_revoke_key((int)($admin['id'] ?? 0), $id, true);
        ai_api_json(['ok' => $ok, 'msg' => $ok ? 'API Key 已停用' : '未找到 API Key', 'admin' => ai_api_admin_summary()]);
    }

    if ($action === 'admin_save_packages') {
        api_require_method(['POST']);
        auth_require_admin();
        $packages = $body['packages'] ?? [];
        if (is_string($packages)) $packages = json_decode($packages, true);
        if (!is_array($packages)) ai_api_json(['ok' => false, 'msg' => '套餐 JSON 格式错误'], 400);
        $saved = ai_api_save_packages($packages);
        ai_api_json(['ok' => true, 'msg' => 'Token 套餐已保存', 'packages' => $saved]);
    }

    ai_api_json(['ok' => false, 'msg' => '未知操作：' . $action], 404);
} catch (Throwable $e) {
    ai_api_json(['ok' => false, 'msg' => $e->getMessage()], 500);
}
?>
