<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api_security.php';
require_once __DIR__ . '/sponsor_payment_lib.php';

if (!defined('AI_API_KEYS_FILE')) define('AI_API_KEYS_FILE', DATA_DIR . '/ai_api_keys.json');
if (!defined('AI_API_WALLETS_FILE')) define('AI_API_WALLETS_FILE', DATA_DIR . '/ai_api_wallets.json');
if (!defined('AI_API_LEDGER_FILE')) define('AI_API_LEDGER_FILE', DATA_DIR . '/ai_api_ledger.json');
if (!defined('AI_API_USAGE_FILE')) define('AI_API_USAGE_FILE', DATA_DIR . '/ai_api_usage.json');
if (!defined('AI_API_PACKAGES_FILE')) define('AI_API_PACKAGES_FILE', DATA_DIR . '/ai_api_packages.json');
if (!defined('AI_API_ORDERS_FILE')) define('AI_API_ORDERS_FILE', DATA_DIR . '/ai_api_orders.json');
if (!defined('AI_API_RATE_LIMIT_FILE')) define('AI_API_RATE_LIMIT_FILE', DATA_DIR . '/ai_api_rate_limits.json');
if (!defined('AI_API_REDEEM_CODES_FILE')) define('AI_API_REDEEM_CODES_FILE', DATA_DIR . '/ai_api_redeem_codes.json');
if (!defined('AI_API_REDEEM_RECORDS_FILE')) define('AI_API_REDEEM_RECORDS_FILE', DATA_DIR . '/ai_api_redeem_records.json');
if (!defined('AI_API_DEVELOPER_APPLICATIONS_FILE')) define('AI_API_DEVELOPER_APPLICATIONS_FILE', DATA_DIR . '/ai_api_developer_applications.json');

function ai_api_now(): string { return date('Y-m-d H:i:s'); }


function ai_api_cors_headers(): void {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS, HEAD');
    header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept, X-API-Key, api-key, OpenAI-Organization, OpenAI-Project, OpenAI-Beta, X-Requested-With');
    header('Access-Control-Expose-Headers: X-KingDungeon-Token-Charged, X-KingDungeon-Token-Balance, X-Request-Id');
    header('Access-Control-Max-Age: 86400');
    header('Access-Control-Allow-Private-Network: true');
}

function ai_api_preflight_or_continue(): void {
    ai_api_cors_headers();
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function ai_api_request_headers(): array {
    $headers = [];
    if (function_exists('getallheaders')) {
        $got = getallheaders();
        if (is_array($got)) {
            foreach ($got as $k => $v) $headers[strtolower((string)$k)] = (string)$v;
        }
    }
    foreach ($_SERVER as $k => $v) {
        if (str_starts_with((string)$k, 'HTTP_')) {
            $name = strtolower(str_replace('_', '-', substr((string)$k, 5)));
            $headers[$name] = (string)$v;
        }
    }
    foreach (['authorization', 'redirect-http-authorization', 'x-http-authorization'] as $name) {
        $serverKey = strtoupper(str_replace('-', '_', $name));
        if (!empty($_SERVER[$serverKey])) $headers[$name] = (string)$_SERVER[$serverKey];
    }
    return $headers;
}


function ai_api_store_read(string $file): array {
    if (!is_dir(dirname($file))) @mkdir(dirname($file), 0777, true);
    return read_store($file);
}

function ai_api_store_write(string $file, array $rows): void {
    if (!is_dir(dirname($file))) @mkdir(dirname($file), 0777, true);
    write_store($file, array_values($rows));
}

function ai_api_json(array $payload, int $status = 200): void {
    http_response_code($status);
    ai_api_cors_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ai_api_base_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'example.com';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/ai_api_console_api.php';
    $root = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if (preg_match('#/v1(?:/.*)?$#', $root)) {
        $root = preg_replace('#/v1(?:/.*)?$#', '', $root) ?: '';
    }
    return $scheme . '://' . $host . ($root === '' ? '' : $root) . '/v1';
}

function ai_api_request_origin(): string {
    $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin !== '' && preg_match('#^https?://#i', $origin)) {
        return rtrim($origin, '/');
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') $host = 'example.com';

    return $scheme . '://' . $host;
}

function ai_api_default_packages(): array {
    return [
        [
            'id' => 'trial_20k',
            'title' => '注册试用包',
            'description' => '每个账号限领一次，用于测试 OpenAI 兼容调用和多模型切换。',
            'tokens' => 20000,
            'price' => 0,
            'currency' => 'CNY',
            'kind' => 'trial',
            'period_days' => 0,
            'badge' => '免费',
            'recommended' => false,
            'auto_grant' => true,
            'once_per_user' => true,
            'model_scope' => ['all'],
            'features' => ['注册后可领一次', '适合测试 API Key', '支持所有已上架模型'],
            'sort_order' => 1,
        ],
        [
            'id' => 'sub_basic_month',
            'title' => '轻量月卡',
            'description' => '适合个人聊天、轻量写作和偶尔调用 API。',
            'tokens' => 2000000,
            'price' => 19,
            'currency' => 'CNY',
            'kind' => 'subscription',
            'period_days' => 30,
            'badge' => '个人推荐',
            'recommended' => false,
            'auto_grant' => false,
            'once_per_user' => false,
            'model_scope' => ['all'],
            'features' => ['2M Token / 30 天', '适合个人轻量对话', '可用 Kimi / 百炼 / GitHub / 硅基流动'],
            'sort_order' => 10,
        ],
        [
            'id' => 'sub_plus_month',
            'title' => '进阶月卡',
            'description' => '适合高频创作、资料总结、代码辅助和多模型对比。',
            'tokens' => 8000000,
            'price' => 49,
            'currency' => 'CNY',
            'kind' => 'subscription',
            'period_days' => 30,
            'badge' => '最受欢迎',
            'recommended' => true,
            'auto_grant' => false,
            'once_per_user' => false,
            'model_scope' => ['all'],
            'features' => ['8M Token / 30 天', '适合高频 AI 创作', '优先推荐给普通用户和站长'],
            'sort_order' => 20,
        ],
        [
            'id' => 'sub_pro_month',
            'title' => '专业月卡',
            'description' => '适合开发者、批量脚本、插件接入和更长上下文任务。',
            'tokens' => 25000000,
            'price' => 99,
            'currency' => 'CNY',
            'kind' => 'subscription',
            'period_days' => 30,
            'badge' => '开发者',
            'recommended' => false,
            'auto_grant' => false,
            'once_per_user' => false,
            'model_scope' => ['all'],
            'features' => ['25M Token / 30 天', '适合 API 接入和批量任务', '更适合推理/代码模型'],
            'sort_order' => 30,
        ],
        [
            'id' => 'sub_team_month',
            'title' => '团队月卡',
            'description' => '适合小团队、工作室或站内多人共享调用。',
            'tokens' => 80000000,
            'price' => 299,
            'currency' => 'CNY',
            'kind' => 'subscription',
            'period_days' => 30,
            'badge' => '团队',
            'recommended' => false,
            'auto_grant' => false,
            'once_per_user' => false,
            'model_scope' => ['all'],
            'features' => ['80M Token / 30 天', '适合团队和工作室', '高频调用更划算'],
            'sort_order' => 40,
        ],
        [
            'id' => 'topup_1m',
            'title' => '1M Token 加量包',
            'description' => '适合临时补充余额，不绑定月度有效期。',
            'tokens' => 1000000,
            'price' => 10,
            'currency' => 'CNY',
            'kind' => 'topup',
            'period_days' => 0,
            'badge' => '补量',
            'recommended' => false,
            'auto_grant' => false,
            'once_per_user' => false,
            'model_scope' => ['all'],
            'features' => ['1M Token', '余额长期可用', '适合临时测试'],
            'sort_order' => 110,
        ],
        [
            'id' => 'topup_6m',
            'title' => '6M Token 加量包',
            'description' => '适合阶段性项目、内容批量生成和插件调试。',
            'tokens' => 6000000,
            'price' => 50,
            'currency' => 'CNY',
            'kind' => 'topup',
            'period_days' => 0,
            'badge' => '项目补量',
            'recommended' => false,
            'auto_grant' => false,
            'once_per_user' => false,
            'model_scope' => ['all'],
            'features' => ['6M Token', '适合项目制使用', '比小包更划算'],
            'sort_order' => 120,
        ],
        [
            'id' => 'topup_20m',
            'title' => '20M Token 加量包',
            'description' => '适合短期大批量任务、批量分析和集中开发。',
            'tokens' => 20000000,
            'price' => 150,
            'currency' => 'CNY',
            'kind' => 'topup',
            'period_days' => 0,
            'badge' => '大额补量',
            'recommended' => false,
            'auto_grant' => false,
            'once_per_user' => false,
            'model_scope' => ['all'],
            'features' => ['20M Token', '适合集中调用', '单价更低'],
            'sort_order' => 130,
        ],
    ];
}

function ai_api_packages(bool $includeDisabled = false): array {
    $rows = ai_api_store_read(AI_API_PACKAGES_FILE);
    if (!$rows) $rows = ai_api_default_packages();
    foreach ($rows as &$row) {
        $row['id'] = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($row['id'] ?? '')) ?: ('pkg_' . substr(md5(json_encode($row)), 0, 8));
        $row['title'] = trim((string)($row['title'] ?? $row['id']));
        $row['description'] = trim((string)($row['description'] ?? ''));
        $row['tokens'] = max(0, (int)($row['tokens'] ?? 0));
        $row['price'] = max(0, (float)($row['price'] ?? 0));
        $row['currency'] = trim((string)($row['currency'] ?? 'CNY')) ?: 'CNY';
        $kind = strtolower(trim((string)($row['kind'] ?? 'topup')));
        $row['kind'] = in_array($kind, ['trial', 'subscription', 'topup'], true) ? $kind : 'topup';
        $row['period_days'] = max(0, (int)($row['period_days'] ?? 0));
        $row['badge'] = mb_substr(trim((string)($row['badge'] ?? '')), 0, 20, 'UTF-8');
        $row['recommended'] = !empty($row['recommended']);
        $row['features'] = array_values(array_filter(array_map('strval', (array)($row['features'] ?? [])), fn($v) => trim($v) !== ''));
        $row['enabled'] = array_key_exists('enabled', $row) ? !empty($row['enabled']) : true;
        $row['auto_grant'] = !empty($row['auto_grant']);
        $row['once_per_user'] = !empty($row['once_per_user']);
        $row['model_scope'] = array_values((array)($row['model_scope'] ?? ['all']));
        $row['sort_order'] = (int)($row['sort_order'] ?? 99);
    }
    unset($row);
    usort($rows, fn($a, $b) => ((int)($a['sort_order'] ?? 0)) <=> ((int)($b['sort_order'] ?? 0)));
    if (!$includeDisabled) $rows = array_values(array_filter($rows, fn($row) => !empty($row['enabled'])));
    return array_values($rows);
}

function ai_api_save_packages(array $packages): array {
    $clean = [];
    foreach ($packages as $row) {
        if (!is_array($row)) continue;
        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($row['id'] ?? ''));
        if ($id === '') continue;
        $clean[] = [
            'id' => $id,
            'title' => mb_substr(trim((string)($row['title'] ?? $id)), 0, 60, 'UTF-8'),
            'description' => mb_substr(trim((string)($row['description'] ?? '')), 0, 200, 'UTF-8'),
            'tokens' => max(0, (int)($row['tokens'] ?? 0)),
            'price' => max(0, (float)($row['price'] ?? 0)),
            'currency' => trim((string)($row['currency'] ?? 'CNY')) ?: 'CNY',
            'kind' => in_array(strtolower(trim((string)($row['kind'] ?? 'topup'))), ['trial', 'subscription', 'topup'], true) ? strtolower(trim((string)($row['kind'] ?? 'topup'))) : 'topup',
            'period_days' => max(0, (int)($row['period_days'] ?? 0)),
            'badge' => mb_substr(trim((string)($row['badge'] ?? '')), 0, 20, 'UTF-8'),
            'recommended' => !empty($row['recommended']),
            'features' => array_values(array_filter(array_map('strval', (array)($row['features'] ?? [])), fn($v) => trim($v) !== '')),
            'enabled' => !empty($row['enabled']),
            'auto_grant' => !empty($row['auto_grant']),
            'once_per_user' => !empty($row['once_per_user']),
            'model_scope' => array_values((array)($row['model_scope'] ?? ['all'])),
            'sort_order' => (int)($row['sort_order'] ?? 99),
        ];
    }
    if (!$clean) $clean = ai_api_default_packages();
    ai_api_store_write(AI_API_PACKAGES_FILE, $clean);
    return ai_api_packages(true);
}



function ai_api_developer_applications_all(): array { return ai_api_store_read(AI_API_DEVELOPER_APPLICATIONS_FILE); }
function ai_api_save_developer_applications(array $rows): void { ai_api_store_write(AI_API_DEVELOPER_APPLICATIONS_FILE, $rows); }

function ai_api_clean_text_field(mixed $value, int $limit = 500): string {
    $text = trim((string)$value);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]+/u', '', $text) ?? '';
    return mb_substr($text, 0, $limit, 'UTF-8');
}

