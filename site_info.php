<?php
session_start();
date_default_timezone_set('Asia/Shanghai');

const SITE_INFO_FILE = __DIR__ . '/site_info.json';
const DB_CONFIG_FILE = __DIR__ . '/db_config.php';
const DB_CONFIG_BACKUP_FILE = __DIR__ . '/config/db_config.last.php';
const PAYMENT_CONFIG_FILE = __DIR__ . '/config/payment_config.php';
const PAYMENT_CONFIG_BACKUP_FILE = __DIR__ . '/config/payment_config.last.php';
const ADMIN_SOURCE_FILE = __DIR__ . '/db.php';

function site_info_respond(array $payload, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function site_info_input(): array {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        return $_GET;
    }
    if (!empty($_POST)) {
        return $_POST;
    }
    $raw = file_get_contents('php://input');
    $decoded = json_decode((string)$raw, true);
    return is_array($decoded) ? $decoded : [];
}

function site_info_defaults(): array {
    return [
        'site_name' => '小付music',
        'site_kicker' => 'Music Center',
        'site_tagline' => '灵动音源库',
        'hero_title' => '把歌单、收藏与歌词都收进一个更顺手的音乐空间',
        'hero_subtitle' => '支持本地音源上传、歌曲详情页、歌词联动展示、用户注册收藏与推荐。后台已支持更丰富的音频格式上传。',
        'site_description' => '一个支持本地音源上传、歌单管理、歌词联动与用户互动的音乐站点。',
        'site_copyright' => '© 2026 小付music. All rights reserved.',
        'payment_site_name' => '小付music',
        'updated_at' => date('Y-m-d H:i:s'),
    ];
}

function load_site_info_config(): array {
    $defaults = site_info_defaults();
    if (!is_file(SITE_INFO_FILE)) {
        return $defaults;
    }
    $raw = @file_get_contents(SITE_INFO_FILE);
    $decoded = json_decode((string)$raw, true);
    if (!is_array($decoded)) {
        return $defaults;
    }
    return array_merge($defaults, $decoded);
}