function ai_api_public_developer_application(array $row): array {
    return [
        'id' => (int)($row['id'] ?? 0),
        'user_id' => (int)($row['user_id'] ?? 0),
        'username' => (string)($row['username'] ?? ''),
        'contact_email' => (string)($row['contact_email'] ?? ''),
        'applicant_type' => (string)($row['applicant_type'] ?? ''),
        'project_name' => (string)($row['project_name'] ?? ''),
        'project_url' => (string)($row['project_url'] ?? ''),
        'project_stage' => (string)($row['project_stage'] ?? ''),
        'ai_tools' => (string)($row['ai_tools'] ?? ''),
        'project_desc' => (string)($row['project_desc'] ?? ''),
        'usage_plan' => (string)($row['usage_plan'] ?? ''),
        'proof_url' => (string)($row['proof_url'] ?? ''),
        'expected_package_id' => (string)($row['expected_package_id'] ?? ''),
        'status' => (string)($row['status'] ?? 'pending'),
        'admin_note' => (string)($row['admin_note'] ?? ''),
        'granted_package_id' => (string)($row['granted_package_id'] ?? ''),
        'granted_package_title' => (string)($row['granted_package_title'] ?? ''),
        'granted_tokens' => max(0, (int)($row['granted_tokens'] ?? 0)),
        'created_at' => (string)($row['created_at'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
        'reviewed_at' => (string)($row['reviewed_at'] ?? ''),
    ];
}

function ai_api_user_developer_applications(int $userId, int $limit = 20): array {
    $rows = array_values(array_filter(ai_api_developer_applications_all(), fn($row) => (int)($row['user_id'] ?? 0) === $userId));
    usort($rows, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return array_map('ai_api_public_developer_application', array_slice($rows, 0, $limit));
}

function ai_api_submit_developer_application(array $user, array $payload): array {
    $userId = (int)($user['id'] ?? 0);
    if ($userId <= 0) throw new RuntimeException('请先登录后再提交申请。');
    $rows = ai_api_developer_applications_all();
    foreach ($rows as $row) {
        if ((int)($row['user_id'] ?? 0) === $userId && (string)($row['status'] ?? 'pending') === 'pending') {
            throw new RuntimeException('你已有一份待审核申请，请等待管理员处理后再重新提交。');
        }
    }

    $contactEmail = ai_api_clean_text_field($payload['contact_email'] ?? ($user['email'] ?? ''), 120);
    if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('请填写有效的联系邮箱。');
    $projectName = ai_api_clean_text_field($payload['project_name'] ?? '', 80);
    $projectDesc = ai_api_clean_text_field($payload['project_desc'] ?? '', 1200);
    $usagePlan = ai_api_clean_text_field($payload['usage_plan'] ?? '', 1200);
    if (mb_strlen($projectName, 'UTF-8') < 2) throw new RuntimeException('请填写项目名称。');
    if (mb_strlen($projectDesc, 'UTF-8') < 20) throw new RuntimeException('项目介绍至少需要 20 个字，便于管理员评估。');
    if (mb_strlen($usagePlan, 'UTF-8') < 10) throw new RuntimeException('请说明你计划如何使用 API Token。');

    $expectedPackageId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($payload['expected_package_id'] ?? '')) ?: '';
    $row = [
        'id' => next_id($rows),
        'user_id' => $userId,
        'username' => (string)($user['username'] ?? ''),
        'contact_email' => $contactEmail,
        'applicant_type' => ai_api_clean_text_field($payload['applicant_type'] ?? '个人开发者', 40),
        'project_name' => $projectName,
        'project_url' => ai_api_clean_text_field($payload['project_url'] ?? '', 240),
        'project_stage' => ai_api_clean_text_field($payload['project_stage'] ?? '开发中', 40),
        'ai_tools' => ai_api_clean_text_field($payload['ai_tools'] ?? '', 500),
        'project_desc' => $projectDesc,
        'usage_plan' => $usagePlan,
        'proof_url' => ai_api_clean_text_field($payload['proof_url'] ?? '', 240),
        'expected_package_id' => $expectedPackageId,
        'status' => 'pending',
        'admin_note' => '',
        'granted_package_id' => '',
        'granted_package_title' => '',
        'granted_tokens' => 0,
        'created_at' => ai_api_now(),
        'updated_at' => ai_api_now(),
        'reviewed_at' => '',
        'reviewed_by' => 0,
        'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 240, 'UTF-8'),
    ];
    $rows[] = $row;
    ai_api_save_developer_applications($rows);
    add_user_log($userId, '提交开发者激励申请', $projectName);
    return ai_api_public_developer_application($row);
}

function ai_api_review_developer_application(int $id, string $decision, int $adminId = 0, string $packageId = '', string $note = ''): array {
    $decision = strtolower(trim($decision));
    if (!in_array($decision, ['approved', 'rejected'], true)) throw new RuntimeException('审核状态无效。');
    $rows = ai_api_developer_applications_all();
    foreach ($rows as &$row) {
        if ((int)($row['id'] ?? 0) !== $id) continue;
        if ((string)($row['status'] ?? 'pending') !== 'pending') throw new RuntimeException('该申请已处理，不能重复发放。');
        $userId = (int)($row['user_id'] ?? 0);
        if (!find_user_by_id($userId)) throw new RuntimeException('申请用户不存在。');
        $row['status'] = $decision;
        $row['admin_note'] = ai_api_clean_text_field($note, 400);
        $row['reviewed_at'] = ai_api_now();
        $row['updated_at'] = ai_api_now();
        $row['reviewed_by'] = $adminId;

        if ($decision === 'approved') {
            $packageId = preg_replace('/[^a-zA-Z0-9_-]/', '', $packageId) ?: '';
            if ($packageId === '') throw new RuntimeException('通过申请时必须选择发放套餐。');
            $pkg = ai_api_find_package($packageId);
            if (!$pkg) throw new RuntimeException('选择的套餐不存在。');
            $tokens = max(0, (int)($pkg['tokens'] ?? 0));
            if ($tokens <= 0) throw new RuntimeException('选择的套餐 Token 数量必须大于 0。');
            $row['granted_package_id'] = $packageId;
            $row['granted_package_title'] = (string)($pkg['title'] ?? $packageId);
            $row['granted_tokens'] = $tokens;
            $wallet = ai_api_update_wallet($userId, $tokens, 'package_grant', ai_api_package_grant_meta($pkg, $packageId, '', [
                'developer_incentive' => true,
                'application_id' => $id,
                'approved_by' => $adminId,
                'admin_note' => $row['admin_note'],
            ]));
            $row['wallet_after'] = (int)($wallet['balance_tokens'] ?? 0);
            add_user_log($userId, '开发者激励 Token 到账', $row['granted_package_title'] . ' +' . $tokens);
            if (function_exists('create_notification')) {
                create_notification($userId, '开发者激励申请已通过', '管理员已为你发放 ' . $row['granted_package_title'] . '，共 ' . number_format($tokens) . ' Token。', 'system', 'developer-plan.html');
            }
        } else {
            add_user_log($userId, '开发者激励申请未通过', $row['admin_note'] ?: '管理员未填写备注');
            if (function_exists('create_notification')) {
                create_notification($userId, '开发者激励申请未通过', $row['admin_note'] ?: '你的开发者激励申请暂未通过，可补充材料后重新提交。', 'system', 'developer-plan.html');
            }
        }
        ai_api_save_developer_applications($rows);
        return ai_api_public_developer_application($row);
    }
    unset($row);
    throw new RuntimeException('申请记录不存在。');
}

function ai_api_developer_application_stats(): array {
    $rows = ai_api_developer_applications_all();
    $stats = ['total' => count($rows), 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'granted_tokens' => 0];
    foreach ($rows as $row) {
        $status = (string)($row['status'] ?? 'pending');
        if (isset($stats[$status])) $stats[$status]++;
        if ($status === 'approved') $stats['granted_tokens'] += max(0, (int)($row['granted_tokens'] ?? 0));
    }
    return $stats;
}

function ai_api_hash_key(string $key): string { return hash('sha256', $key); }

function ai_api_key_prefix(string $key): string {
    return substr($key, 0, 12) . '...' . substr($key, -6);
}

function ai_api_generate_secret(): string {
    return 'sk-kd-' . bin2hex(random_bytes(28));
}

function ai_api_keys_all(): array { return ai_api_store_read(AI_API_KEYS_FILE); }
function ai_api_save_keys(array $rows): void { ai_api_store_write(AI_API_KEYS_FILE, $rows); }

function ai_api_public_key_row(array $row): array {
    return [
        'id' => (int)($row['id'] ?? 0),
        'name' => (string)($row['name'] ?? '默认密钥'),
        'key_prefix' => (string)($row['key_prefix'] ?? ''),
        'status' => (string)($row['status'] ?? 'active'),
        'created_at' => (string)($row['created_at'] ?? ''),
        'last_used_at' => (string)($row['last_used_at'] ?? ''),
        'last_ip' => (string)($row['last_ip'] ?? ''),
    ];
}

function ai_api_user_keys(int $userId): array {
    $rows = array_values(array_filter(ai_api_keys_all(), fn($row) => (int)($row['user_id'] ?? 0) === $userId));
    usort($rows, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return array_map('ai_api_public_key_row', $rows);
}

function ai_api_create_key(int $userId, string $name = ''): array {
    $rows = ai_api_keys_all();
    $activeCount = 0;
    foreach ($rows as $row) if ((int)($row['user_id'] ?? 0) === $userId && (($row['status'] ?? 'active') === 'active')) $activeCount++;
    if ($activeCount >= 5) throw new RuntimeException('每个账号最多保留 5 个启用中的 API Key');
    $secret = ai_api_generate_secret();
    $row = [
        'id' => next_id($rows),
        'user_id' => $userId,
        'name' => mb_substr(trim($name) ?: '默认 API Key', 0, 40, 'UTF-8'),
        'key_hash' => ai_api_hash_key($secret),
        'key_prefix' => ai_api_key_prefix($secret),
        'status' => 'active',
        'created_at' => ai_api_now(),
        'last_used_at' => '',
        'last_ip' => '',
    ];
    $rows[] = $row;
    ai_api_save_keys($rows);
    add_user_log($userId, '创建 AI API Key', $row['key_prefix']);
    return ['secret' => $secret, 'key' => ai_api_public_key_row($row)];
}

function ai_api_revoke_key(int $userId, int $keyId, bool $asAdmin = false): bool {
    $rows = ai_api_keys_all();
    $changed = false;
    foreach ($rows as &$row) {
        if ((int)($row['id'] ?? 0) !== $keyId) continue;
        if (!$asAdmin && (int)($row['user_id'] ?? 0) !== $userId) continue;
        $row['status'] = 'revoked';
        $row['revoked_at'] = ai_api_now();
        $changed = true;
        break;
    }
    unset($row);
    if ($changed) ai_api_save_keys($rows);
    return $changed;
}

function ai_api_authenticate_request(): array {
    $auth = '';
    $headers = ai_api_request_headers();
    foreach ([
        $headers['authorization'] ?? '',
        $headers['redirect-http-authorization'] ?? '',
        $headers['x-http-authorization'] ?? '',
        $_SERVER['HTTP_AUTHORIZATION'] ?? '',
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '',
        $_SERVER['Authorization'] ?? '',
    ] as $candidate) {
        $candidate = trim((string)$candidate);
        if ($candidate === '') continue;
        if (preg_match('/Bearer\s+(.+)/i', $candidate, $m)) { $auth = trim($m[1]); break; }
        if (preg_match('/^sk-[A-Za-z0-9_\-.]+$/', $candidate)) { $auth = $candidate; break; }
    }
    if ($auth === '') {
        foreach (['x-api-key', 'api-key', 'apikey'] as $h) {
            $candidate = trim((string)($headers[$h] ?? ''));
            if ($candidate !== '') { $auth = $candidate; break; }
        }
    }
    if ($auth === '' && isset($_GET['api_key'])) $auth = trim((string)$_GET['api_key']);
    if ($auth === '') ai_api_openai_error('invalid_request_error', '缺少 Authorization: Bearer sk-... 请求头，也可兼容 X-API-Key / api-key 请求头', 401);

    $hash = ai_api_hash_key($auth);
    $rows = ai_api_keys_all();
    $matched = null;
    foreach ($rows as $idx => $row) {
        if (($row['key_hash'] ?? '') !== $hash) continue;
        if (($row['status'] ?? 'active') !== 'active') ai_api_openai_error('invalid_api_key', 'API Key 已被禁用或撤销', 401);
        $matched = $row;
        $rows[$idx]['last_used_at'] = ai_api_now();
        $rows[$idx]['last_ip'] = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        ai_api_save_keys($rows);
        break;
    }
    if (!$matched) ai_api_openai_error('invalid_api_key', 'API Key 不存在或不正确', 401);
    $user = find_user_by_id((int)($matched['user_id'] ?? 0));
    if (!$user) ai_api_openai_error('invalid_api_key', 'API Key 对应用户不存在', 401);
    if (($user['status'] ?? 'active') !== 'active' || is_user_banned($user)) ai_api_openai_error('account_error', '当前账号已被限制，无法调用 API', 401);
    return ['key' => $matched, 'user' => $user, 'secret_hash' => $hash];
}

function ai_api_wallets_all(): array { return ai_api_store_read(AI_API_WALLETS_FILE); }
function ai_api_save_wallets(array $rows): void { ai_api_store_write(AI_API_WALLETS_FILE, $rows); }

function ai_api_wallet_for_user(int $userId): array {
    foreach (ai_api_wallets_all() as $row) {
        if ((int)($row['user_id'] ?? 0) === $userId) {
            return [
                'user_id' => $userId,
                'balance_tokens' => max(0, (int)($row['balance_tokens'] ?? 0)),
                'total_granted_tokens' => max(0, (int)($row['total_granted_tokens'] ?? 0)),
                'total_used_tokens' => max(0, (int)($row['total_used_tokens'] ?? 0)),
                'updated_at' => (string)($row['updated_at'] ?? ''),
            ];
        }
    }
    return ['user_id' => $userId, 'balance_tokens' => 0, 'total_granted_tokens' => 0, 'total_used_tokens' => 0, 'updated_at' => '']; 
}

function ai_api_update_wallet(int $userId, int $delta, string $type, array $meta = []): array {
    $rows = ai_api_wallets_all();
    $idx = -1;
    foreach ($rows as $i => $row) if ((int)($row['user_id'] ?? 0) === $userId) { $idx = $i; break; }
    if ($idx < 0) {
        $rows[] = ['user_id' => $userId, 'balance_tokens' => 0, 'total_granted_tokens' => 0, 'total_used_tokens' => 0, 'updated_at' => ''];
        $idx = count($rows) - 1;
    }
    $before = max(0, (int)($rows[$idx]['balance_tokens'] ?? 0));
    $after = max(0, $before + $delta);
    $rows[$idx]['balance_tokens'] = $after;
    if ($delta > 0) $rows[$idx]['total_granted_tokens'] = max(0, (int)($rows[$idx]['total_granted_tokens'] ?? 0)) + $delta;
    if ($delta < 0) $rows[$idx]['total_used_tokens'] = max(0, (int)($rows[$idx]['total_used_tokens'] ?? 0)) + abs($delta);
    $rows[$idx]['updated_at'] = ai_api_now();
    ai_api_save_wallets($rows);
    ai_api_add_ledger($userId, $type, $delta, $before, $after, $meta);
    return ai_api_wallet_for_user($userId);
}

function ai_api_add_ledger(int $userId, string $type, int $delta, int $before, int $after, array $meta = []): void {
    $rows = ai_api_store_read(AI_API_LEDGER_FILE);
    $rows[] = [
        'id' => next_id($rows),
        'user_id' => $userId,
        'type' => $type,
        'delta_tokens' => $delta,
        'balance_before' => $before,
        'balance_after' => $after,
        'meta' => $meta,
        'created_at' => ai_api_now(),
    ];
    ai_api_store_write(AI_API_LEDGER_FILE, $rows);
}

function ai_api_user_ledger(int $userId, int $limit = 20): array {
    $rows = array_values(array_filter(ai_api_store_read(AI_API_LEDGER_FILE), fn($row) => (int)($row['user_id'] ?? 0) === $userId));
    usort($rows, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return array_slice($rows, 0, $limit);
}


function ai_api_redeem_codes_all(): array { return ai_api_store_read(AI_API_REDEEM_CODES_FILE); }
function ai_api_save_redeem_codes(array $rows): void { ai_api_store_write(AI_API_REDEEM_CODES_FILE, $rows); }
function ai_api_redeem_records_all(): array { return ai_api_store_read(AI_API_REDEEM_RECORDS_FILE); }
function ai_api_save_redeem_records(array $rows): void { ai_api_store_write(AI_API_REDEEM_RECORDS_FILE, $rows); }

function ai_api_normalize_redeem_code(string $code): string {
    $code = strtoupper(trim($code));
    $code = preg_replace('/[^A-Z0-9]+/', '', $code) ?? '';
    return $code;
}

function ai_api_redeem_code_hash(string $code): string {
    return hash('sha256', ai_api_normalize_redeem_code($code));
}

function ai_api_format_redeem_code(string $raw, string $prefix = ''): string {
    $normalized = ai_api_normalize_redeem_code($raw);
    $prefix = ai_api_normalize_redeem_code($prefix);
    if ($prefix !== '' && str_starts_with($normalized, $prefix)) {
        $body = substr($normalized, strlen($prefix));
        $chunks = str_split($body, 4);
        return $prefix . ($chunks ? '-' . implode('-', $chunks) : '');
    }
    return implode('-', str_split($normalized, 4));
}

function ai_api_redeem_code_preview(string $code): string {
    $normalized = ai_api_normalize_redeem_code($code);
    if ($normalized === '') return '';
    if (strlen($normalized) <= 10) return substr($normalized, 0, 3) . '...' . substr($normalized, -3);
    return substr($normalized, 0, 6) . '...' . substr($normalized, -4);
}

function ai_api_generate_redeem_code_plain(string $prefix = 'AIF', int $groups = 4, int $groupLen = 4): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $prefix = ai_api_normalize_redeem_code($prefix) ?: 'AIF';
    $groups = max(2, min(6, $groups));
    $groupLen = max(3, min(6, $groupLen));
    $parts = [$prefix];
    for ($g = 0; $g < $groups; $g++) {
        $part = '';
        for ($i = 0; $i < $groupLen; $i++) $part .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        $parts[] = $part;
    }
    return implode('-', $parts);
}

function ai_api_normalize_redeem_code_row(array $row): array {
    $status = strtolower(trim((string)($row['status'] ?? 'active')));
    if (!in_array($status, ['active', 'disabled', 'depleted'], true)) $status = 'active';
    $tokens = max(0, (int)($row['tokens'] ?? 0));
    $maxUses = max(0, (int)($row['max_uses'] ?? 1));
    $usedCount = max(0, (int)($row['used_count'] ?? 0));
    if ($maxUses > 0 && $usedCount >= $maxUses && $status === 'active') $status = 'depleted';
    return [
        'id' => max(0, (int)($row['id'] ?? 0)),
        'code_hash' => (string)($row['code_hash'] ?? ''),
        'code_preview' => (string)($row['code_preview'] ?? ''),
        'title' => mb_substr(trim((string)($row['title'] ?? 'Token 兑换码')), 0, 80, 'UTF-8'),
        'tokens' => $tokens,
        'status' => $status,
        'max_uses' => $maxUses,
        'used_count' => $usedCount,
        'per_user_limit' => max(1, (int)($row['per_user_limit'] ?? 1)),
        'starts_at' => trim((string)($row['starts_at'] ?? '')),
        'expires_at' => trim((string)($row['expires_at'] ?? '')),
        'batch_id' => mb_substr(trim((string)($row['batch_id'] ?? '')), 0, 80, 'UTF-8'),
        'note' => mb_substr(trim((string)($row['note'] ?? '')), 0, 300, 'UTF-8'),
        'created_by' => (string)($row['created_by'] ?? ''),
        'created_at' => (string)($row['created_at'] ?? ai_api_now()),
        'updated_at' => (string)($row['updated_at'] ?? ''),
        'last_used_at' => (string)($row['last_used_at'] ?? ''),
    ];
}

function ai_api_redeem_code_public(array $row): array {
    $row = ai_api_normalize_redeem_code_row($row);
    unset($row['code_hash']);
    return $row;
}

function ai_api_public_redeem_record(array $row, array $users = []): array {
    $uid = (int)($row['user_id'] ?? 0);
    $safe = [
        'id' => (int)($row['id'] ?? 0),
        'code_id' => (int)($row['code_id'] ?? 0),
        'code_preview' => (string)($row['code_preview'] ?? ''),
        'title' => (string)($row['title'] ?? ''),
        'tokens' => max(0, (int)($row['tokens'] ?? 0)),
        'user_id' => $uid,
        'created_at' => (string)($row['created_at'] ?? ''),
        'ip' => (string)($row['ip'] ?? ''),
    ];
    if ($users) $safe['user'] = ai_api_admin_user_brief($uid, $users);
    return $safe;
}

function ai_api_user_redeem_records(int $userId, int $limit = 20): array {
    $rows = array_values(array_filter(ai_api_redeem_records_all(), fn($row) => (int)($row['user_id'] ?? 0) === $userId));
    usort($rows, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return array_map('ai_api_public_redeem_record', array_slice($rows, 0, $limit));
}

function ai_api_redeem_code_lock_handle() {
    if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0777, true);
    $handle = @fopen(DATA_DIR . '/ai_api_redeem_codes.lock', 'c');
    if ($handle && function_exists('flock')) @flock($handle, LOCK_EX);
    return $handle;
}

function ai_api_redeem_code_unlock($handle): void {
    if ($handle) {
        if (function_exists('flock')) @flock($handle, LOCK_UN);
        @fclose($handle);
    }
}

function ai_api_create_redeem_codes(int $count, int $tokens, array $options = []): array {
    $count = max(1, min(500, $count));
    $tokens = max(1, $tokens);
    $prefix = ai_api_normalize_redeem_code((string)($options['prefix'] ?? 'AIF')) ?: 'AIF';
    $title = mb_substr(trim((string)($options['title'] ?? 'Token 兑换码')), 0, 80, 'UTF-8') ?: 'Token 兑换码';
    $note = mb_substr(trim((string)($options['note'] ?? '')), 0, 300, 'UTF-8');
    $maxUses = max(1, (int)($options['max_uses'] ?? 1));
    $perUserLimit = max(1, (int)($options['per_user_limit'] ?? 1));
    $startsAt = trim((string)($options['starts_at'] ?? ''));
    $expiresAt = trim((string)($options['expires_at'] ?? ''));
    $createdBy = (string)($options['created_by'] ?? 'admin');
    $batchId = mb_substr(trim((string)($options['batch_id'] ?? '')), 0, 80, 'UTF-8');
    if ($batchId === '') $batchId = 'batch_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
    if ($expiresAt !== '' && strtotime($expiresAt) === false) throw new RuntimeException('过期时间格式不正确。');
    if ($startsAt !== '' && strtotime($startsAt) === false) throw new RuntimeException('生效时间格式不正确。');

    $lock = ai_api_redeem_code_lock_handle();
    try {
        $rows = array_map('ai_api_normalize_redeem_code_row', ai_api_redeem_codes_all());
        $existing = [];
        foreach ($rows as $row) if (!empty($row['code_hash'])) $existing[$row['code_hash']] = true;
        $plainCodes = [];
        $now = ai_api_now();
        $nextId = next_id($rows);
        for ($i = 0; $i < $count; $i++) {
            $plain = '';
            $hash = '';
            for ($try = 0; $try < 20; $try++) {
                $plain = ai_api_generate_redeem_code_plain($prefix);
                $hash = ai_api_redeem_code_hash($plain);
                if (!isset($existing[$hash])) break;
            }
            if ($plain === '' || $hash === '' || isset($existing[$hash])) throw new RuntimeException('兑换码生成冲突，请重试。');
            $existing[$hash] = true;
            $plainCodes[] = $plain;
            $rows[] = [
                'id' => $nextId++,
                'code_hash' => $hash,
                'code_preview' => ai_api_redeem_code_preview($plain),
                'title' => $title,
                'tokens' => $tokens,
                'status' => 'active',
                'max_uses' => $maxUses,
                'used_count' => 0,
                'per_user_limit' => $perUserLimit,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'batch_id' => $batchId,
                'note' => $note,
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
                'last_used_at' => '',
            ];
        }
        ai_api_save_redeem_codes($rows);
        return ['codes' => $plainCodes, 'batch_id' => $batchId, 'count' => count($plainCodes), 'tokens' => $tokens];
    } finally {
        ai_api_redeem_code_unlock($lock);
    }
}

function ai_api_redeem_code(int $userId, string $code, array $meta = []): array {
    $normalized = ai_api_normalize_redeem_code($code);
    if ($normalized === '' || strlen($normalized) < 8) throw new RuntimeException('请输入有效的兑换码。');
    $hash = ai_api_redeem_code_hash($normalized);
    $lock = ai_api_redeem_code_lock_handle();
    try {
        $codes = array_map('ai_api_normalize_redeem_code_row', ai_api_redeem_codes_all());
        $records = ai_api_redeem_records_all();
        $idx = -1;
        foreach ($codes as $i => $row) {
            if (($row['code_hash'] ?? '') === $hash) { $idx = $i; break; }
        }
        if ($idx < 0) throw new RuntimeException('兑换码不存在或已失效。');
        $row = $codes[$idx];
        $nowTs = time();
        if (($row['status'] ?? 'active') !== 'active') throw new RuntimeException('兑换码当前不可用。');
        if ((int)($row['tokens'] ?? 0) <= 0) throw new RuntimeException('兑换码额度配置不正确。');
        if (($row['starts_at'] ?? '') !== '' && (strtotime((string)$row['starts_at']) ?: 0) > $nowTs) throw new RuntimeException('兑换码尚未到生效时间。');
        if (($row['expires_at'] ?? '') !== '' && (strtotime((string)$row['expires_at']) ?: 0) < $nowTs) throw new RuntimeException('兑换码已过期。');
        $maxUses = max(0, (int)($row['max_uses'] ?? 1));
        $usedCount = max(0, (int)($row['used_count'] ?? 0));
        if ($maxUses > 0 && $usedCount >= $maxUses) throw new RuntimeException('兑换码已被领完。');
        $perUserLimit = max(1, (int)($row['per_user_limit'] ?? 1));
        $userUses = 0;
        foreach ($records as $record) {
            if ((int)($record['code_id'] ?? 0) === (int)$row['id'] && (int)($record['user_id'] ?? 0) === $userId) $userUses++;
        }
        if ($userUses >= $perUserLimit) throw new RuntimeException('你已兑换过该兑换码。');

        $tokens = max(1, (int)$row['tokens']);
        $wallet = ai_api_update_wallet($userId, $tokens, 'redeem_code', [
            'code_id' => (int)$row['id'],
            'code_preview' => (string)$row['code_preview'],
            'title' => (string)$row['title'],
            'batch_id' => (string)$row['batch_id'],
        ]);
        $now = ai_api_now();
        $codes[$idx]['used_count'] = $usedCount + 1;
        $codes[$idx]['last_used_at'] = $now;
        $codes[$idx]['updated_at'] = $now;
        if ($maxUses > 0 && (int)$codes[$idx]['used_count'] >= $maxUses) $codes[$idx]['status'] = 'depleted';
        $record = [
            'id' => next_id($records),
            'code_id' => (int)$row['id'],
            'code_preview' => (string)$row['code_preview'],
            'title' => (string)$row['title'],
            'tokens' => $tokens,
            'user_id' => $userId,
            'ip' => (string)($meta['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? '')),
            'user_agent' => mb_substr((string)($meta['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 220, 'UTF-8'),
            'created_at' => $now,
        ];
        $records[] = $record;
        ai_api_save_redeem_codes($codes);
        ai_api_save_redeem_records($records);
        try { add_user_log($userId, '兑换 Token', (string)$row['title'] . ' +' . $tokens); } catch (Throwable $e) {}
        if (function_exists('create_notification')) {
            try { create_notification($userId, '兑换成功', '兑换码已到账 +' . number_format($tokens) . ' Token。', 'ai_api', 'console.html'); } catch (Throwable $e) {}
        }
        return ['wallet' => $wallet, 'record' => ai_api_public_redeem_record($record), 'code' => ai_api_redeem_code_public($codes[$idx])];
    } finally {
        ai_api_redeem_code_unlock($lock);
    }
}

function ai_api_admin_redeem_summary(): array {
    $codes = array_map('ai_api_normalize_redeem_code_row', ai_api_redeem_codes_all());
    $records = ai_api_redeem_records_all();
    $now = time();
    $active = 0; $unused = 0; $expired = 0; $depleted = 0; $tokensIssued = 0;
    foreach ($codes as $row) {
        $isExpired = ((string)($row['expires_at'] ?? '') !== '' && (strtotime((string)$row['expires_at']) ?: 0) < $now);
        if ($isExpired) $expired++;
        if (($row['status'] ?? '') === 'depleted') $depleted++;
        if (($row['status'] ?? 'active') === 'active' && !$isExpired) $active++;
        if ((int)($row['used_count'] ?? 0) === 0) $unused++;
    }
    foreach ($records as $record) $tokensIssued += max(0, (int)($record['tokens'] ?? 0));
    return [
        'total_codes' => count($codes),
        'active_codes' => $active,
        'unused_codes' => $unused,
        'expired_codes' => $expired,
        'depleted_codes' => $depleted,
        'redeem_records' => count($records),
        'redeem_tokens_issued' => $tokensIssued,
    ];
}

function ai_api_orders_all(): array { return ai_api_store_read(AI_API_ORDERS_FILE); }
function ai_api_save_orders(array $rows): void { ai_api_store_write(AI_API_ORDERS_FILE, $rows); }

function ai_api_user_orders(int $userId, int $limit = 20): array {
    $rows = array_values(array_filter(ai_api_orders_all(), fn($row) => (int)($row['user_id'] ?? 0) === $userId));
    usort($rows, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return array_map('ai_api_public_order', array_slice($rows, 0, $limit));
}

function ai_api_public_order(array $row): array {
    $orderNo = (string)($row['order_no'] ?? '');
    $status = (string)($row['status'] ?? 'pending');
    $price = (float)($row['price'] ?? 0);
    $safe = $row;
    if ($orderNo !== '' && $status === 'pending' && $price > 0) {
        $safe['pay_url'] = ai_api_payment_url($orderNo);
    }
    if (!isset($safe['payment_type']) || ai_api_normalize_payment_type((string)$safe['payment_type']) === '') {
        $safe['payment_type'] = 'alipay';
    }
    return $safe;
}

function ai_api_has_claimed_package(int $userId, string $packageId): bool {
    foreach (ai_api_store_read(AI_API_LEDGER_FILE) as $row) {
        if ((int)($row['user_id'] ?? 0) !== $userId) continue;
        $meta = (array)($row['meta'] ?? []);
        if (($meta['package_id'] ?? '') === $packageId && ($row['type'] ?? '') === 'package_grant') return true;
    }
    return false;
}


function ai_api_package_grant_meta(array $pkg, string $packageId, string $orderNo = '', array $extra = []): array {
    $kind = strtolower(trim((string)($pkg['kind'] ?? 'topup'))) ?: 'topup';
    $periodDays = max(0, (int)($pkg['period_days'] ?? 0));
    $isSubscription = ($kind === 'subscription' || $periodDays > 0);
    $meta = [
        'package_id' => $packageId,
        'title' => (string)($pkg['title'] ?? $packageId),
        'package_kind' => $kind,
        'subscription' => $isSubscription,
        'period_days' => $periodDays,
    ];
    if ($orderNo !== '') $meta['order_no'] = $orderNo;
    if ($isSubscription) {
        $meta['subscription_started_at'] = ai_api_now();
        $meta['subscription_until'] = date('Y-m-d H:i:s', time() + max(1, $periodDays) * 86400);
    }
    return array_merge($meta, $extra);
}

function ai_api_user_subscriptions(int $userId): array {
    $items = [];
    foreach (ai_api_store_read(AI_API_LEDGER_FILE) as $row) {
        if ((int)($row['user_id'] ?? 0) !== $userId) continue;
        if (($row['type'] ?? '') !== 'package_grant') continue;
        $meta = (array)($row['meta'] ?? []);
        if (empty($meta['subscription'])) continue;
        $until = (string)($meta['subscription_until'] ?? '');
        $items[] = [
            'package_id' => (string)($meta['package_id'] ?? ''),
            'title' => (string)($meta['title'] ?? ''),
            'tokens' => max(0, (int)($row['delta_tokens'] ?? 0)),
            'period_days' => max(0, (int)($meta['period_days'] ?? 0)),
            'started_at' => (string)($meta['subscription_started_at'] ?? $row['created_at'] ?? ''),
            'active_until' => $until,
            'is_active' => $until !== '' && strtotime($until) >= time(),
            'created_at' => (string)($row['created_at'] ?? ''),
        ];
    }
    usort($items, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return $items;
}

function ai_api_payment_methods(): array {
    return [
        ['key' => 'alipay', 'label' => '支付宝', 'icon' => 'credit-card'],
        ['key' => 'wxpay', 'label' => '微信支付', 'icon' => 'weixin'],
        ['key' => 'qqpay', 'label' => 'QQ钱包', 'icon' => 'qq'],
    ];
}

function ai_api_normalize_payment_type(string $type): string {
    $type = strtolower(trim($type));
    foreach (ai_api_payment_methods() as $item) {
        if (($item['key'] ?? '') === $type) return $type;
    }
    return '';
}

function ai_api_payment_url(string $orderNo): string {
    return sponsor_absolute_url('ai_api_pay_submit.php?out_trade_no=' . rawurlencode($orderNo));
}

function ai_api_find_order_index_by_no(array $rows, string $orderNo): int {
    foreach ($rows as $i => $row) {
        if ((string)($row['order_no'] ?? '') === $orderNo) return $i;
    }
    return -1;
}

function ai_api_find_order_by_no(string $orderNo): ?array {
    $orderNo = trim($orderNo);
    if ($orderNo === '') return null;
    foreach (ai_api_orders_all() as $row) {
        if ((string)($row['order_no'] ?? '') === $orderNo) return $row;
    }
    return null;
}

function ai_api_mark_order_paid_from_callback(array $callback, string $source = 'notify'): array {
    $orderNo = trim((string)($callback['out_trade_no'] ?? ''));
    if ($orderNo === '') throw new RuntimeException('缺少订单号');
    $rows = ai_api_orders_all();
    $idx = ai_api_find_order_index_by_no($rows, $orderNo);
    if ($idx < 0) throw new RuntimeException('AI API 订单不存在');
    $row = $rows[$idx];
    if (($row['status'] ?? '') === 'paid') return ai_api_public_order($row);
    if (($row['status'] ?? '') !== 'pending') throw new RuntimeException('订单状态不允许支付');
    if (($callback['trade_status'] ?? '') !== 'TRADE_SUCCESS') throw new RuntimeException('支付未成功');

    $expected = round((float)($row['price'] ?? 0), 2);
    $paid = array_key_exists('money', $callback) ? round((float)$callback['money'], 2) : $expected;
    if ($expected > 0 && $paid + 0.01 < $expected) {
        throw new RuntimeException('支付金额不足');
    }

    $now = ai_api_now();
    $rows[$idx]['status'] = 'paid';
    $rows[$idx]['trade_no'] = trim((string)($callback['trade_no'] ?? ''));
    $rows[$idx]['payment_type'] = ai_api_normalize_payment_type((string)($callback['type'] ?? ($row['payment_type'] ?? 'alipay'))) ?: (string)($row['payment_type'] ?? 'alipay');
    $rows[$idx]['paid_amount'] = number_format($paid, 2, '.', '');
    $rows[$idx]['paid_at'] = $now;
    $rows[$idx]['updated_at'] = $now;
    $rows[$idx]['paid_source'] = $source;
    $rows[$idx]['note'] = '在线支付成功，Token 已自动到账';
    ai_api_save_orders($rows);

    $pkg = ai_api_find_package((string)($row['package_id'] ?? '')) ?: $row;
    $wallet = ai_api_update_wallet((int)$row['user_id'], (int)$row['tokens'], 'package_grant', ai_api_package_grant_meta($pkg, (string)($row['package_id'] ?? ''), $orderNo, [
        'payment_type' => (string)($rows[$idx]['payment_type'] ?? ''),
        'trade_no' => (string)($rows[$idx]['trade_no'] ?? ''),
        'source' => $source,
    ]));
    add_user_log((int)$row['user_id'], 'AI API Token 包到账', $orderNo . ' +' . (int)$row['tokens']);
    if (function_exists('create_notification')) {
        create_notification((int)$row['user_id'], 'AI API Token 已到账', (string)($row['title'] ?? 'Token 套餐') . ' 已支付成功，+' . (int)$row['tokens'] . ' tokens。', 'ai_api', 'ai_api_console.html', ['order_no' => $orderNo, 'tokens' => (int)$row['tokens']]);
    }
    $rows[$idx]['wallet'] = $wallet;
    return ai_api_public_order($rows[$idx]);
}

function ai_api_refresh_order_from_gateway(string $orderNo): ?array {
    $orderNo = trim($orderNo);
    if ($orderNo === '') return null;
    $local = ai_api_find_order_by_no($orderNo);
    if (!$local) return null;
    if (($local['status'] ?? '') === 'paid') return ai_api_public_order($local);
    try {
        $result = sponsor_gateway()->queryOrder($orderNo);
    } catch (Throwable $e) {
        return ai_api_public_order($local);
    }
    $code = (string)($result['code'] ?? '');
    $status = (string)($result['status'] ?? '');
    if (($code === '1' || $status === '1') && $status === '1') {
        return ai_api_mark_order_paid_from_callback([
            'out_trade_no' => $orderNo,
            'trade_no' => (string)($result['trade_no'] ?? ''),
            'trade_status' => 'TRADE_SUCCESS',
            'type' => (string)($result['type'] ?? ($local['payment_type'] ?? 'alipay')),
            'money' => (string)($result['money'] ?? ($local['price'] ?? '0.00')),
        ], 'query');
    }
    return ai_api_public_order($local);
}
function ai_api_find_package(string $packageId): ?array {
    foreach (ai_api_packages(true) as $pkg) if (($pkg['id'] ?? '') === $packageId) return $pkg;
    return null;
}

function ai_api_create_order(int $userId, string $packageId, string $paymentType = 'alipay'): array {
    $pkg = ai_api_find_package($packageId);
    if (!$pkg || empty($pkg['enabled'])) throw new RuntimeException('套餐不存在或未上架');
    if (!empty($pkg['once_per_user']) && ai_api_has_claimed_package($userId, $packageId)) throw new RuntimeException('该套餐每个账号只能领取一次');
    if (!empty($pkg['auto_grant']) || (float)($pkg['price'] ?? 0) <= 0) {
        $wallet = ai_api_update_wallet($userId, (int)$pkg['tokens'], 'package_grant', ai_api_package_grant_meta($pkg, $packageId, '', ['auto_grant' => true]));
        add_user_log($userId, 'AI API Token 包到账', $pkg['title'] . ' +' . (int)$pkg['tokens']);
        return ['status' => 'paid', 'wallet' => $wallet, 'package' => $pkg, 'order' => null, 'pay_url' => ''];
    }
    $paymentType = ai_api_normalize_payment_type($paymentType) ?: 'alipay';
    $rows = ai_api_orders_all();
    $order = [
        'id' => next_id($rows),
        'order_no' => 'AIP' . date('YmdHis') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
        'user_id' => $userId,
        'package_id' => $packageId,
        'title' => $pkg['title'],
        'tokens' => (int)$pkg['tokens'],
        'price' => (float)$pkg['price'],
        'currency' => (string)$pkg['currency'],
        'package_kind' => (string)($pkg['kind'] ?? 'topup'),
        'period_days' => max(0, (int)($pkg['period_days'] ?? 0)),
        'subscription_until' => ((string)($pkg['kind'] ?? '') === 'subscription' || (int)($pkg['period_days'] ?? 0) > 0) ? date('Y-m-d H:i:s', time() + max(1, (int)($pkg['period_days'] ?? 30)) * 86400) : '',
        'payment_type' => $paymentType,
        'status' => 'pending',
        'created_at' => ai_api_now(),
        'updated_at' => ai_api_now(),
        'paid_at' => '',
        'trade_no' => '',
        'note' => '请在支付页面完成付款，支付成功后 Token 自动到账',
    ];
    $order['pay_url'] = ai_api_payment_url($order['order_no']);
    $rows[] = $order;
    ai_api_save_orders($rows);
    add_user_log($userId, '创建 AI API Token 包订单', $order['order_no'] . ' ' . $pkg['title']);
    return ['status' => 'pending', 'wallet' => ai_api_wallet_for_user($userId), 'package' => $pkg, 'order' => ai_api_public_order($order), 'pay_url' => $order['pay_url']];
}
function ai_api_approve_order(int $orderId, int $adminId = 0): array {
    $rows = ai_api_orders_all();
    foreach ($rows as &$row) {
        if ((int)($row['id'] ?? 0) !== $orderId) continue;
        if (($row['status'] ?? '') === 'paid') return $row;
        if (($row['status'] ?? '') !== 'pending') throw new RuntimeException('订单不是待确认状态');
        $row['status'] = 'paid';
        $row['paid_at'] = ai_api_now();
        $row['approved_by'] = $adminId;
        $pkg = ai_api_find_package((string)($row['package_id'] ?? '')) ?: $row;
        $wallet = ai_api_update_wallet((int)$row['user_id'], (int)$row['tokens'], 'package_grant', ai_api_package_grant_meta($pkg, (string)($row['package_id'] ?? ''), (string)($row['order_no'] ?? ''), ['approved_by' => $adminId]));
        ai_api_save_orders($rows);
        add_user_log((int)$row['user_id'], 'AI API Token 包到账', $row['order_no'] . ' +' . (int)$row['tokens']);
        $row['wallet'] = $wallet;
        return $row;
    }
    unset($row);
    throw new RuntimeException('订单不存在');
}

function ai_api_reject_order(int $orderId, string $note = ''): array {
    $rows = ai_api_orders_all();
    foreach ($rows as &$row) {
        if ((int)($row['id'] ?? 0) !== $orderId) continue;
        if (($row['status'] ?? '') !== 'pending') throw new RuntimeException('只能取消待确认订单');
        $row['status'] = 'cancelled';
        $row['cancelled_at'] = ai_api_now();
        $row['note'] = $note !== '' ? $note : '管理员已取消订单';
        ai_api_save_orders($rows);
        return $row;
    }
    unset($row);
    throw new RuntimeException('订单不存在');
}

function ai_api_load_model_config(): array {
    $cfg = [];
    $cfgPath = __DIR__ . '/config/spark_lite.php';
    if (is_file($cfgPath)) {
        $loaded = include $cfgPath;
        if (is_array($loaded)) $cfg = $loaded;
    }
    $privatePath = DATA_DIR . '/ai_private.php';
    if (is_file($privatePath)) {
        $private = include $privatePath;
        if (is_array($private) && !empty($private['models']) && is_array($private['models'])) {
            foreach ($private['models'] as $key => $secretCfg) {
                if (!isset($cfg['models'][$key]) || !is_array($cfg['models'][$key])) $cfg['models'][$key] = [];
                if (is_array($secretCfg)) $cfg['models'][$key] = array_merge($cfg['models'][$key], $secretCfg);
            }
        }
    }
    $cfg['models'] = is_array($cfg['models'] ?? null) ? $cfg['models'] : [];
    foreach ($cfg['models'] as $key => &$modelRow) {
        if (!is_array($modelRow)) continue;
        if (ai_api_model_is_zero_token((string)$key, $modelRow)) {
            $modelRow['zero_token'] = true;
            $modelRow['no_token_charge'] = true;
            $modelRow['is_free'] = true;
            $modelRow['free'] = true;
            $modelRow['api_token_multiplier'] = 0;
            $tags = array_values(array_unique(array_merge(array_map('strval', (array)($modelRow['tags'] ?? [])), ['free', 'zero-token'])));
            $modelRow['tags'] = $tags;
        }
    }
    unset($modelRow);
    return $cfg;
}


function ai_api_provider_private_config(): array {
    $secrets = [];
    $path = DATA_DIR . '/ai_providers_private.php';
    if (is_file($path)) {
        $loaded = include $path;
        if (is_array($loaded)) $secrets = $loaded;
    }
    $envMap = [
        'moonshot' => ['MOONSHOT_API_KEY', 'KIMI_API_KEY'],
        'bailian' => ['DASHSCOPE_API_KEY', 'BAILIAN_API_KEY'],
        'github' => ['GITHUB_TOKEN', 'GITHUB_MODELS_TOKEN'],
        'siliconflow' => ['SILICONFLOW_API_KEY'],
        'openrouter' => ['OPENROUTER_API_KEY', 'OPENROUTER_TOKEN'],
        'mimo' => ['MIMO_TOKEN_PLAN_API_KEY', 'MIMO_API_KEY', 'XIAOMI_MIMO_API_KEY', 'MIMOTOKENPLAN_API_KEY'],
        'mimo2' => ['MIMO2_API_KEY', 'MIMO_V2_API_KEY', 'XIAOMI_MIMO2_API_KEY', 'XIAOMI_MIMO_V2_API_KEY', 'MIMO_TOKEN_PLAN_2_API_KEY'],
    ];
    foreach ($envMap as $provider => $names) {
        foreach ($names as $name) {
            $value = getenv($name);
            if ($value !== false && trim((string)$value) !== '') {
                if (!isset($secrets[$provider]) || !is_array($secrets[$provider])) $secrets[$provider] = [];
                if (empty($secrets[$provider]['api_key'])) $secrets[$provider]['api_key'] = trim((string)$value);
            }
        }
    }
    return $secrets;
}

function ai_api_provider_key(string $provider): string {
    // 【修改｜兼容性优化｜风险等级：低】统一 provider 别名映射，避免 kimi/dashscope/sf/or 前缀解析不一致。
    $provider = ai_api_normalize_provider_name($provider);
    $secrets = ai_api_provider_private_config();
    $row = (array)($secrets[$provider] ?? []);
    return trim((string)($row['api_key'] ?? $row['token'] ?? ''));
}



/**
 * 【新增｜安全加固/兼容性优化｜风险等级：低】
 * 统一规范上游 provider 标识，便于新增渠道和环境变量覆盖，不改变原有公开 API 契约。
 */
function ai_api_normalize_provider_name(string $provider): string {
    $provider = strtolower(trim($provider));
    if ($provider === 'kimi') $provider = 'moonshot';
    if ($provider === 'dashscope') $provider = 'bailian';
    if ($provider === 'sf') $provider = 'siliconflow';
    if ($provider === 'or' || $provider === 'open-router') $provider = 'openrouter';
    if (in_array($provider, ['mimo2', 'mimo-2', 'mimo_v2', 'mimo-v2-key', 'mimo-key2', 'mimo2key', 'mimo-key-2', 'xiaomi-mimo2', 'xiaomi-mimo-key2', 'mi-mimo2'], true)) $provider = 'mimo2';
    if (in_array($provider, ['mi', 'mimo', 'xiaomi', 'xiaomimimo', 'mimotoken', 'mimotokenplan', 'tokenplan', 'mi-mimo', 'xiaomi-mimo'], true)) $provider = 'mimo';
    return $provider;
}

/**
 * 【新增｜兼容性优化｜风险等级：低】
 * 支持通过环境变量修正/切换上游 Base URL，解决各平台地区域名、全球/中国区端点切换导致的对接失败。
 * 仅影响上游请求地址，不修改站点对外 OpenAI 兼容 API 契约。
 */
function ai_api_provider_base_url(string $provider, string $fallback): string {
    $provider = ai_api_normalize_provider_name($provider);
    $envMap = [
        'moonshot' => ['MOONSHOT_API_BASE', 'KIMI_API_BASE', 'KIMI_BASE_URL'],
        'bailian' => ['DASHSCOPE_API_BASE', 'BAILIAN_API_BASE', 'DASHSCOPE_BASE_URL'],
        'github' => ['GITHUB_MODELS_API_BASE', 'GITHUB_MODELS_BASE_URL'],
        'siliconflow' => ['SILICONFLOW_API_BASE', 'SILICONFLOW_BASE_URL'],
        'openrouter' => ['OPENROUTER_API_BASE', 'OPENROUTER_BASE_URL'],
        'mimo' => ['MIMO_TOKEN_PLAN_API_BASE', 'MIMO_API_BASE', 'XIAOMI_MIMO_API_BASE', 'MIMOTOKENPLAN_API_BASE'],
        'mimo2' => ['MIMO2_API_BASE', 'MIMO_V2_API_BASE', 'XIAOMI_MIMO2_API_BASE', 'XIAOMI_MIMO_V2_API_BASE', 'MIMO_TOKEN_PLAN_2_API_BASE', 'MIMO_TOKEN_PLAN_API_BASE', 'MIMO_API_BASE', 'XIAOMI_MIMO_API_BASE'],
    ];
    foreach ($envMap[$provider] ?? [] as $name) {
        $value = getenv($name);
        if ($value !== false && trim((string)$value) !== '') return rtrim(trim((string)$value), '/');
    }
    return rtrim($fallback, '/');
}

/**
 * 【新增｜兼容性优化｜风险等级：低】
 * 为特定上游补齐官方要求/推荐的请求头。仅用于出站请求，不暴露或改变客户端入站协议。
 */
function ai_api_provider_default_headers(string $provider): array {
    $provider = ai_api_normalize_provider_name($provider);
    if ($provider === 'github') {
        return [
            'X-GitHub-Api-Version' => '2026-03-10',
        ];
    }
    if ($provider === 'openrouter') {
        return [
            'HTTP-Referer' => '{origin}',
            'X-OpenRouter-Title' => 'AIF AI API Gateway',
        ];
    }
    return [];
}

/**
 * 【新增｜兼容性优化｜风险等级：低】
 * 不同 OpenAI-Compatible 上游对可选参数的兼容度不同。这里仅删除已知会造成 400/422 的“非核心可选字段”，
 * 不改动 model/messages/stream/temperature/top_p/max_tokens/tools 等核心契约字段。
 */
function ai_api_sanitize_payload_for_upstream(array $payload, array $model): array {
    $provider = ai_api_normalize_provider_name((string)($model['provider_key'] ?? $model['provider_id'] ?? $model['provider'] ?? ''));
    $drop = array_map('strval', (array)($model['drop_request_fields'] ?? []));

    if ($provider === 'github') {
        // GitHub Models REST inference接口对 OpenAI 扩展字段较严格；删除会导致兼容层 422 的可选字段。
        $drop = array_merge($drop, ['logprobs', 'top_logprobs', 'user']);
    }
    if ($provider === 'moonshot') {
        // Kimi/Moonshot 对部分 OpenAI 统计/调试字段不保证兼容，保留核心生成参数。
        $drop = array_merge($drop, ['logprobs', 'top_logprobs']);
    }
    if ($provider === 'bailian') {
        // 百炼 OpenAI 兼容端点按区域区分，保守移除非必要调试字段，避免因模型不支持而失败。
        $drop = array_merge($drop, ['logprobs', 'top_logprobs']);
    }
    if (in_array($provider, ['mimo', 'mimo2'], true)) {
        // 小米 MiMo Token Plan 使用 OpenAI-Compatible 协议，保守移除非核心调试字段，提升第三方 SDK 兼容性。
        $drop = array_merge($drop, ['logprobs', 'top_logprobs']);
    }

    foreach (array_values(array_unique($drop)) as $field) {
        if ($field !== '') unset($payload[$field]);
    }
    return $payload;
}

function ai_api_provider_registry(): array {
    $path = __DIR__ . '/config/ai_model_registry.php';
    $loaded = is_file($path) ? include $path : [];
    return is_array($loaded) ? $loaded : ['providers' => []];
}

function ai_api_openrouter_price_is_zero($value): bool {
    if ($value === null || $value === '') return false;
    return abs((float)$value) < 0.0000000001;
}

function ai_api_openrouter_model_row_is_free(array $model): bool {
    if (!empty($model['free'])) return true;
    $id = strtolower(trim((string)($model['id'] ?? '')));
    $tags = array_map('strtolower', array_map('strval', (array)($model['tags'] ?? [])));
    if ($id === 'openrouter/free' || str_ends_with($id, ':free') || in_array('free', $tags, true)) return true;
    $pricing = (array)($model['pricing'] ?? []);
    return ai_api_openrouter_price_is_zero($pricing['prompt'] ?? null)
        && ai_api_openrouter_price_is_zero($pricing['completion'] ?? null);
}

function ai_api_openrouter_normalize_live_model(array $model): array {
    $id = trim((string)($model['id'] ?? ''));
    $pricing = (array)($model['pricing'] ?? []);
    $arch = (array)($model['architecture'] ?? []);
    $topProvider = (array)($model['top_provider'] ?? []);
    $supported = array_map('strval', (array)($model['supported_parameters'] ?? []));
    $modalities = array_map('strval', (array)($arch['input_modalities'] ?? []));
    $tags = ['openrouter', 'free', 'live-catalog'];

    if (in_array('image', $modalities, true)) $tags[] = 'vision';
    if (in_array('audio', $modalities, true)) $tags[] = 'audio';
    if (in_array('video', $modalities, true)) $tags[] = 'video';
    if (in_array('tools', $supported, true)) $tags[] = 'tools';
    if (in_array('structured_outputs', $supported, true)) $tags[] = 'json';

    return [
        'id' => $id,
        'label' => trim((string)($model['name'] ?? $id)) ?: $id,
        'tags' => array_values(array_unique($tags)),
        'thinking' => in_array('reasoning', $supported, true)
            || in_array('include_reasoning', $supported, true)
            || (bool)preg_match('/(thinking|reason|r1|qwq|o3|o4|gpt-oss|nemotron)/i', $id),
        'vision' => in_array('image', $modalities, true),
        'context' => (string)($model['context_length'] ?? $topProvider['context_length'] ?? 'live'),
        'context_length' => (int)($model['context_length'] ?? $topProvider['context_length'] ?? 0),
        'max_tokens' => (int)($topProvider['max_completion_tokens'] ?? 4096),
        'supported_parameters' => $supported,
        'pricing' => [
            'prompt' => (string)($pricing['prompt'] ?? '0'),
            'completion' => (string)($pricing['completion'] ?? '0'),
        ],
        'free' => true,
    ];
}

function ai_api_openrouter_cache_path(): string {
    return DATA_DIR . '/openrouter_free_models_cache.json';
}

function ai_api_curl_get_json(string $url, array $headers = [], int $timeout = 12, bool $verifySsl = true): array {
    if (!function_exists('curl_init')) return ['ok' => false, 'status' => 0, 'data' => null, 'error' => '当前 PHP 环境未开启 cURL 扩展'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers ?: ['Accept: application/json'],
        CURLOPT_TIMEOUT => max(5, $timeout),
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        CURLOPT_HEADER => false,
        // 【新增｜兼容性优化｜风险等级：低】部分上游会在边缘层 301/302 到区域网关，允许安全跟随 HTTPS 跳转。
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = is_string($body) && $body !== '' ? json_decode($body, true) : null;
    return [
        'ok' => $status >= 200 && $status < 300 && is_array($data),
        'status' => $status,
        'data' => is_array($data) ? $data : null,
        'error' => $err,
    ];
}

function ai_api_openrouter_read_cache(bool $allowExpired = false): array {
    $path = ai_api_openrouter_cache_path();
    if (!is_file($path)) return [];
    $json = json_decode((string)@file_get_contents($path), true);
    if (!is_array($json)) return [];
    $expiresAt = (int)($json['expires_at'] ?? 0);
    if (!$allowExpired && $expiresAt > 0 && $expiresAt < time()) return [];
    $rows = (array)($json['models'] ?? []);
    return array_values(array_filter($rows, fn($row) => is_array($row) && !empty($row['id'])));
}

function ai_api_openrouter_write_cache(array $models, int $ttl): void {
    if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0777, true);
    $payload = [
        'updated_at' => ai_api_now(),
        'expires_at' => time() + max(300, $ttl),
        'source' => 'https://openrouter.ai/api/v1/models',
        'models' => array_values($models),
    ];
    @file_put_contents(ai_api_openrouter_cache_path(), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
}

function ai_api_openrouter_fallback_free_models(array $cfg): array {
    $rows = [];
    foreach ((array)($cfg['models'] ?? []) as $model) {
        if (!is_array($model)) continue;
        if (ai_api_openrouter_model_row_is_free($model)) $rows[] = $model + ['free' => true];
    }
    return $rows;
}

function ai_api_openrouter_free_models(array $cfg, bool $forceRefresh = false): array {
    $ttl = (int)($cfg['cache_ttl_seconds'] ?? 21600);
    if (!$forceRefresh) {
        $cached = ai_api_openrouter_read_cache(false);
        if ($cached) return $cached;
    }

    $base = rtrim((string)($cfg['base_url'] ?? 'https://openrouter.ai/api/v1'), '/');
    $path = (string)($cfg['models_path'] ?? '/models');
    $url = $base . $path;
    // Use output_modalities=all so the catalog contains every free text-output model,
    // including multimodal-input models. Non-text output models are filtered out below.
    $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query(['output_modalities' => 'all']);

    $headers = [
        'Accept: application/json',
        'HTTP-Referer: ' . ai_api_request_origin(),
        'X-OpenRouter-Title: KingDungeon AI API Gateway',
    ];
    $key = ai_api_provider_key('openrouter');
    if ($key !== '') $headers[] = 'Authorization: Bearer ' . $key;

    $resp = ai_api_curl_get_json($url, $headers, 12, true);
    $rows = is_array($resp['data']['data'] ?? null) ? $resp['data']['data'] : [];
    $models = [];
    foreach ($rows as $row) {
        if (!is_array($row) || empty($row['id'])) continue;
        $arch = (array)($row['architecture'] ?? []);
        $outputs = array_map('strval', (array)($arch['output_modalities'] ?? []));
        if ($outputs && !in_array('text', $outputs, true)) continue;
        if (!ai_api_openrouter_model_row_is_free($row)) continue;
        $models[] = ai_api_openrouter_normalize_live_model($row);
    }

    if ($models) {
        ai_api_openrouter_write_cache($models, $ttl);
        return $models;
    }

    $expired = ai_api_openrouter_read_cache(true);
    if ($expired) return $expired;

    return ai_api_openrouter_fallback_free_models($cfg);
}

function ai_api_openrouter_model_id_is_allowed_free(string $modelId, array $cfg): bool {
    $id = strtolower(trim($modelId));
    if ($id === 'openrouter/free' || str_ends_with($id, ':free')) return true;
    foreach (ai_api_openrouter_free_models($cfg, false) as $row) {
        if (strtolower((string)($row['id'] ?? '')) === $id) return true;
    }
    return false;
}


/**
 * Normalize common Xiaomi MiMo model aliases so clients can send user-friendly IDs
 * such as mimov2.5pro, mimo-v2.5pro, xiaomi::mimov2.5pro, or MiMo-v2.5-Pro.
 */
function ai_api_canonical_mimo_model_id(string $modelId): string {
    $raw = trim($modelId);
    if ($raw === '') return '';
    $raw = preg_replace('/^(mimo2|mimo-2|mimo_v2|xiaomi-mimo2|xiaomi|xiaomimimo|mi|mimo|mimotoken|mimotokenplan|tokenplan)[\/:_]+/i', '', $raw);
    $compact = strtolower(preg_replace('/[^a-z0-9]+/', '', $raw));
    $map = [
        'mimov25pro' => 'mimo-v2.5-pro',
        'mimov25' => 'mimo-v2.5',
        'mimov2pro' => 'mimo-v2-pro',
        'mimov2omni' => 'mimo-v2-omni',
        'mimov25tts' => 'mimo-v2.5-tts',
        'mimov25ttsvoiceclone' => 'mimo-v2.5-tts-voiceclone',
        'mimov25ttsvoicedesign' => 'mimo-v2.5-tts-voicedesign',
        'mimov2tts' => 'mimo-v2-tts',
        'xiaomimimov25pro' => 'mimo-v2.5-pro',
        'xiaomimimov25' => 'mimo-v2.5',
        'xiaomimimov2pro' => 'mimo-v2-pro',
        'xiaomimimov2omni' => 'mimo-v2-omni',
        'xiaomimimov25tts' => 'mimo-v2.5-tts',
    ];
    return $map[$compact] ?? '';
}


function ai_api_is_mimo2_request(string $requested): bool {
    $raw = strtolower(trim($requested));
    if ($raw === '') return false;
    if (preg_match('/^(mimo2|mimo-2|mimo_v2|mimo-key2|mimo-key-2|xiaomi-mimo2|xiaomi-mimo-key2|mi-mimo2)(::|[\/:_-])/i', $raw)) return true;
    $compact = preg_replace('/[^a-z0-9]+/i', '', $raw);
    return (bool)preg_match('/(key2|keytwo|mimo2)$/i', $compact);
}

function ai_api_with_mimo_identity_guard(array $messages, string $modelId): array {
    if (ai_api_canonical_mimo_model_id($modelId) === '' && stripos($modelId, 'mimo') === false) return $messages;
    $guard = '你正在通过小米 MiMo 模型服务回答。若用户询问你的模型、开发方或身份，只能说明当前请求路由到 Xiaomi MiMo（模型标签 ' . $modelId . '）；不要自称 GLM、Z.ai、智谱、NVIDIA 或其他模型。';
    array_unshift($messages, ['role' => 'system', 'content' => $guard]);
    return $messages;
}


function ai_api_registry_public_models(array $existing = []): array {
    $seen = [];
    foreach ($existing as $row) {
        $seen[strtolower((string)($row['id'] ?? ''))] = true;
    }
    $registry = ai_api_provider_registry();
    $out = [];
    foreach ((array)($registry['providers'] ?? []) as $provider => $cfg) {
        $provider = (string)$provider;
        $prefix = (string)($cfg['prefix'] ?? ($provider . '::'));
        $providerModels = (array)($cfg['models'] ?? []);

        if ($provider === 'openrouter' && !empty($cfg['free_only_catalog']) && !empty($cfg['live_catalog'])) {
            $providerModels = ai_api_openrouter_free_models((array)$cfg, false);
        }

        foreach ($providerModels as $model) {
            if (!is_array($model)) continue;
            $id = trim((string)($model['id'] ?? ''));
            if ($id === '') continue;

            if ($provider === 'openrouter' && !empty($cfg['free_only_catalog']) && !ai_api_openrouter_model_row_is_free($model)) {
                continue;
            }

            if (array_key_exists('supports_chat', $model) && empty($model['supports_chat'])) continue;
            $publicId = $prefix . $id;
            if (isset($seen[strtolower($publicId)])) continue;

            $zeroModel = $model;
            $zeroModel['provider'] = (string)($cfg['label'] ?? $provider);
            if ($provider === 'openrouter' && ai_api_openrouter_model_row_is_free($model)) $zeroModel['free'] = true;
            $isFree = ai_api_model_is_zero_token($publicId, $zeroModel);
            $tags = array_values(array_unique(array_map('strval', (array)($model['tags'] ?? []))));
            if ($isFree && !in_array('zero-token', $tags, true)) $tags[] = 'zero-token';
            $maxTokens = (int)($model['max_tokens'] ?? $model['max_completion_tokens'] ?? 4096);
            if ($maxTokens <= 0) $maxTokens = 4096;

            $out[] = [
                'id' => $publicId,
                'label' => (string)($model['label'] ?? $id),
                'type' => (string)($model['type'] ?? (!empty($model['thinking']) ? 'reasoning' : 'chat')),
                'provider' => (string)($cfg['label'] ?? $provider),
                'model_name' => $id,
                'stream_supported' => true,
                'supports_thinking' => !empty($model['thinking']),
                'supports_image_input' => !empty($model['vision']) || stripos($id, 'vision') !== false || stripos($id, 'k2.5') !== false,
                'max_tokens' => $maxTokens,
                'context_length' => (int)($model['context_length'] ?? (is_numeric($model['context'] ?? '') ? (int)$model['context'] : 0)),
                'token_multiplier' => $isFree ? 0.0 : (float)($model['token_multiplier'] ?? 1.0),
                'aliases' => array_values(array_unique(array_merge([$id], array_map('strval', (array)($model['aliases'] ?? []))))),
                'tags' => $tags,
                'is_free' => $isFree,
                'price_label' => $isFree ? '免Token' : '',
            ];
        }
    }
    return $out;
}


function ai_api_registry_model_catalog_row(array $cfg, string $modelId): array {
    $wanted = trim($modelId);
    $wantedMimo = ai_api_canonical_mimo_model_id($wanted);
    foreach ((array)($cfg['models'] ?? []) as $row) {
        if (!is_array($row)) continue;
        $candidates = [trim((string)($row['id'] ?? ''))];
        foreach ((array)($row['aliases'] ?? []) as $alias) $candidates[] = trim((string)$alias);
        foreach ($candidates as $candidate) {
            if ($candidate === '') continue;
            if (strcasecmp($candidate, $wanted) === 0) return $row;
            if ($wantedMimo !== '' && strcasecmp($candidate, $wantedMimo) === 0) return $row;
            $candidateMimo = ai_api_canonical_mimo_model_id($candidate);
            if ($wantedMimo !== '' && $candidateMimo !== '' && strcasecmp($candidateMimo, $wantedMimo) === 0) return $row;
        }
    }
    return [];
}

function ai_api_parse_provider_model(string $requested, string $defaultProvider = 'mimo'): array {
    $requested = trim($requested);
    if (preg_match('/^(moonshot|kimi|bailian|dashscope|github|siliconflow|sf|openrouter|or|mimo2|mimo-2|mimo_v2|mimo-key2|xiaomi-mimo2|xiaomi-mimo-key2|mi-mimo2|mimo|mi|xiaomi|xiaomimimo|mimotoken|mimotokenplan|tokenplan|mi-mimo|xiaomi-mimo)::(.+)$/i', $requested, $m)) {
        return [ai_api_normalize_provider_name((string)$m[1]), trim((string)$m[2])];
    }
    return [ai_api_is_mimo2_request($requested) ? 'mimo2' : ai_api_normalize_provider_name($defaultProvider), $requested];
}

function ai_api_dynamic_provider_model(string $requested): ?array {
    $requested = trim($requested);
    if ($requested === '' || !preg_match('/^(moonshot|kimi|bailian|dashscope|github|siliconflow|sf|openrouter|or|mimo2|mimo-2|mimo_v2|mimo-key2|xiaomi-mimo2|xiaomi-mimo-key2|mi-mimo2|mimo|mi|xiaomi|xiaomimimo|mimotoken|mimotokenplan|tokenplan|mi-mimo|xiaomi-mimo)::(.+)$/i', $requested, $m)) return null;
    // 【修改｜兼容性优化｜风险等级：低】统一 provider 别名，避免模型广场前缀与网关解析不一致。
    $provider = ai_api_normalize_provider_name((string)$m[1]);
    $modelId = trim($m[2]);
    if (in_array($provider, ['mimo', 'mimo2'], true)) {
        $canonicalMimo = ai_api_canonical_mimo_model_id($modelId);
        if ($canonicalMimo !== '') $modelId = $canonicalMimo;
    }
    if ($modelId === '') return null;

    $registry = ai_api_provider_registry();
    $providers = (array)($registry['providers'] ?? []);
    $cfg = (array)($providers[$provider] ?? []);
    if (!$cfg) return null;
    if ($provider === 'openrouter' && !empty($cfg['free_only_catalog']) && !ai_api_openrouter_model_id_is_allowed_free($modelId, $cfg)) {
        return null;
    }
    $catalogRow = ai_api_registry_model_catalog_row($cfg, $modelId);
    if (in_array($provider, ['mimo', 'mimo2'], true) && !$catalogRow) return null;
    if ($catalogRow && array_key_exists('supports_chat', $catalogRow) && empty($catalogRow['supports_chat'])) return null;
    if (!empty($catalogRow['id'])) $modelId = (string)$catalogRow['id'];

    // 【修改｜兼容性优化｜风险等级：低】支持环境变量覆盖 provider Base URL，便于 Kimi 全球/中国区、百炼新加坡/美国/北京等区域切换。
    $base = ai_api_provider_base_url($provider, (string)($cfg['base_url'] ?? ''));
    $path = (string)($cfg['chat_path'] ?? '/chat/completions');
    if ($base === '') return null;

    $apiKey = ai_api_provider_key($provider);
    $label = (string)($cfg['label'] ?? $provider) . ' · ' . (string)($catalogRow['label'] ?? $modelId);
    $isThinking = !empty($catalogRow['thinking']) || (bool)preg_match('/(thinking|reason|r1|qwq|k2|o3|o4|gpt-5|pro)/i', $modelId);
    $supportsVision = !empty($catalogRow['vision']) || (bool)preg_match('/(vision|image|k2\.5|gpt-4o|gemini|claude|multimodal|omni)/i', $modelId);
    $maxTokens = (int)($catalogRow['max_tokens'] ?? $catalogRow['max_completion_tokens'] ?? 4096);
    if ($maxTokens <= 0) $maxTokens = 4096;
    return [
        'key' => $provider . '::' . $modelId,
        'model' => [
            'enabled' => true,
            'label' => $label,
            'type' => $isThinking ? 'reasoning' : 'chat',
            'protocol' => 'openai_compatible',
            'base_url' => $base,
            'path' => $path,
            'model_name' => $modelId,
            'auth_type' => 'bearer',
            'api_key' => $apiKey,
            // 【新增｜兼容性优化｜风险等级：低】标记 provider，用于出站头部和 payload 参数兼容处理。
            'provider_key' => $provider,
            'accept_header' => (string)($cfg['accept_header'] ?? ($provider === 'github' ? 'application/vnd.github+json' : 'application/json')),
            'drop_request_fields' => (array)($cfg['drop_request_fields'] ?? []),
            'timeout' => 180,
            'temperature' => 0.7,
            'top_p' => 0.9,
            'max_tokens' => $maxTokens,
            'stream_supported' => true,
            'supports_thinking' => $isThinking,
            'supports_image_input' => $supportsVision,
            'provider' => (string)($cfg['label'] ?? $provider),
            'aliases' => [$requested, $modelId],
            'tags' => ($provider === 'openrouter' && !empty($cfg['free_only_catalog'])) ? ['openrouter', 'free', 'zero-token'] : array_values(array_map('strval', (array)($catalogRow['tags'] ?? []))),
            'free' => ($provider === 'openrouter' && !empty($cfg['free_only_catalog'])),
            'is_free' => ($provider === 'openrouter' && !empty($cfg['free_only_catalog'])),
            'zero_token' => ($provider === 'openrouter' && !empty($cfg['free_only_catalog'])),
            'api_token_multiplier' => ($provider === 'openrouter' && !empty($cfg['free_only_catalog'])) ? 0 : 1,
            'extra_headers' => (array)($cfg['extra_headers'] ?? []),
        ],
    ];
}

function ai_api_model_type(array $model): string {
    return strtolower(trim((string)($model['type'] ?? 'chat'))) ?: 'chat';
}

function ai_api_model_is_chat(array $model): bool {
    return in_array(ai_api_model_type($model), ['chat', 'reasoning'], true);
}

function ai_api_model_bool_value($value): bool {
    if (is_bool($value)) return $value;
    if (is_int($value) || is_float($value)) return ((float)$value) > 0;
    $text = strtolower(trim((string)$value));
    return in_array($text, ['1', 'true', 'yes', 'on', 'free', 'zero', 'zero-token', 'no-token', '免token', '免扣费'], true);
}

function ai_api_model_tag_list(array $model): array {
    $tags = [];
    foreach ((array)($model['tags'] ?? []) as $tag) {
        $tag = strtolower(trim((string)$tag));
        if ($tag !== '') $tags[] = $tag;
    }
    return array_values(array_unique($tags));
}

function ai_api_model_text_blob(string $key, array $model): string {
    $parts = [
        $key,
        (string)($model['model_name'] ?? ''),
        (string)($model['label'] ?? ''),
        (string)($model['provider'] ?? ''),
        (string)($model['base_url'] ?? ''),
    ];
    foreach ((array)($model['aliases'] ?? []) as $alias) $parts[] = (string)$alias;
    foreach ((array)($model['tags'] ?? []) as $tag) $parts[] = (string)$tag;
    return strtolower(trim(implode(' ', array_filter($parts, fn($v) => trim((string)$v) !== ''))));
}

function ai_api_model_alias_or_key_is(array $model, string $key, array $names): bool {
    $names = array_map('strtolower', $names);
    $candidates = [strtolower(trim($key)), strtolower(trim((string)($model['model_name'] ?? '')))];
    foreach ((array)($model['aliases'] ?? []) as $alias) $candidates[] = strtolower(trim((string)$alias));
    foreach ($candidates as $candidate) {
        if ($candidate !== '' && in_array($candidate, $names, true)) return true;
    }
    return false;
}

function ai_api_model_is_zero_token(string $key, array $model): bool {
    foreach (['zero_token', 'no_token_charge', 'is_free', 'free', 'free_model'] as $flag) {
        if (array_key_exists($flag, $model) && ai_api_model_bool_value($model[$flag])) return true;
    }

    if (array_key_exists('api_token_multiplier', $model) && is_numeric($model['api_token_multiplier']) && (float)$model['api_token_multiplier'] <= 0) return true;

    $tags = ai_api_model_tag_list($model);
    foreach ($tags as $tag) {
        if (in_array($tag, ['free', 'free-quota', 'free-tier', 'zero-token', 'no-token', 'no-token-charge', 'upstream-free', '免token', '免扣费'], true)) return true;
        if (str_contains($tag, 'free') || str_contains($tag, 'zero-token') || str_contains($tag, 'no-token')) return true;
    }

    if (ai_api_model_alias_or_key_is($model, $key, ['lite', 'spark-lite', 'pro', 'advanced-pro'])) return true;

    $blob = ai_api_model_text_blob($key, $model);

    if (str_contains($blob, '免费') || str_contains($blob, '免token') || str_contains($blob, '免扣费')) return true;
    if (str_contains($blob, 'openrouter/free') || str_contains($blob, 'openrouter::openrouter/free') || preg_match('/(^|[\s:\/_-])[^\\s]+:free($|[\s])/', $blob)) return true;

    // NVIDIA models are free in this site policy, including direct NVIDIA endpoints
    // and OpenRouter NVIDIA/Nemotron free entries.
    if (str_contains($blob, 'nvidia') || str_contains($blob, 'integrate.api.nvidia.com') || str_contains($blob, 'nemotron')) return true;

    // Site built-in Lite / Pro models should also never consume site tokens.
    if (preg_match('/(^|[\s:\/_-])spark[\s_-]*lite($|[\s:\/_-])/', $blob)) return true;
    if (preg_match('/(^|[\s:\/_-])进阶[\s_-]*pro($|[\s:\/_-])/', $blob)) return true;

    return false;
}

function ai_api_model_price_label(string $key, array $model): string {
    return ai_api_model_is_zero_token($key, $model) ? '免Token' : '';
}

function ai_api_default_model_multiplier(string $key, array $model): float {
    if (ai_api_model_is_zero_token($key, $model)) return 0.0;
    if (isset($model['api_token_multiplier'])) return max(0.01, (float)$model['api_token_multiplier']);
    $name = strtolower($key . ' ' . (string)($model['model_name'] ?? '') . ' ' . (string)($model['label'] ?? ''));
    if (str_contains($name, '397b')) return 8.0;
    if (str_contains($name, '122b')) return 3.0;
    if (str_contains($name, '36b')) return 2.0;
    if (str_contains($name, '31b')) return 3.0;
    if (str_contains($name, 'glm') || str_contains($name, 'minimax')) return 2.0;
    if (str_contains($name, 'gpt-oss')) return 2.0;
    return 1.0;
}

function ai_api_public_models(): array {
    $cfg = ai_api_load_model_config();
    $models = [];
    foreach ($cfg['models'] as $key => $model) {
        if (empty($model['enabled']) || !ai_api_model_is_chat((array)$model)) continue;
        $zeroToken = ai_api_model_is_zero_token((string)$key, (array)$model);
        $models[] = [
            'id' => (string)$key,
            'label' => (string)($model['label'] ?? $key),
            'type' => ai_api_model_type((array)$model),
            'provider' => (string)($model['provider'] ?? ($model['base_url'] ?? '')),
            'model_name' => (string)($model['model_name'] ?? $key),
            'stream_supported' => array_key_exists('stream_supported', (array)$model) ? !empty($model['stream_supported']) : true,
            'supports_thinking' => !empty($model['supports_thinking']),
            'supports_image_input' => !empty($model['supports_image_input']),
            'max_tokens' => (int)($model['max_tokens'] ?? 4096),
            'token_multiplier' => $zeroToken ? 0.0 : ai_api_default_model_multiplier((string)$key, (array)$model),
            'aliases' => array_values((array)($model['aliases'] ?? [])),
            'tags' => array_values((array)($model['tags'] ?? [])),
            'is_free' => $zeroToken,
            'price_label' => $zeroToken ? ai_api_model_price_label((string)$key, (array)$model) : '',
        ];
    }
    $models = array_merge($models, ai_api_registry_public_models($models));
    return $models;
}

function ai_api_resolve_model(string $requested): array {
    $cfg = ai_api_load_model_config();
    $requested = trim($requested) ?: (string)($cfg['default_model'] ?? '');
    $canonicalMimo = ai_api_canonical_mimo_model_id($requested);
    if ($canonicalMimo !== '') {
        $mimoProvider = ai_api_is_mimo2_request($requested) ? 'mimo2' : 'mimo';
        $dynamic = ai_api_dynamic_provider_model($mimoProvider . '::' . $canonicalMimo);
        if ($dynamic) return ['key' => (string)$dynamic['key'], 'model' => (array)$dynamic['model'], 'cfg' => $cfg];
    }
    foreach ($cfg['models'] as $key => $model) {
        $model = (array)$model;
        if (empty($model['enabled']) || !ai_api_model_is_chat($model)) continue;
        $candidates = array_merge([(string)$key, (string)($model['model_name'] ?? '')], array_map('strval', (array)($model['aliases'] ?? [])));
        foreach ($candidates as $candidate) {
            if ($candidate !== '' && strcasecmp($candidate, $requested) === 0) {
                return ['key' => (string)$key, 'model' => $model, 'cfg' => $cfg];
            }
        }
    }
    if ($requested !== '') {
        $dynamic = ai_api_dynamic_provider_model($requested);
        if ($dynamic) return ['key' => (string)$dynamic['key'], 'model' => (array)$dynamic['model'], 'cfg' => $cfg];
    }
    if ($requested === '' && !empty($cfg['default_model']) && isset($cfg['models'][$cfg['default_model']])) {
        return ['key' => (string)$cfg['default_model'], 'model' => (array)$cfg['models'][$cfg['default_model']], 'cfg' => $cfg];
    }
    ai_api_openai_error('model_not_found', '模型不存在或未上架：' . $requested, 404);
}

function ai_api_openai_error(string $type, string $message, int $status = 400, string $code = ''): void {
    // A few third-party OpenAI-compatible clients treat any HTTP 403 from the
    // target domain as a hard connection failure and hide the JSON body.
    // The API layer therefore avoids emitting 403 directly. Permission/auth
    // failures still return an OpenAI-style JSON error, but with 401 so the
    // client can display the real message instead of a generic "403 Server error".
    $originalStatus = $status;
    if ($status === 403) $status = 401;
    http_response_code($status);
    ai_api_cors_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Request-Id: kd-' . bin2hex(random_bytes(8)));
    if ($originalStatus !== $status) header('X-KingDungeon-Original-Status: ' . $originalStatus);
    echo json_encode(['error' => ['message' => $message, 'type' => $type, 'param' => null, 'code' => $code ?: $type]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ai_api_has_any_auth_candidate(): bool {
    $headers = ai_api_request_headers();
    foreach (['authorization', 'redirect-http-authorization', 'x-http-authorization', 'x-api-key', 'api-key', 'apikey'] as $h) {
        if (trim((string)($headers[$h] ?? '')) !== '') return true;
    }
    foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION', 'Authorization'] as $k) {
        if (trim((string)($_SERVER[$k] ?? '')) !== '') return true;
    }
    return isset($_GET['api_key']) && trim((string)$_GET['api_key']) !== '';
}

function ai_api_build_headers(array $cfg, array $model, bool $stream): array {
    $authType = strtolower(trim((string)($model['auth_type'] ?? 'bearer')));
    $apiKey = trim((string)($model['api_key'] ?? $cfg['api_key'] ?? ''));
    $apiPassword = trim((string)($model['api_password'] ?? $cfg['api_password'] ?? ''));
    // 【修改｜兼容性优化｜风险等级：低】允许上游模型指定 Accept 头，修复 GitHub Models 等平台对 Accept 版本头敏感导致的对接失败。
    $acceptHeader = trim((string)($model['accept_header'] ?? $cfg['accept_header'] ?? ''));
    if ($acceptHeader === '') $acceptHeader = $stream ? 'text/event-stream' : 'application/json';
    $headers = [
        'Content-Type: application/json',
        'Accept: ' . $acceptHeader,
        'User-Agent: ' . trim((string)($model['user_agent'] ?? $cfg['user_agent'] ?? 'KingDungeon-OpenAI-Compatible-Gateway/1.2')),
        'Connection: close',
    ];

    if (in_array($authType, ['api_password', 'password', 'apipassword'], true)) {
        if ($apiPassword === '') throw new RuntimeException(($model['label'] ?? '模型') . ' 未配置 API Password');
        $headers[] = 'Authorization: Bearer ' . $apiPassword;
    } elseif (in_array($authType, ['x-api-key', 'apikey', 'api_key'], true)) {
        if ($apiKey === '') throw new RuntimeException(($model['label'] ?? '模型') . ' 未配置 API Key');
        $headers[] = 'X-API-Key: ' . $apiKey;
    } elseif (in_array($authType, ['none', 'noauth', 'anonymous'], true)) {
        // No upstream authorization header.
    } else {
        if ($apiKey === '') throw new RuntimeException(($model['label'] ?? '模型') . ' 未配置 API Key');
        $headers[] = 'Authorization: Bearer ' . $apiKey;
    }

    $resourceId = trim((string)($model['resource_id'] ?? $cfg['resource_id'] ?? ''));
    if ($resourceId !== '') $headers[] = 'lora_id: ' . $resourceId;

    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host !== '') $headers[] = 'X-Forwarded-Host: ' . $host;
    $scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) ? 'https' : 'http';
    $origin = $host !== '' ? ($scheme . '://' . $host) : 'https://example.com';

    if (!empty($model['upstream_browser_headers']) && $host !== '') {
        $scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) ? 'https' : 'http';
        $origin = $scheme . '://' . $host;
        $headers[] = 'Origin: ' . $origin;
        $headers[] = 'Referer: ' . $origin . '/';
    }

    // 【修改｜兼容性优化｜风险等级：低】合并 provider 默认头和模型自定义头，补齐 GitHub/OpenRouter 等平台必需/推荐头。
    $providerHeaderMap = ai_api_provider_default_headers((string)($model['provider_key'] ?? $model['provider_id'] ?? $model['provider'] ?? ''));
    $modelHeaderMap = !empty($model['extra_headers']) && is_array($model['extra_headers']) ? $model['extra_headers'] : [];
    foreach (array_merge($providerHeaderMap, $modelHeaderMap) as $k => $v) {
        $k = trim((string)$k);
        if ($k === '' || preg_match('/[\r\n:]/', $k)) continue;
        $value = strtr((string)$v, ['{host}' => $host, '{origin}' => $origin]);
        $headers[] = $k . ': ' . str_replace(["\r", "\n"], '', $value);
    }

    if ($stream) $headers[] = 'Cache-Control: no-cache';
    return $headers;
}

function ai_api_request_url(array $model): string {
    $base = rtrim((string)($model['base_url'] ?? ''), '/');
    $path = trim((string)($model['path'] ?? '/chat/completions')) ?: '/chat/completions';
    if ($base === '') throw new RuntimeException(($model['label'] ?? '模型') . ' 未配置 base_url');
    if ($path[0] !== '/') $path = '/' . $path;
    return $base . $path;
}


function ai_api_message_text_from_content($content): string {
    if (is_string($content) || is_numeric($content)) return trim((string)$content);
    if (!is_array($content)) return '';
    $parts = [];
    foreach ($content as $item) {
        if (is_string($item) || is_numeric($item)) { $parts[] = (string)$item; continue; }
        if (!is_array($item)) continue;
        $type = strtolower(trim((string)($item['type'] ?? '')));
        if (($type === 'text' || $type === 'input_text' || $type === 'output_text') && isset($item['text'])) $parts[] = (string)$item['text'];
        elseif ($type === 'image_url' || $type === 'input_image') $parts[] = '[图片输入]';
        elseif (isset($item['text'])) $parts[] = (string)$item['text'];
        elseif (isset($item['content'])) $parts[] = ai_api_message_text_from_content($item['content']);
    }
    return trim(implode("\n", array_filter($parts, fn($v) => trim((string)$v) !== '')));
}

function ai_api_normalize_messages_for_upstream(array $messages, array $model): array {
    $allowImage = !empty($model['supports_image_input']);
    $clean = [];
    foreach ($messages as $msg) {
        if (!is_array($msg)) continue;
        $role = strtolower(trim((string)($msg['role'] ?? 'user')));
        if (!in_array($role, ['system', 'developer', 'user', 'assistant', 'tool'], true)) $role = 'user';
        if ($role === 'developer') $role = 'system';
        $row = $msg;
        $row['role'] = $role;
        if (array_key_exists('content', $row)) {
            if ($row['content'] === null) $row['content'] = '';
            if (is_array($row['content']) && !$allowImage) {
                $row['content'] = ai_api_message_text_from_content($row['content']);
            } elseif (is_array($row['content']) && $allowImage) {
                $parts = [];
                foreach ($row['content'] as $item) {
                    if (is_array($item) && (($item['type'] ?? '') === 'input_text')) $item['type'] = 'text';
                    if (is_array($item) && (($item['type'] ?? '') === 'input_image')) {
                        $url = $item['image_url'] ?? $item['url'] ?? '';
                        $item = ['type' => 'image_url', 'image_url' => is_array($url) ? $url : ['url' => (string)$url]];
                    }
                    $parts[] = $item;
                }
                $row['content'] = $parts;
            }
        } else {
            $row['content'] = '';
        }
        $clean[] = $row;
    }
    return $clean;
}

function ai_api_request_payload(array $body, string $publicKey, array $model): array {
    if (!isset($body['messages']) || !is_array($body['messages'])) ai_api_openai_error('invalid_request_error', 'messages 必须是数组', 400);
    $allowed = [
        'model', 'messages', 'stream', 'temperature', 'top_p', 'max_tokens', 'max_completion_tokens',
        'presence_penalty', 'frequency_penalty', 'stop', 'user', 'n', 'tools', 'tool_choice',
        'response_format', 'seed', 'logit_bias', 'logprobs', 'top_logprobs', 'stream_options'
    ];
    $payload = [];
    foreach ($allowed as $field) if (array_key_exists($field, $body)) $payload[$field] = $body[$field];
    $payload['model'] = (string)($model['model_name'] ?? $publicKey);
    $messagesForUpstream = (array)$body['messages'];
    if (in_array(ai_api_normalize_provider_name((string)($model['provider_key'] ?? $model['provider_id'] ?? $model['provider'] ?? '')), ['mimo', 'mimo2'], true)) {
        $messagesForUpstream = ai_api_with_mimo_identity_guard($messagesForUpstream, $payload['model']);
    }
    $payload['messages'] = ai_api_normalize_messages_for_upstream($messagesForUpstream, $model);
    if (isset($payload['max_completion_tokens']) && !isset($payload['max_tokens'])) $payload['max_tokens'] = (int)$payload['max_completion_tokens'];
    unset($payload['max_completion_tokens']);
    foreach (['temperature', 'top_p', 'presence_penalty', 'frequency_penalty'] as $floatField) {
        if (array_key_exists($floatField, $payload) && $payload[$floatField] !== null && $payload[$floatField] !== '') $payload[$floatField] = (float)$payload[$floatField];
    }
    if (isset($payload['max_tokens'])) $payload['max_tokens'] = max(1, (int)$payload['max_tokens']);
    if (!array_key_exists('temperature', $payload) && array_key_exists('temperature', $model)) $payload['temperature'] = (float)$model['temperature'];
    if (!array_key_exists('top_p', $payload) && array_key_exists('top_p', $model)) $payload['top_p'] = (float)$model['top_p'];
    if (!array_key_exists('max_tokens', $payload) && array_key_exists('max_tokens', $model)) $payload['max_tokens'] = (int)$model['max_tokens'];
    if (!array_key_exists('presence_penalty', $payload) && array_key_exists('presence_penalty', $model)) $payload['presence_penalty'] = (float)$model['presence_penalty'];
    if (!array_key_exists('frequency_penalty', $payload) && array_key_exists('frequency_penalty', $model)) $payload['frequency_penalty'] = (float)$model['frequency_penalty'];
    if (isset($payload['stream']) && !is_bool($payload['stream'])) $payload['stream'] = in_array(strtolower((string)$payload['stream']), ['1', 'true', 'yes', 'on'], true);
    if (empty($payload['stream'])) unset($payload['stream_options']);
    else {
        $streamOptions = isset($payload['stream_options']) && is_array($payload['stream_options']) ? $payload['stream_options'] : [];
        $streamOptions['include_usage'] = true;
        $payload['stream_options'] = $streamOptions;
    }
    if (!empty($model['extra_body']) && is_array($model['extra_body'])) $payload = array_merge($payload, $model['extra_body']);
    // 【修改｜兼容性优化｜风险等级：低】删除不同上游不兼容的非核心可选字段，防止 400/422 影响 API 对接。
    return ai_api_sanitize_payload_for_upstream($payload, $model);
}

function ai_api_estimate_tokens_from_value($value): int {
    if (is_array($value)) {
        $sum = 0;
        foreach ($value as $v) $sum += ai_api_estimate_tokens_from_value($v);
        return $sum;
    }
    $text = (string)$value;
    if ($text === '') return 0;
    $chars = mb_strlen($text, 'UTF-8');
    $asciiWords = preg_match_all('/[a-zA-Z0-9_]+/', $text, $m) ?: 0;
    return max(1, (int)ceil(($chars + $asciiWords) / 3.2));
}

function ai_api_estimate_prompt_tokens(array $messages): int {
    $sum = 0;
    foreach ($messages as $msg) $sum += 4 + ai_api_estimate_tokens_from_value($msg);
    return max(1, $sum);
}

function ai_api_usage_from_response(array $data, array $payload): array {
    $usage = (array)($data['usage'] ?? []);
    $prompt = (int)($usage['prompt_tokens'] ?? 0);
    $completion = (int)($usage['completion_tokens'] ?? 0);
    $total = (int)($usage['total_tokens'] ?? 0);
    if ($total <= 0) {
        $prompt = ai_api_estimate_prompt_tokens((array)($payload['messages'] ?? []));
        $text = '';
        foreach ((array)($data['choices'] ?? []) as $choice) {
            $message = (array)($choice['message'] ?? []);
            $text .= ' ' . (string)($message['content'] ?? '');
            $text .= ' ' . (string)($message['reasoning_content'] ?? '');
        }
        $completion = ai_api_estimate_tokens_from_value($text);
        $total = $prompt + $completion;
    }
    return ['prompt_tokens' => max(0, $prompt), 'completion_tokens' => max(0, $completion), 'total_tokens' => max(1, $total)];
}

function ai_api_charge_for_usage(array $usage, string $modelKey, array $model): int {
    if (ai_api_model_is_zero_token($modelKey, $model)) return 0;
    $multiplier = ai_api_default_model_multiplier($modelKey, $model);
    if ($multiplier <= 0) return 0;
    return max(1, (int)ceil(max(1, (int)($usage['total_tokens'] ?? 1)) * $multiplier));
}

function ai_api_record_usage(int $userId, int $keyId, string $modelKey, array $usage, int $chargedTokens, string $status, array $meta = []): void {
    $rows = ai_api_store_read(AI_API_USAGE_FILE);
    $rows[] = [
        'id' => next_id($rows),
        'user_id' => $userId,
        'key_id' => $keyId,
        'model' => $modelKey,
        'prompt_tokens' => (int)($usage['prompt_tokens'] ?? 0),
        'completion_tokens' => (int)($usage['completion_tokens'] ?? 0),
        'total_tokens' => (int)($usage['total_tokens'] ?? 0),
        'charged_tokens' => $chargedTokens,
        'status' => $status,
        'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        'meta' => $meta,
        'created_at' => ai_api_now(),
    ];
    if (count($rows) > 2000) $rows = array_slice($rows, -2000);
    ai_api_store_write(AI_API_USAGE_FILE, $rows);
}

function ai_api_user_usage(int $userId, int $limit = 30): array {
    $rows = array_values(array_filter(ai_api_store_read(AI_API_USAGE_FILE), fn($row) => (int)($row['user_id'] ?? 0) === $userId));
    usort($rows, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return array_slice($rows, 0, $limit);
}

function ai_api_rate_limit(string $keyHash): void {
    $window = (int)floor(time() / 60);
    $rows = ai_api_store_read(AI_API_RATE_LIMIT_FILE);
    $new = [];
    $current = null;
    foreach ($rows as $row) {
        if ((int)($row['window'] ?? 0) < $window - 2) continue;
        if (($row['key_hash'] ?? '') === $keyHash && (int)($row['window'] ?? 0) === $window) $current = $row;
        $new[] = $row;
    }
    if (!$current) {
        $new[] = ['id' => next_id($new), 'key_hash' => $keyHash, 'window' => $window, 'count' => 1, 'updated_at' => ai_api_now()];
    } else {
        foreach ($new as &$row) {
            if (($row['key_hash'] ?? '') === $keyHash && (int)($row['window'] ?? 0) === $window) {
                $row['count'] = (int)($row['count'] ?? 0) + 1;
                $row['updated_at'] = ai_api_now();
                if ((int)$row['count'] > 60) {
                    ai_api_store_write(AI_API_RATE_LIMIT_FILE, $new);
                    ai_api_openai_error('rate_limit_exceeded', '请求过于频繁：当前每个 API Key 默认限制 60 次/分钟', 429);
                }
                break;
            }
        }
        unset($row);
    }
    ai_api_store_write(AI_API_RATE_LIMIT_FILE, $new);
}


function ai_api_curl_post_json(string $url, array $headers, array $payload, int $timeout, bool $verifySsl): array {
    if (!function_exists('curl_init')) return ['status' => 0, 'body' => '', 'error' => '当前 PHP 环境未开启 cURL 扩展'];
    $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($jsonBody === false) return ['status' => 0, 'body' => '', 'error' => '请求 JSON 编码失败'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $jsonBody,
        CURLOPT_TIMEOUT => max(10, $timeout),
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        CURLOPT_HEADER => false,
        // 【新增｜兼容性优化｜风险等级：低】部分上游会在边缘层 301/302 到区域网关，允许安全跟随 HTTPS 跳转。
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => $body === false ? '' : (string)$body, 'error' => $err];
}


function ai_api_mask_secret_for_log(string $text): string {
    // 【修改｜安全加固｜风险等级：低】扩展常见 AI 平台密钥脱敏模式，防止日志泄漏上游 AK/SK。
    $text = preg_replace('/sk-[A-Za-z0-9_\-.]{10,}/', 'sk-***', $text);
    $text = preg_replace('/sk-or-v1-[A-Za-z0-9_\-.]{10,}/', 'sk-or-v1-***', $text);
    $text = preg_replace('/nvapi-[A-Za-z0-9_\-.]{10,}/', 'nvapi-***', $text);
    $text = preg_replace('/github_pat_[A-Za-z0-9_]+/i', 'github_pat_***', $text);
    $text = preg_replace('/Bearer\s+[A-Za-z0-9_\-.:\+\/=]{10,}/i', 'Bearer ***', $text);
    return (string)$text;
}

function ai_api_log_upstream_failure(string $modelKey, string $url, array $payload, array $resp, array $headers = []): void {
    $row = [
        'time' => ai_api_now(),
        'model' => $modelKey,
        'url' => preg_replace('#://([^/@]+@)#', '://***@', $url),
        'status' => (int)($resp['status'] ?? 0),
        'curl_error' => ai_api_mask_secret_for_log((string)($resp['error'] ?? '')),
        'body' => ai_api_mask_secret_for_log(mb_substr((string)($resp['body'] ?? ''), 0, 1200, 'UTF-8')),
        'payload_model' => (string)($payload['model'] ?? ''),
        'payload_keys' => array_keys($payload),
        'request_ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        'ua' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200, 'UTF-8'),
    ];
    $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($line !== false) @file_put_contents(DATA_DIR . '/ai_api_gateway_error.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function ai_api_upstream_error_is_retryable(int $status, string $curlError = ''): bool {
    if ($status === 0) return true;
    return in_array($status, [401, 403, 404, 408, 409, 425, 429, 500, 502, 503, 504], true);
}

function ai_api_model_has_upstream_credential(array $cfg, array $model): bool {
    $authType = strtolower(trim((string)($model['auth_type'] ?? 'bearer')));
    if (in_array($authType, ['none', 'noauth', 'anonymous'], true)) return true;
    if (in_array($authType, ['api_password', 'password', 'apipassword'], true)) {
        return trim((string)($model['api_password'] ?? $cfg['api_password'] ?? '')) !== '';
    }
    return trim((string)($model['api_key'] ?? $cfg['api_key'] ?? '')) !== '';
}

function ai_api_fallback_candidates(string $primaryKey, array $primaryModel, array $cfg, bool $explicitModel): array {
    $candidates = [['key' => $primaryKey, 'model' => $primaryModel, 'fallback' => false]];

    if ($explicitModel && empty($cfg['fallback_for_explicit_model'])) return $candidates;
    if (array_key_exists('enable_upstream_fallback', $cfg) && empty($cfg['enable_upstream_fallback'])) return $candidates;

    $seen = [$primaryKey => true];
    $preferred = array_values((array)($cfg['fallback_models'] ?? []));
    foreach ($preferred as $key) {
        $key = (string)$key;
        if ($key === '' || isset($seen[$key]) || empty($cfg['models'][$key]) || !is_array($cfg['models'][$key])) continue;
        $model = (array)$cfg['models'][$key];
        if (empty($model['enabled']) || !ai_api_model_is_chat($model) || !ai_api_model_has_upstream_credential($cfg, $model)) continue;
        $candidates[] = ['key' => $key, 'model' => $model, 'fallback' => true];
        $seen[$key] = true;
    }

    foreach ($cfg['models'] as $key => $model) {
        $key = (string)$key;
        $model = (array)$model;
        if (isset($seen[$key]) || empty($model['enabled']) || !ai_api_model_is_chat($model)) continue;
        if (!ai_api_model_has_upstream_credential($cfg, $model)) continue;
        $candidates[] = ['key' => $key, 'model' => $model, 'fallback' => true];
        $seen[$key] = true;
    }
    return $candidates;
}

function ai_api_call_upstream_nonstream_with_fallback(array $body, string $requestedModel, string $primaryKey, array $primaryModel, array $cfg): array {
    $explicitModel = trim($requestedModel) !== '';
    $attempts = [];
    $lastResp = null;
    $lastData = null;

    foreach (ai_api_fallback_candidates($primaryKey, $primaryModel, $cfg, $explicitModel) as $candidate) {
        $key = (string)$candidate['key'];
        $model = (array)$candidate['model'];
        $payload = ai_api_request_payload($body, $key, $model);
        $payload['stream'] = false;
        unset($payload['stream_options']);

        $headers = ai_api_build_headers($cfg, $model, false);
        $url = ai_api_request_url($model);
        $timeout = max(15, (int)($model['timeout'] ?? 120));
        $verifySsl = array_key_exists('verify_ssl', $cfg) ? !empty($cfg['verify_ssl']) : true;
        if (array_key_exists('verify_ssl', $model)) $verifySsl = !empty($model['verify_ssl']);

        $resp = ai_api_curl_post_json($url, $headers, $payload, $timeout, $verifySsl);
        $data = json_decode((string)$resp['body'], true);
        $lastResp = $resp;
        $lastData = $data;

        if ((int)$resp['status'] >= 200 && (int)$resp['status'] < 300 && is_array($data)) {
            return [
                'ok' => true,
                'key' => $key,
                'model' => $model,
                'payload' => $payload,
                'resp' => $resp,
                'data' => $data,
                'attempts' => $attempts,
                'fallback_used' => !empty($candidate['fallback']),
            ];
        }

        ai_api_log_upstream_failure($key, $url, $payload, $resp, $headers);
        $message = ai_api_extract_upstream_error_message(is_array($data) ? $data : (string)$resp['body'], (string)($resp['error'] ?? ''));
        $attempts[] = [
            'model' => $key,
            'status' => (int)($resp['status'] ?? 0),
            'message' => mb_substr($message, 0, 240, 'UTF-8'),
        ];

        if (!ai_api_upstream_error_is_retryable((int)($resp['status'] ?? 0), (string)($resp['error'] ?? ''))) break;
    }

    return [
        'ok' => false,
        'key' => $primaryKey,
        'model' => $primaryModel,
        'payload' => ai_api_request_payload($body, $primaryKey, $primaryModel),
        'resp' => $lastResp ?: ['status' => 0, 'body' => '', 'error' => '上游模型无响应'],
        'data' => is_array($lastData) ? $lastData : null,
        'attempts' => $attempts,
        'fallback_used' => false,
    ];
}

function ai_api_should_simulate_stream(array $cfg, array $model): bool {
    if (array_key_exists('simulate_stream', $model)) return !empty($model['simulate_stream']);
    if (array_key_exists('simulate_stream', $cfg)) return !empty($cfg['simulate_stream']);
    return true;
}

function ai_api_emit_simulated_stream_from_chat(array $data, int $charge, int $balance): void {
    ai_api_cors_headers();
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-store');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');
    header('X-KingDungeon-Token-Charged: ' . $charge);
    header('X-KingDungeon-Token-Balance: ' . $balance);

    $id = (string)($data['id'] ?? ai_api_openai_request_id());
    $created = (int)($data['created'] ?? time());
    $model = (string)($data['model'] ?? '');
    foreach ((array)($data['choices'] ?? []) as $i => $choice) {
        $choice = (array)$choice;
        $message = (array)($choice['message'] ?? []);
        $content = (string)($message['content'] ?? '');
        $delta = [
            'id' => $id,
            'object' => 'chat.completion.chunk',
            'created' => $created,
            'model' => $model,
            'choices' => [[
                'index' => (int)($choice['index'] ?? $i),
                'delta' => ['role' => 'assistant', 'content' => $content],
                'finish_reason' => null,
            ]],
        ];
        echo 'data: ' . json_encode($delta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    }
    echo 'data: ' . json_encode([
        'id' => $id,
        'object' => 'chat.completion.chunk',
        'created' => $created,
        'model' => $model,
        'choices' => [['index' => 0, 'delta' => new stdClass(), 'finish_reason' => 'stop']],
        'usage' => $data['usage'] ?? null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    echo "data: [DONE]\n\n";
    exit;
}

function ai_api_handle_models(): void {
    ai_api_preflight_or_continue();
    // Many OpenAI-compatible clients probe /v1/models before saving the key.
    // Keep this endpoint public when no auth header is sent, but still validate
    // a provided key so revoked keys are not silently accepted.
    if (ai_api_has_any_auth_candidate()) ai_api_authenticate_request();
    $data = [];
    foreach (ai_api_public_models() as $model) {
        $data[] = ['id' => $model['id'], 'object' => 'model', 'created' => 0, 'owned_by' => 'kingdungeon-proxy'];
    }
    ai_api_json(['object' => 'list', 'data' => $data]);
}

function ai_api_openai_request_id(): string { return 'chatcmpl-kd-' . bin2hex(random_bytes(12)); }

function ai_api_normalize_chat_response(array $data, string $modelKey, string $requestedModel): array {
    if (empty($data['id'])) $data['id'] = ai_api_openai_request_id();
    if (empty($data['object'])) $data['object'] = 'chat.completion';
    if (empty($data['created'])) $data['created'] = time();
    $data['model'] = $requestedModel !== '' ? $requestedModel : $modelKey;
    if (empty($data['choices']) || !is_array($data['choices'])) {
        $text = '';
        foreach (['content', 'text', 'message', 'answer', 'output'] as $field) {
            if (isset($data[$field]) && (is_string($data[$field]) || is_numeric($data[$field]))) { $text = (string)$data[$field]; break; }
        }
        $data['choices'] = [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $text], 'finish_reason' => 'stop']];
    }
    foreach ($data['choices'] as $i => $choice) {
        if (!is_array($choice)) $choice = [];
        if (!isset($choice['index'])) $choice['index'] = $i;
        if (!isset($choice['message']) || !is_array($choice['message'])) $choice['message'] = ['role' => 'assistant', 'content' => (string)($choice['text'] ?? '')];
        if (empty($choice['message']['role'])) $choice['message']['role'] = 'assistant';
        if (!array_key_exists('content', $choice['message']) || $choice['message']['content'] === null) $choice['message']['content'] = '';
        if (!array_key_exists('finish_reason', $choice)) $choice['finish_reason'] = 'stop';
        $data['choices'][$i] = $choice;
    }
    return $data;
}

function ai_api_extract_upstream_error_message($body, string $fallback = ''): string {
    if (is_array($body)) {
        $err = (array)($body['error'] ?? []);
        $msg = trim((string)($err['message'] ?? $body['message'] ?? ''));
        if ($msg !== '') return $msg;
        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $fallback !== '' ? $fallback : mb_substr((string)$json, 0, 800, 'UTF-8');
    }
    $text = trim((string)$body);
    if ($text !== '') return mb_substr($text, 0, 800, 'UTF-8');
    return $fallback !== '' ? $fallback : '上游模型无响应';
}

function ai_api_execute_chat_completion(array $body, bool $forceNonStream = false): array {
    $auth = ai_api_authenticate_request();
    ai_api_rate_limit((string)$auth['secret_hash']);
    $requestedModel = trim((string)($body['model'] ?? ''));
    $resolved = ai_api_resolve_model($requestedModel);
    $modelKey = $resolved['key'];
    $model = $resolved['model'];
    $cfg = $resolved['cfg'];
    $zeroTokenModel = ai_api_model_is_zero_token($modelKey, $model);
    $wallet = ai_api_wallet_for_user((int)$auth['user']['id']);
    if (!$zeroTokenModel && (int)$wallet['balance_tokens'] <= 0) ai_api_openai_error('insufficient_quota', 'Token 余额不足，请先在站内购买或领取 Token 包', 402);

    if ($forceNonStream) $body['stream'] = false;
    $streamRequested = !$forceNonStream && !empty($body['stream']);
    $stream = $streamRequested;
    if ($streamRequested && ai_api_should_simulate_stream($cfg, $model)) {
        $stream = false;
        $body['stream'] = false;
    }

    if ($stream) {
        $payload = ai_api_request_payload($body, $modelKey, $model);
        $headers = ai_api_build_headers($cfg, $model, true);
        $url = ai_api_request_url($model);
        $timeout = max(15, (int)($model['timeout'] ?? 120));
        $verifySsl = array_key_exists('verify_ssl', $cfg) ? !empty($cfg['verify_ssl']) : true;
        if (array_key_exists('verify_ssl', $model)) $verifySsl = !empty($model['verify_ssl']);
        ai_api_proxy_stream($url, $headers, $payload, $timeout, $verifySsl, $auth, $modelKey, $model, $requestedModel);
        exit;
    }

    $attempt = ai_api_call_upstream_nonstream_with_fallback($body, $requestedModel, $modelKey, $model, $cfg);
    if (empty($attempt['ok'])) {
        $resp = (array)($attempt['resp'] ?? []);
        ai_api_record_usage((int)$auth['user']['id'], (int)$auth['key']['id'], $modelKey, ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0], 0, 'upstream_error', [
            'http_status' => (int)($resp['status'] ?? 0),
            'error' => (string)($resp['error'] ?? ''),
            'body' => mb_substr((string)($resp['body'] ?? ''), 0, 800, 'UTF-8'),
            'attempts' => $attempt['attempts'] ?? [],
        ]);
        $message = ai_api_extract_upstream_error_message($attempt['data'] ?: (string)($resp['body'] ?? ''), (string)($resp['error'] ?? ''));
        $attemptText = '';
        if (!empty($attempt['attempts']) && is_array($attempt['attempts'])) {
            $pieces = [];
            foreach ($attempt['attempts'] as $a) $pieces[] = ($a['model'] ?? '') . ' HTTP ' . ($a['status'] ?? 0);
            $attemptText = '；已尝试：' . implode('，', $pieces);
        }
        $status = (int)($resp['status'] ?? 0);
        $publicStatus = ($status === 401 || $status === 403 || $status === 0) ? 502 : (($status >= 400 && $status < 600) ? $status : 502);
        ai_api_openai_error('upstream_error', '上游模型调用失败：' . $message . $attemptText . '。详细日志见 data/ai_api_gateway_error.log', $publicStatus);
    }

    $modelKey = (string)$attempt['key'];
    $model = (array)$attempt['model'];
    if ($zeroTokenModel && !empty($attempt['fallback_used'])) {
        $model['zero_token'] = true;
        $model['no_token_charge'] = true;
        $model['is_free'] = true;
        $model['free'] = true;
        $model['api_token_multiplier'] = 0;
    }
    $payload = (array)$attempt['payload'];
    $data = ai_api_normalize_chat_response((array)$attempt['data'], $modelKey, $requestedModel);
    if (!empty($attempt['fallback_used'])) $data['kd_gateway'] = ['fallback_model' => $modelKey];

    $usage = ai_api_usage_from_response($data, $payload);
    $charge = ai_api_charge_for_usage($usage, $modelKey, $model);
    $walletAfter = $charge > 0
        ? ai_api_update_wallet((int)$auth['user']['id'], -$charge, 'api_usage', ['model' => $modelKey, 'request_id' => (string)($data['id'] ?? '')])
        : $wallet;
    $usageMeta = ['request_id' => (string)($data['id'] ?? ''), 'fallback_used' => !empty($attempt['fallback_used'])];
    if ($charge === 0) $usageMeta['zero_token'] = true;
    ai_api_record_usage((int)$auth['user']['id'], (int)$auth['key']['id'], $modelKey, $usage, $charge, 'success', $usageMeta);
    $data['usage'] = array_merge($usage, ['charged_tokens' => $charge, 'balance_tokens' => (int)$walletAfter['balance_tokens'], 'zero_token' => $charge === 0]);

    if ($streamRequested && !$forceNonStream) {
        ai_api_emit_simulated_stream_from_chat($data, $charge, (int)$walletAfter['balance_tokens']);
        exit;
    }

    return ['data' => $data, 'charge' => $charge, 'balance' => (int)$walletAfter['balance_tokens']];
}

function ai_api_emit_openai_json(array $result): void {
    ai_api_cors_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-KingDungeon-Token-Charged: ' . (int)($result['charge'] ?? 0));
    header('X-KingDungeon-Token-Balance: ' . (int)($result['balance'] ?? 0));
    echo json_encode($result['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ai_api_read_json_body(): array {
    $raw = file_get_contents('php://input');
    $body = json_decode((string)$raw, true);
    if (!is_array($body)) ai_api_openai_error('invalid_request_error', '请求体必须是 JSON', 400);
    return $body;
}

function ai_api_request_method(): string {
    return strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

function ai_api_compat_non_post_probe(string $endpoint): void {
    $method = ai_api_request_method();
    if ($method === 'POST') return;

    // Some OpenAI-compatible clients or server redirects probe endpoint URLs
    // with GET/HEAD before sending real POST requests. Returning 405 here makes
    // those clients fail validation with "bad response status code 405".
    if ($method === 'GET' || $method === 'HEAD') {
        if ($method === 'HEAD') {
            http_response_code(200);
            ai_api_cors_headers();
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            exit;
        }

        $models = [];
        foreach (ai_api_public_models() as $model) {
            $models[] = ['id' => $model['id'], 'object' => 'model', 'created' => 0, 'owned_by' => 'kingdungeon-proxy'];
        }

        ai_api_json([
            'ok' => true,
            'object' => 'endpoint',
            'endpoint' => $endpoint,
            'required_method' => 'POST',
            'message' => '接口可用；正式调用请使用 POST 并提交 JSON 请求体。',
            'base_url' => ai_api_base_url(),
            'models' => ['object' => 'list', 'data' => $models],
        ]);
    }

    header('Allow: POST, OPTIONS');
    ai_api_openai_error('invalid_request_error', '仅支持 POST 请求', 405);
}

function ai_api_handle_chat_completion(): void {
    ai_api_preflight_or_continue();
    ai_api_compat_non_post_probe('/v1/chat/completions');
    try {
        ai_api_emit_openai_json(ai_api_execute_chat_completion(ai_api_read_json_body(), false));
    } catch (Throwable $e) {
        ai_api_openai_error('server_error', $e->getMessage(), 500);
    }
}

function ai_api_proxy_stream(string $url, array $headers, array $payload, int $timeout, bool $verifySsl, array $auth, string $modelKey, array $model, string $requestedModel = ''): void {
    if (!function_exists('curl_init')) ai_api_openai_error('server_error', '当前 PHP 环境未开启 cURL 扩展', 500);
    @ini_set('output_buffering', 'off');
    @ini_set('zlib.output_compression', '0');
    @ini_set('implicit_flush', '1');
    if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', '1'); @apache_setenv('dont-vary', '1'); }
    while (ob_get_level() > 0) @ob_end_flush();
    ob_implicit_flush(true);
    ai_api_cors_headers();
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-store');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');
    $streamZeroToken = ai_api_model_is_zero_token($modelKey, $model);
    $streamWalletBefore = ai_api_wallet_for_user((int)$auth['user']['id']);
    if ($streamZeroToken) {
        header('X-KingDungeon-Token-Charged: 0');
        header('X-KingDungeon-Token-Balance: ' . (int)$streamWalletBefore['balance_tokens']);
    }
    $usage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
    $content = '';
    $buffer = '';
    $doneSeen = false;
    $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $jsonBody,
        CURLOPT_TIMEOUT => max(20, $timeout),
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_HEADER => false,
        // 【新增｜兼容性优化｜风险等级：低】流式转发允许安全跟随上游 HTTPS 重定向，避免区域网关跳转导致连接失败。
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_BUFFERSIZE => 512,
        CURLOPT_WRITEFUNCTION => function($ch, $chunk) use (&$buffer, &$usage, &$content, &$doneSeen) {
            echo $chunk;
            @flush();
            $buffer .= $chunk;
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);
                if (stripos($line, 'data:') !== 0) continue;
                $dataLine = trim(substr($line, 5));
                if ($dataLine === '') continue;
                if ($dataLine === '[DONE]') { $doneSeen = true; continue; }
                $json = json_decode($dataLine, true);
                if (!is_array($json)) continue;
                if (!empty($json['usage']) && is_array($json['usage'])) {
                    $usage['prompt_tokens'] = max($usage['prompt_tokens'], (int)($json['usage']['prompt_tokens'] ?? 0));
                    $usage['completion_tokens'] = max($usage['completion_tokens'], (int)($json['usage']['completion_tokens'] ?? 0));
                    $usage['total_tokens'] = max($usage['total_tokens'], (int)($json['usage']['total_tokens'] ?? 0));
                }
                foreach ((array)($json['choices'] ?? []) as $choice) {
                    $delta = (array)($choice['delta'] ?? []);
                    $content .= (string)($delta['content'] ?? '');
                    $content .= (string)($delta['reasoning_content'] ?? '');
                }
            }
            return strlen($chunk);
        },
    ]);
    $ok = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!$ok || $status < 200 || $status >= 300) {
        ai_api_record_usage((int)$auth['user']['id'], (int)$auth['key']['id'], $modelKey, ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0], 0, 'upstream_error', ['http_status' => $status, 'error' => $err]);
        echo "\nevent: error\n";
        echo 'data: ' . json_encode(['error' => ['message' => $err ?: ('上游流式接口异常 HTTP ' . $status), 'type' => 'upstream_error']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        echo "data: [DONE]\n\n";
        exit;
    }
    if (!$doneSeen) echo "data: [DONE]\n\n";
    if ((int)$usage['total_tokens'] <= 0) {
        $usage['prompt_tokens'] = ai_api_estimate_prompt_tokens((array)($payload['messages'] ?? []));
        $usage['completion_tokens'] = ai_api_estimate_tokens_from_value($content);
        $usage['total_tokens'] = max(1, $usage['prompt_tokens'] + $usage['completion_tokens']);
    }
    $charge = ai_api_charge_for_usage($usage, $modelKey, $model);
    if ($charge > 0) ai_api_update_wallet((int)$auth['user']['id'], -$charge, 'api_usage', ['model' => $modelKey, 'stream' => true]);
    $streamMeta = ['stream' => true];
    if ($charge === 0) $streamMeta['zero_token'] = true;
    ai_api_record_usage((int)$auth['user']['id'], (int)$auth['key']['id'], $modelKey, $usage, $charge, 'success', $streamMeta);
    exit;
}


function ai_api_curl_post_binary(string $url, array $headers, array $payload, int $timeout, bool $verifySsl): array {
    if (!function_exists('curl_init')) return ['status' => 0, 'headers' => '', 'body' => '', 'error' => '当前 PHP 环境未开启 cURL 扩展'];
    $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($jsonBody === false) return ['status' => 0, 'headers' => '', 'body' => '', 'error' => '请求 JSON 编码失败'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $jsonBody,
        CURLOPT_TIMEOUT => max(20, $timeout),
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    if ($raw === false) $raw = '';
    return ['status' => $status, 'headers' => substr((string)$raw, 0, $headerSize), 'body' => substr((string)$raw, $headerSize), 'error' => $err];
}

function ai_api_header_value_from_raw(string $headers, string $name): string {
    $needle = strtolower($name) . ':';
    $value = '';
    foreach (preg_split('/\r?\n/', $headers) as $line) {
        if (stripos($line, $needle) === 0) $value = trim(substr($line, strlen($needle)));
    }
    return $value;
}

function ai_api_handle_audio_speech(): void {
    ai_api_preflight_or_continue();
    ai_api_compat_non_post_probe('/v1/audio/speech');
    try {
        $auth = ai_api_authenticate_request();
        ai_api_rate_limit((string)$auth['secret_hash']);
        $body = ai_api_read_json_body();
        $requestedModel = trim((string)($body['model'] ?? ''));
        if ($requestedModel === '') ai_api_openai_error('invalid_request_error', 'model 必须填写，例如 mimo::MiMo-v2.5-TTS', 400);
        if (!isset($body['input']) && !isset($body['text'])) ai_api_openai_error('invalid_request_error', 'input 必须填写要合成的文本', 400);

        [$provider, $modelId] = ai_api_parse_provider_model($requestedModel, 'mimo');
        $registry = ai_api_provider_registry();
        $cfg = (array)(($registry['providers'] ?? [])[$provider] ?? []);
        if (!$cfg) ai_api_openai_error('model_not_found', '音频模型供应商不存在：' . $provider, 404);
        $catalogRow = ai_api_registry_model_catalog_row($cfg, $modelId);
        $isAudioModel = !empty($catalogRow) && ((string)($catalogRow['type'] ?? '') === 'audio_tts' || in_array('tts', array_map('strtolower', array_map('strval', (array)($catalogRow['tags'] ?? []))), true));
        if (!$isAudioModel) ai_api_openai_error('model_not_found', '该模型未声明为音频合成模型：' . $requestedModel, 404);

        $apiKey = ai_api_provider_key($provider);
        if ($apiKey === '') throw new RuntimeException(($cfg['label'] ?? $provider) . ' 未配置 API Key');
        $modelKey = $provider . '::' . $modelId;
        $model = [
            'enabled' => true,
            'label' => (string)($catalogRow['label'] ?? $modelId),
            'type' => 'audio_tts',
            'protocol' => 'openai_compatible_audio',
            'base_url' => ai_api_provider_base_url($provider, (string)($cfg['base_url'] ?? '')),
            'path' => (string)($cfg['audio_speech_path'] ?? '/audio/speech'),
            'model_name' => $modelId,
            'auth_type' => 'bearer',
            'api_key' => $apiKey,
            'provider_key' => $provider,
            'accept_header' => 'audio/mpeg, audio/*;q=0.9, application/octet-stream;q=0.8, application/json;q=0.5',
            'extra_headers' => (array)($cfg['extra_headers'] ?? []),
            'api_token_multiplier' => (float)($catalogRow['token_multiplier'] ?? 1.0),
            'tags' => (array)($catalogRow['tags'] ?? []),
            'timeout' => 180,
        ];
        $wallet = ai_api_wallet_for_user((int)$auth['user']['id']);
        if (!ai_api_model_is_zero_token($modelKey, $model) && (int)$wallet['balance_tokens'] <= 0) ai_api_openai_error('insufficient_quota', 'Token 余额不足，请先在站内购买或领取 Token 包', 402);

        $payload = $body;
        $payload['model'] = $modelId;
        unset($payload['stream'], $payload['stream_options']);
        if (!isset($payload['input']) && isset($payload['text'])) $payload['input'] = (string)$payload['text'];
        $headers = ai_api_build_headers(['user_agent' => 'KingDungeon-OpenAI-Compatible-Gateway/1.2'], $model, false);
        $url = ai_api_request_url($model);
        $verifySsl = true;
        $resp = ai_api_curl_post_binary($url, $headers, $payload, (int)$model['timeout'], $verifySsl);
        $status = (int)($resp['status'] ?? 0);
        $contentType = ai_api_header_value_from_raw((string)($resp['headers'] ?? ''), 'Content-Type') ?: 'audio/mpeg';
        if ($status < 200 || $status >= 300) {
            ai_api_log_upstream_failure($modelKey, $url, $payload, ['status' => $status, 'body' => (string)($resp['body'] ?? ''), 'error' => (string)($resp['error'] ?? '')], $headers);
            $data = json_decode((string)($resp['body'] ?? ''), true);
            $message = ai_api_extract_upstream_error_message(is_array($data) ? $data : (string)($resp['body'] ?? ''), (string)($resp['error'] ?? ''));
            ai_api_record_usage((int)$auth['user']['id'], (int)$auth['key']['id'], $modelKey, ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0], 0, 'upstream_error', ['endpoint' => 'audio_speech', 'http_status' => $status]);
            ai_api_openai_error('upstream_error', '上游音频合成失败：' . $message, ($status >= 400 && $status < 600) ? $status : 502);
        }

        $inputText = $payload['input'] ?? '';
        $usage = ['prompt_tokens' => ai_api_estimate_tokens_from_value($inputText), 'completion_tokens' => 0, 'total_tokens' => ai_api_estimate_tokens_from_value($inputText)];
        $charge = ai_api_charge_for_usage($usage, $modelKey, $model);
        $walletAfter = $charge > 0 ? ai_api_update_wallet((int)$auth['user']['id'], -$charge, 'api_usage', ['model' => $modelKey, 'endpoint' => 'audio_speech']) : $wallet;
        ai_api_record_usage((int)$auth['user']['id'], (int)$auth['key']['id'], $modelKey, $usage, $charge, 'success', ['endpoint' => 'audio_speech', 'content_type' => $contentType]);

        ai_api_cors_headers();
        header('Content-Type: ' . str_replace(["\r", "\n"], '', $contentType));
        header('Cache-Control: no-store');
        header('X-KingDungeon-Token-Charged: ' . $charge);
        header('X-KingDungeon-Token-Balance: ' . (int)$walletAfter['balance_tokens']);
        echo (string)($resp['body'] ?? '');
        exit;
    } catch (Throwable $e) {
        ai_api_openai_error('server_error', $e->getMessage(), 500);
    }
}

function ai_api_handle_completions(): void {
    ai_api_preflight_or_continue();
    ai_api_compat_non_post_probe('/v1/completions');
    try {
    $body = ai_api_read_json_body();
    $prompt = $body['prompt'] ?? '';
    if (is_array($prompt)) $prompt = implode("\n", array_map(fn($v) => is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE), $prompt));
    $chatBody = $body;
    unset($chatBody['prompt'], $chatBody['suffix'], $chatBody['echo'], $chatBody['best_of']);
    $chatBody['messages'] = [['role' => 'user', 'content' => (string)$prompt]];
    $result = ai_api_execute_chat_completion($chatBody, true);
    $chat = $result['data'];
    $choices = [];
    foreach ((array)($chat['choices'] ?? []) as $i => $choice) {
        $message = (array)($choice['message'] ?? []);
        $choices[] = ['text' => (string)($message['content'] ?? ''), 'index' => (int)($choice['index'] ?? $i), 'logprobs' => null, 'finish_reason' => $choice['finish_reason'] ?? 'stop'];
    }
    $result['data'] = ['id' => str_replace('chatcmpl', 'cmpl', (string)($chat['id'] ?? ai_api_openai_request_id())), 'object' => 'text_completion', 'created' => (int)($chat['created'] ?? time()), 'model' => (string)($chat['model'] ?? ($body['model'] ?? '')), 'choices' => $choices, 'usage' => $chat['usage'] ?? null];
    ai_api_emit_openai_json($result);
    } catch (Throwable $e) {
        ai_api_openai_error('server_error', $e->getMessage(), 500);
    }
}

function ai_api_messages_from_responses_input(array $body): array {
    $messages = [];
    if (!empty($body['instructions'])) $messages[] = ['role' => 'system', 'content' => (string)$body['instructions']];
    $input = $body['input'] ?? ($body['messages'] ?? '');
    if (is_string($input) || is_numeric($input)) { $messages[] = ['role' => 'user', 'content' => (string)$input]; return $messages; }
    if (is_array($input)) {
        foreach ($input as $item) {
            if (is_string($item) || is_numeric($item)) { $messages[] = ['role' => 'user', 'content' => (string)$item]; continue; }
            if (!is_array($item)) continue;
            $role = (string)($item['role'] ?? 'user');
            $content = $item['content'] ?? ($item['text'] ?? '');
            if (is_array($content)) {
                $parts = [];
                foreach ($content as $part) {
                    if (is_array($part) && (($part['type'] ?? '') === 'input_text')) $part = ['type' => 'text', 'text' => (string)($part['text'] ?? '')];
                    if (is_array($part) && (($part['type'] ?? '') === 'output_text')) $part = ['type' => 'text', 'text' => (string)($part['text'] ?? '')];
                    $parts[] = $part;
                }
                $content = $parts;
            }
            $messages[] = ['role' => $role, 'content' => $content];
        }
    }
    if (!$messages) $messages[] = ['role' => 'user', 'content' => ''];
    return $messages;
}

function ai_api_response_text_from_chat(array $chat): string {
    $text = '';
    foreach ((array)($chat['choices'] ?? []) as $choice) $text .= (string)(((array)($choice['message'] ?? []))['content'] ?? '');
    return $text;
}

function ai_api_handle_responses(): void {
    ai_api_preflight_or_continue();
    ai_api_compat_non_post_probe('/v1/responses');
    try {
    $body = ai_api_read_json_body();
    $chatBody = $body;
    foreach (['input', 'instructions', 'previous_response_id', 'store', 'metadata', 'reasoning', 'truncation'] as $field) unset($chatBody[$field]);
    $chatBody['messages'] = ai_api_messages_from_responses_input($body);
    $result = ai_api_execute_chat_completion($chatBody, true);
    $chat = $result['data'];
    $text = ai_api_response_text_from_chat($chat);
    $result['data'] = [
        'id' => 'resp_kd_' . bin2hex(random_bytes(12)),
        'object' => 'response',
        'created_at' => (int)($chat['created'] ?? time()),
        'status' => 'completed',
        'model' => (string)($chat['model'] ?? ($body['model'] ?? '')),
        'output' => [[
            'id' => 'msg_' . bin2hex(random_bytes(8)),
            'type' => 'message',
            'status' => 'completed',
            'role' => 'assistant',
            'content' => [['type' => 'output_text', 'text' => $text, 'annotations' => []]],
        ]],
        'output_text' => $text,
        'usage' => $chat['usage'] ?? null,
    ];
    ai_api_emit_openai_json($result);
    } catch (Throwable $e) {
        ai_api_openai_error('server_error', $e->getMessage(), 500);
    }
}

function ai_api_admin_user_map(): array {
    $map = [];
    if (function_exists('users_all')) {
        foreach (users_all() as $user) {
            $id = (int)($user['id'] ?? 0);
            if ($id <= 0) continue;
            $map[$id] = [
                'id' => $id,
                'username' => (string)($user['username'] ?? ''),
                'nickname' => (string)($user['nickname'] ?? ($user['username'] ?? '')),
                'role' => (string)($user['role'] ?? 'user'),
                'email' => (string)($user['email'] ?? ''),
            ];
        }
    }
    return $map;
}

function ai_api_admin_user_brief(int $userId, array $users): array {
    $u = $users[$userId] ?? null;
    if (!$u) return ['id' => $userId, 'username' => 'UID ' . $userId, 'nickname' => 'UID ' . $userId, 'role' => 'unknown', 'email' => ''];
    return [
        'id' => $userId,
        'username' => (string)($u['username'] ?? ''),
        'nickname' => (string)($u['nickname'] ?? ($u['username'] ?? '')),
        'role' => (string)($u['role'] ?? 'user'),
        'email' => (string)($u['email'] ?? ''),
    ];
}

function ai_api_admin_public_wallet(array $row, array $users): array {
    $uid = (int)($row['user_id'] ?? 0);
    $safe = ai_api_wallet_for_user($uid);
    $safe['user'] = ai_api_admin_user_brief($uid, $users);
    return $safe;
}

function ai_api_admin_public_key(array $row, array $users): array {
    $uid = (int)($row['user_id'] ?? 0);
    $safe = ai_api_public_key_row($row);
    $safe['user_id'] = $uid;
    $safe['user'] = ai_api_admin_user_brief($uid, $users);
    return $safe;
}

function ai_api_admin_public_order(array $row, array $users): array {
    $uid = (int)($row['user_id'] ?? 0);
    $safe = ai_api_public_order($row);
    $safe['user'] = ai_api_admin_user_brief($uid, $users);
    return $safe;
}

function ai_api_admin_public_usage(array $row, array $users): array {
    $uid = (int)($row['user_id'] ?? 0);
    $safe = $row;
    $safe['user'] = ai_api_admin_user_brief($uid, $users);
    unset($safe['key_hash']);
    return $safe;
}

function ai_api_admin_summary(): array {
    $users = ai_api_admin_user_map();
    $wallets = ai_api_wallets_all();
    $usage = ai_api_store_read(AI_API_USAGE_FILE);
    $orders = ai_api_orders_all();
    $keys = ai_api_keys_all();
    $ledger = ai_api_store_read(AI_API_LEDGER_FILE);
    $redeemSummary = ai_api_admin_redeem_summary();
    $developerStats = function_exists('ai_api_developer_application_stats') ? ai_api_developer_application_stats() : ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'granted_tokens' => 0];

    $totalBalance = 0; $totalUsed = 0; $activeKeys = 0; $pendingOrders = 0; $paidOrders = 0; $rejectedOrders = 0; $revenue = 0.0;
    foreach ($wallets as $w) {
        $totalBalance += (int)($w['balance_tokens'] ?? 0);
        $totalUsed += (int)($w['total_used_tokens'] ?? 0);
    }
    foreach ($keys as $k) if (($k['status'] ?? 'active') === 'active') $activeKeys++;
    foreach ($orders as $o) {
        $status = (string)($o['status'] ?? '');
        if ($status === 'pending') $pendingOrders++;
        if ($status === 'paid') { $paidOrders++; $revenue += (float)($o['price'] ?? 0); }
        if ($status === 'rejected' || $status === 'cancelled') $rejectedOrders++;
    }

    usort($orders, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    usort($usage, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    usort($wallets, fn($a, $b) => ((int)($b['balance_tokens'] ?? 0)) <=> ((int)($a['balance_tokens'] ?? 0)));
    usort($keys, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    usort($ledger, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));

    return [
        'base_url' => ai_api_base_url(),
        'total_balance_tokens' => $totalBalance,
        'total_used_tokens' => $totalUsed,
        'total_granted_tokens' => array_sum(array_map(fn($w) => (int)($w['total_granted_tokens'] ?? 0), $wallets)),
        'active_keys' => $activeKeys,
        'total_keys' => count($keys),
        'pending_orders' => $pendingOrders,
        'paid_orders' => $paidOrders,
        'rejected_orders' => $rejectedOrders,
        'revenue' => round($revenue, 2),
        'wallet_count' => count($wallets),
        'orders' => array_map(fn($row) => ai_api_admin_public_order($row, $users), array_slice($orders, 0, 120)),
        'recent_usage' => array_map(fn($row) => ai_api_admin_public_usage($row, $users), array_slice($usage, 0, 120)),
        'wallets' => array_map(fn($row) => ai_api_admin_public_wallet($row, $users), array_slice($wallets, 0, 120)),
        'keys' => array_map(fn($row) => ai_api_admin_public_key($row, $users), array_slice($keys, 0, 120)),
        'redeem_summary' => $redeemSummary,
        'developer_applications' => $developerStats,
        'ledger' => array_slice($ledger, 0, 120),
    ];
}
?>