function save_site_info_config(array $incoming): array {
    $current = load_site_info_config();
    $next = array_merge($current, [
        'site_name' => trim((string)($incoming['site_name'] ?? $current['site_name'])),
        'site_kicker' => trim((string)($incoming['site_kicker'] ?? $current['site_kicker'])),
        'site_tagline' => trim((string)($incoming['site_tagline'] ?? $current['site_tagline'])),
        'hero_title' => trim((string)($incoming['hero_title'] ?? $current['hero_title'])),
        'hero_subtitle' => trim((string)($incoming['hero_subtitle'] ?? $current['hero_subtitle'])),
        'site_description' => trim((string)($incoming['site_description'] ?? $current['site_description'])),
        'site_copyright' => trim((string)($incoming['site_copyright'] ?? $current['site_copyright'])),
        'payment_site_name' => trim((string)($incoming['payment_site_name'] ?? $current['payment_site_name'])),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    foreach (['site_name', 'site_kicker', 'site_tagline', 'hero_title', 'hero_subtitle', 'site_description', 'site_copyright', 'payment_site_name'] as $field) {
        if ($next[$field] === '') {
            $next[$field] = site_info_defaults()[$field];
        }
    }

    file_put_contents(SITE_INFO_FILE, json_encode($next, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $next;
}

function payment_config_defaults(): array {
    return [
        'provider' => 'epay',
        'epay' => [
            'apiurl' => 'https://mzf.yuvps.com/xpay/epay/',
            'pid' => '10478',
            'key' => 'fm74EyWDJZBbvwXre8r3',
            'sign_type' => 'MD5',
        ],
        'codepay' => [
            'apiurl' => '',
            'pid' => '',
            'key' => '',
            'sign_type' => 'MD5',
        ],
        'updated_at' => date('Y-m-d H:i:s'),
    ];
}

function payment_provider_label(string $provider): string {
    return $provider === 'codepay' ? '码支付' : '易支付';
}

function normalize_payment_provider(mixed $value): string {
    $provider = strtolower(trim((string)$value));
    return in_array($provider, ['epay', 'codepay'], true) ? $provider : 'epay';
}

function normalize_payment_sign_type(mixed $value): string {
    $type = strtoupper(trim((string)$value));
    return $type !== '' ? $type : 'MD5';
}

function clean_payment_apiurl(mixed $value): string {
    $url = trim((string)$value);
    if ($url === '') {
        return '';
    }
    return rtrim($url, '/') . '/';
}

function merge_payment_config(array $defaults, array $loaded): array {
    $result = $defaults;
    $result['provider'] = normalize_payment_provider($loaded['provider'] ?? $defaults['provider']);
    foreach (['epay', 'codepay'] as $provider) {
        $source = is_array($loaded[$provider] ?? null) ? $loaded[$provider] : [];
        $result[$provider] = array_merge($defaults[$provider], $source);
        $result[$provider]['apiurl'] = clean_payment_apiurl($result[$provider]['apiurl'] ?? '');
        $result[$provider]['pid'] = trim((string)($result[$provider]['pid'] ?? ''));
        $result[$provider]['key'] = trim((string)($result[$provider]['key'] ?? ''));
        $result[$provider]['sign_type'] = normalize_payment_sign_type($result[$provider]['sign_type'] ?? 'MD5');
    }
    $result['updated_at'] = trim((string)($loaded['updated_at'] ?? $defaults['updated_at'])) ?: $defaults['updated_at'];
    return $result;
}

function read_payment_config_file(): array {
    $defaults = payment_config_defaults();
    if (!is_file(PAYMENT_CONFIG_FILE)) {
        return $defaults;
    }
    $loaded = require PAYMENT_CONFIG_FILE;
    if (!is_array($loaded)) {
        return $defaults;
    }
    return merge_payment_config($defaults, $loaded);
}

function normalize_payment_gateway_group(array $incoming, string $provider, array $current): array {
    return [
        'apiurl' => clean_payment_apiurl($incoming['payment_' . $provider . '_apiurl'] ?? $current['apiurl'] ?? ''),
        'pid' => trim((string)($incoming['payment_' . $provider . '_pid'] ?? $current['pid'] ?? '')),
        'key' => trim((string)($incoming['payment_' . $provider . '_key'] ?? $current['key'] ?? '')),
        'sign_type' => normalize_payment_sign_type($incoming['payment_' . $provider . '_sign_type'] ?? $current['sign_type'] ?? 'MD5'),
    ];
}

function normalize_payment_config(array $incoming): array {
    $current = read_payment_config_file();
    return [
        'provider' => normalize_payment_provider($incoming['payment_provider'] ?? $current['provider']),
        'epay' => normalize_payment_gateway_group($incoming, 'epay', $current['epay'] ?? []),
        'codepay' => normalize_payment_gateway_group($incoming, 'codepay', $current['codepay'] ?? []),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
}

function render_payment_config_php(array $config): string {
    return "<?php\nreturn " . var_export($config, true) . ";\n";
}

function save_payment_config_file(array $config): void {
    if (!is_dir(dirname(PAYMENT_CONFIG_FILE))) {
        mkdir(dirname(PAYMENT_CONFIG_FILE), 0777, true);
    }
    if (is_file(PAYMENT_CONFIG_FILE)) {
        @copy(PAYMENT_CONFIG_FILE, PAYMENT_CONFIG_BACKUP_FILE);
    }
    file_put_contents(PAYMENT_CONFIG_FILE, render_payment_config_php($config));
}

function admin_password_from_source(): string {
    $raw = @file_get_contents(ADMIN_SOURCE_FILE);
    if (!is_string($raw) || $raw === '') {
        return '';
    }
    if (preg_match("/define\s*\(\s*'ADMIN_PASSWORD'\s*,\s*'([^']*)'\s*\)/", $raw, $matches)) {
        return (string)$matches[1];
    }
    if (preg_match('/define\s*\(\s*"ADMIN_PASSWORD"\s*,\s*"([^"]*)"\s*\)/', $raw, $matches)) {
        return (string)$matches[1];
    }
    return '';
}

function site_info_is_admin(array $data): bool {
    if (!empty($_SESSION['is_admin'])) {
        return true;
    }
    $submitted = trim((string)($data['admin_password'] ?? ''));
    $actual = admin_password_from_source();
    return $submitted !== '' && $actual !== '' && hash_equals($actual, $submitted);
}

function require_site_admin(array $data): void {
    if (!site_info_is_admin($data)) {
        site_info_respond(['ok' => false, 'msg' => '需要管理员权限或正确的后台密码'], 403);
    }
}

function read_db_config_file(): array {
    $defaults = [
        'host' => 'localhost',
        'port' => 3306,
        'name' => '',
        'user' => '',
        'pass' => '',
    ];
    if (!is_file(DB_CONFIG_FILE)) {
        return $defaults;
    }
    $raw = @file_get_contents(DB_CONFIG_FILE);
    if (!is_string($raw) || $raw === '') {
        return $defaults;
    }

    $extractString = function (string $constant, string $fallback = '') use ($raw): string {
        if (preg_match("/define\s*\(\s*'" . preg_quote($constant, '/') . "'\s*,\s*'([^']*)'\s*\)/", $raw, $matches)) {
            return stripcslashes((string)$matches[1]);
        }
        if (preg_match('/define\s*\(\s*"' . preg_quote($constant, '/') . '"\s*,\s*"([^"]*)"\s*\)/', $raw, $matches)) {
            return stripcslashes((string)$matches[1]);
        }
        return $fallback;
    };

    $port = $defaults['port'];
    if (preg_match("/define\s*\(\s*'DB_PORT'\s*,\s*(\d+)\s*\)/", $raw, $matches) || preg_match('/define\s*\(\s*"DB_PORT"\s*,\s*(\d+)\s*\)/', $raw, $matches)) {
        $port = (int)$matches[1];
    }

    return [
        'host' => $extractString('DB_HOST', $defaults['host']),
        'port' => $port > 0 ? $port : $defaults['port'],
        'name' => $extractString('DB_NAME', $defaults['name']),
        'user' => $extractString('DB_USER', $defaults['user']),
        'pass' => $extractString('DB_PASS', $defaults['pass']),
    ];
}

function normalize_db_config(array $incoming): array {
    $current = read_db_config_file();
    $host = trim((string)($incoming['db_host'] ?? $incoming['host'] ?? $current['host']));
    $port = (int)($incoming['db_port'] ?? $incoming['port'] ?? $current['port']);
    $name = trim((string)($incoming['db_name'] ?? $incoming['name'] ?? $current['name']));
    $user = trim((string)($incoming['db_user'] ?? $incoming['user'] ?? $current['user']));
    $pass = (string)($incoming['db_pass'] ?? $incoming['pass'] ?? $current['pass']);

    if ($host === '') $host = 'localhost';
    if ($port <= 0 || $port > 65535) $port = 3306;

    return [
        'host' => $host,
        'port' => $port,
        'name' => $name,
        'user' => $user,
        'pass' => $pass,
    ];
}

function validate_db_config(array $config): void {
    foreach (['host', 'name', 'user'] as $required) {
        if (trim((string)$config[$required]) === '') {
            site_info_respond(['ok' => false, 'msg' => '数据库配置不完整，请至少填写主机、库名和用户名'], 400);
        }
    }
}

function render_db_config_php(array $config): string {
    return "<?php\n"
        . "// MySQL 配置（可在后台的‘网站配置’中修改）\n"
        . "define('DB_HOST', " . var_export((string)$config['host'], true) . ");\n"
        . "define('DB_PORT', " . (int)$config['port'] . ");\n"
        . "define('DB_NAME', " . var_export((string)$config['name'], true) . ");\n"
        . "define('DB_USER', " . var_export((string)$config['user'], true) . ");\n"
        . "define('DB_PASS', " . var_export((string)$config['pass'], true) . ");\n";
}

function save_db_config_file(array $config): void {
    if (!is_dir(dirname(DB_CONFIG_BACKUP_FILE))) {
        mkdir(dirname(DB_CONFIG_BACKUP_FILE), 0777, true);
    }
    if (is_file(DB_CONFIG_FILE)) {
        @copy(DB_CONFIG_FILE, DB_CONFIG_BACKUP_FILE);
    }
    file_put_contents(DB_CONFIG_FILE, render_db_config_php($config));
}

function test_db_connection(array $config): array {
    validate_db_config($config);
    if (!class_exists('PDO')) {
        return ['ok' => false, 'msg' => '当前环境未启用 PDO，无法测试数据库连接'];
    }
    if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
        return ['ok' => false, 'msg' => '当前环境未启用 PDO MySQL 驱动'];
    }
    $dsn = 'mysql:host=' . $config['host'] . ';port=' . (int)$config['port'] . ';dbname=' . $config['name'] . ';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $pdo->query('SELECT 1');
        return ['ok' => true, 'msg' => '数据库连接测试成功'];
    } catch (Throwable $e) {
        return ['ok' => false, 'msg' => '数据库连接失败：' . $e->getMessage()];
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? 'public';
$data = site_info_input();

if ($method === 'GET' && ($action === 'public' || $action === '')) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(load_site_info_config(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'admin_get') {
    require_site_admin($data);
    $payment = read_payment_config_file();
    site_info_respond([
        'ok' => true,
        'data' => [
            'public' => load_site_info_config(),
            'db' => read_db_config_file(),
            'payment' => $payment,
            'auth' => [
                'session_admin' => !empty($_SESSION['is_admin']),
            ],
        ],
    ]);
}

if ($action === 'save_public') {
    require_site_admin($data);
    $saved = save_site_info_config($data);
    site_info_respond(['ok' => true, 'msg' => '网站信息已保存', 'data' => $saved]);
}

if ($action === 'save_payment') {
    require_site_admin($data);
    $config = normalize_payment_config($data);
    save_payment_config_file($config);
    $provider = $config['provider'];
    site_info_respond([
        'ok' => true,
        'msg' => '支付配置已保存，当前通道：' . payment_provider_label($provider),
        'data' => $config,
    ]);
}

if ($action === 'test_database') {
    require_site_admin($data);
    $config = normalize_db_config($data);
    $result = test_db_connection($config);
    site_info_respond($result, $result['ok'] ? 200 : 400);
}

if ($action === 'save_database') {
    require_site_admin($data);
    $config = normalize_db_config($data);
    validate_db_config($config);
    save_db_config_file($config);
    site_info_respond(['ok' => true, 'msg' => '数据库配置已写入 db_config.php', 'data' => $config]);
}

site_info_respond(['ok' => false, 'msg' => '未知操作'], 400);
