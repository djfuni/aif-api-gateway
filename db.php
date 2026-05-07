<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('AIFAPISESSID');
    session_start();
}
date_default_timezone_set('Asia/Shanghai');

require_once __DIR__ . '/db_config.php';

define('ADMIN_PASSWORD', 'yubaoremix666');
define('DATA_DIR', __DIR__ . '/data');
define('USERS_FILE', DATA_DIR . '/users.json');
define('FAVORITES_FILE', DATA_DIR . '/favorites.json');
define('RECOMMENDATIONS_FILE', DATA_DIR . '/recommendations.json');
define('AUTH_TOKENS_FILE', DATA_DIR . '/auth_tokens.json');
define('AUTH_COOKIE_NAME', 'aifapi_remember');
define('REPORTS_FILE', DATA_DIR . '/reports.json');
define('USER_LOGS_FILE', DATA_DIR . '/user_logs.json');
define('PLAY_HISTORY_FILE', DATA_DIR . '/play_history.json');
define('NOTIFICATIONS_FILE', DATA_DIR . '/notifications.json');
define('FORUM_BOOKMARKS_FILE', DATA_DIR . '/forum_bookmarks.json');
define('EXTERNAL_ACCOUNTS_FILE', DATA_DIR . '/external_accounts.json');

define('CHECKIN_REWARD_POINTS', 3);
define('THREAD_REWARD_POINTS', 2);
define('REPLY_REWARD_POINTS', 1);
define('RECOMMEND_REWARD_POINTS', 1);

define('APP_JSON_ROW_STORE_TABLE', 'app_api_json_store_rows');
define('APP_JSON_DOCUMENT_TABLE', 'app_api_json_documents');
define('APP_DB_MIGRATION_MARKER_KEY', '_system/api_legacy_json_migrated_v2');

if (!function_exists('mb_strlen')) {
    function mb_strlen(string $string, ?string $encoding = null): int { return strlen($string); }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $string, int $start, ?int $length = null, ?string $encoding = null): string {
        return $length === null ? substr($string, $start) : substr($string, $start, $length);
    }
}
if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $string, ?string $encoding = null): string { return strtolower($string); }
}
if (!function_exists('mb_strpos')) {
    function mb_strpos(string $haystack, string $needle, int $offset = 0, ?string $encoding = null): int|false { return strpos($haystack, $needle, $offset); }
}
if (!function_exists('mb_stripos')) {
    function mb_stripos(string $haystack, string $needle, int $offset = 0, ?string $encoding = null): int|false { return stripos($haystack, $needle, $offset); }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool { return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0; }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool { return $needle === '' || substr($haystack, -strlen($needle)) === $needle; }
}

if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0777, true);
}

function json_out(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function input_data(): array {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') return $_GET;
    if (!empty($_POST)) return $_POST;
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    return is_array($json) ? $json : [];
}

function db_store_key_from_path(string $file): string {
    $normalized = str_replace('\\', '/', $file);
    $root = str_replace('\\', '/', __DIR__) . '/';
    if (str_starts_with($normalized, $root)) {
        $normalized = substr($normalized, strlen($root));
    }
    return ltrim($normalized, '/');
}

function db_json_encode(mixed $value): string {
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function db_json_decode(?string $payload, mixed $fallback = []): mixed {
    if (!is_string($payload) || $payload === '') return $fallback;
    $data = json_decode($payload, true);
    return json_last_error() === JSON_ERROR_NONE ? $data : $fallback;
}

function db_connection_failed(\Throwable $e): never {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("MySQL 连接失败，请检查 db_config.php 中的数据库配置以及 PDO_MYSQL 扩展是否启用。
" . $e->getMessage());
}

function db_server_dsn(): string {
    return 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4';
}

function db_database_dsn(): string {
    return 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
}

function db_create_pdo(string $dsn): PDO {
    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function db_ensure_schema(PDO $pdo): void {
    $pdo->exec('CREATE TABLE IF NOT EXISTS `' . APP_JSON_ROW_STORE_TABLE . '` (
'
        . '  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
'
        . '  `store_key` VARCHAR(191) NOT NULL,
'
        . '  `sort_order` INT NOT NULL DEFAULT 0,
'
        . '  `payload_json` LONGTEXT NOT NULL,
'
        . '  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
'
        . '  PRIMARY KEY (`id`),
'
        . '  KEY `idx_store_key_sort_order` (`store_key`, `sort_order`)
'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $pdo->exec('CREATE TABLE IF NOT EXISTS `' . APP_JSON_DOCUMENT_TABLE . '` (
'
        . '  `store_key` VARCHAR(191) NOT NULL,
'
        . '  `payload_json` LONGTEXT NOT NULL,
'
        . '  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
'
        . '  PRIMARY KEY (`store_key`)
'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

function db_legacy_row_store_files(): array {
    return [
        'data/users.json',
        'data/favorites.json',
        'data/recommendations.json',
        'data/auth_tokens.json',
        'data/reports.json',
        'data/user_logs.json',
        'data/play_history.json',
        'data/notifications.json',
        'data/forum_bookmarks.json',
        'data/forum_categories.json',
        'data/forum_threads.json',
        'data/forum_posts.json',
        'data/forum_likes.json',
        'data/external_accounts.json',
        'data/social/friend_requests.json',
        'data/social/friendships.json',
        'data/social/direct_conversations.json',
        'data/social/direct_messages.json',
        'data/social/groups.json',
        'data/social/group_members.json',
        'data/social/group_messages.json',
        'data/social/attachments.json',
    ];
}

function db_legacy_document_files(): array {
    return [
        'config/playlists.json',
        'config/announcements.json',
        'config/webdav.json',
        'config/onedrive.json',
        'data/lyrics_index.json',
        'data/search_api_private.php',
        'data/points_settings.json',
    ];
}

function db_read_legacy_row_store(string $key): array {
    $path = __DIR__ . '/' . ltrim($key, '/');
    if (!is_file($path)) return [];
    $raw = @file_get_contents($path);
    $data = json_decode((string)$raw, true);
    return is_array($data) ? array_values($data) : [];
}

function db_read_legacy_document(string $key): mixed {
    $path = __DIR__ . '/' . ltrim($key, '/');
    if ($key === 'data/search_api_private.php') {
        if (!is_file($path)) return null;
        $loaded = include $path;
        return is_array($loaded) ? $loaded : null;
    }
    if (!is_file($path)) return null;
    $raw = @file_get_contents($path);
    $data = json_decode((string)$raw, true);
    return json_last_error() === JSON_ERROR_NONE ? $data : null;
}

function db_write_store_rows(string $storeKey, array $rows): void {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmtDelete = $pdo->prepare('DELETE FROM `' . APP_JSON_ROW_STORE_TABLE . '` WHERE `store_key` = ?');
        $stmtDelete->execute([$storeKey]);
        if ($rows) {
            $stmtInsert = $pdo->prepare('INSERT INTO `' . APP_JSON_ROW_STORE_TABLE . '` (`store_key`,`sort_order`,`payload_json`) VALUES (?,?,?)');
            foreach (array_values($rows) as $i => $row) {
                $stmtInsert->execute([$storeKey, $i, db_json_encode($row)]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function db_read_store_rows(string $storeKey): array {
    $stmt = db()->prepare('SELECT `payload_json` FROM `' . APP_JSON_ROW_STORE_TABLE . '` WHERE `store_key` = ? ORDER BY `sort_order` ASC, `id` ASC');
    $stmt->execute([$storeKey]);
    $rows = [];
    foreach ($stmt->fetchAll() as $item) {
        $decoded = db_json_decode((string)($item['payload_json'] ?? ''), null);
        if (is_array($decoded)) $rows[] = $decoded;
    }
    return $rows;
}

function db_write_document(string $storeKey, mixed $value): void {
    $stmt = db()->prepare('INSERT INTO `' . APP_JSON_DOCUMENT_TABLE . '` (`store_key`,`payload_json`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `payload_json` = VALUES(`payload_json`)');
    $stmt->execute([$storeKey, db_json_encode($value)]);
}

function db_read_document(string $storeKey, mixed $fallback = null): mixed {
    $stmt = db()->prepare('SELECT `payload_json` FROM `' . APP_JSON_DOCUMENT_TABLE . '` WHERE `store_key` = ? LIMIT 1');
    $stmt->execute([$storeKey]);
    $row = $stmt->fetch();
    if (!$row) return $fallback;
    return db_json_decode((string)($row['payload_json'] ?? ''), $fallback);
}

function db_migrate_legacy_files_if_needed(PDO $pdo): void {
    $marker = db_read_document(APP_DB_MIGRATION_MARKER_KEY, null);
    if (is_array($marker) && !empty($marker['done'])) return;

    foreach (db_legacy_row_store_files() as $key) {
        db_write_store_rows($key, db_read_legacy_row_store($key));
    }
    foreach (db_legacy_document_files() as $key) {
        $value = db_read_legacy_document($key);
        if ($value === null) {
            $value = str_ends_with($key, '.json') ? [] : [];
        }
        db_write_document($key, $value);
    }
    db_write_document(APP_DB_MIGRATION_MARKER_KEY, [
        'done' => true,
        'migrated_at' => date('Y-m-d H:i:s'),
        'source' => 'legacy-files',
    ]);
}


function db_force_migrate_legacy_files(): void {
    $pdo = db();
    db_write_document(APP_DB_MIGRATION_MARKER_KEY, ['done' => false, 'requested_at' => date('Y-m-d H:i:s')]);
    db_migrate_legacy_files_if_needed($pdo);
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    try {
        $pdo = db_create_pdo(db_database_dsn());
    } catch (Throwable $e) {
        try {
            $server = db_create_pdo(db_server_dsn());
            $server->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', DB_NAME) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo = db_create_pdo(db_database_dsn());
        } catch (Throwable $inner) {
            db_connection_failed($inner);
        }
    }

    db_ensure_schema($pdo);
    db_migrate_legacy_files_if_needed($pdo);
    return $pdo;
}

function read_store(string $file): array {
    return db_read_store_rows(db_store_key_from_path($file));
}

function write_store(string $file, array $data): void {
    db_write_store_rows(db_store_key_from_path($file), array_values($data));
}

function next_id(array $rows): int {
    $max = 0;
    foreach ($rows as $row) $max = max($max, (int)($row['id'] ?? 0));
    return $max + 1;
}

function user_level_meta(int $points): array {
    $steps = [
        ['level' => 1, 'title' => '新手听众', 'min' => 0],
        ['level' => 2, 'title' => '常驻乐迷', 'min' => 5],
        ['level' => 3, 'title' => '互动达人', 'min' => 12],
        ['level' => 4, 'title' => '社区玩家', 'min' => 24],
        ['level' => 5, 'title' => '核心用户', 'min' => 40],
        ['level' => 6, 'title' => '传说站友', 'min' => 80],
    ];
    $current = $steps[0];
    $next = null;
    foreach ($steps as $idx => $step) {
        if ($points >= (int)$step['min']) {
            $current = $step;
            $next = $steps[$idx + 1] ?? null;
        }
    }
    $current['next_min'] = $next ? (int)$next['min'] : (int)$current['min'];
    $current['progress_percent'] = $next
        ? (int)max(0, min(100, round((($points - (int)$current['min']) / max(1, ((int)$next['min'] - (int)$current['min']))) * 100)))
        : 100;
    return $current;
}

function normalize_tags($tags): array {
    if (is_string($tags)) {
        $tags = preg_split('/[，,\n]+/u', $tags) ?: [];
    }
    if (!is_array($tags)) return [];
    $result = [];
    foreach ($tags as $tag) {
        $tag = trim((string)$tag);
        if ($tag === '') continue;
        $result[] = mb_substr($tag, 0, 12, 'UTF-8');
    }
    return array_values(array_unique($result));
}

function user_defaults(array $user = []): array {
    $points = (int)($user['points'] ?? 0);
    $defaults = [
        'id' => 0,
        'username' => '',
        'nickname' => '',
        'email' => '',
        'email_verified_at' => '',
        'password_hash' => '',
        'role' => 'user',
        'status' => 'active',
        'avatar_url' => '',
        'bio' => '',
        'gender' => '保密',
        'city' => '',
        'birthday' => '',
        'tags' => [],
        'points' => 0,
        'level' => 1,
        'level_title' => '新手听众',
        'level_progress' => 0,
        'next_level_points' => 5,
        'last_checkin_date' => '',
        'checkin_count' => 0,
        'ban_until' => '',
        'ban_reason' => '',
        'referral_code' => '',
        'referred_by' => '',
        'invite_rewarded_at' => '',
        'monthly_card_until' => '',
        'monthly_card_last_bonus_date' => '',
        'vip_until' => '',
        'vip_plan' => '',
        'ai_daily_free_used' => 0,
        'ai_daily_free_date' => date('Y-m-d'),
        'total_ai_generations' => 0,
        'ai_reasoner_daily_bonus' => 0,
        'ai_reasoner_extra_credits' => 0,
        'ai_reasoner_daily_used' => 0,
        'ai_reasoner_daily_date' => date('Y-m-d'),
        'ai_reasoner_last_used_at' => '',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    $merged = array_merge($defaults, $user);
    $merged['nickname'] = trim((string)($merged['nickname'] ?: $merged['username']));
    $merged['tags'] = normalize_tags($merged['tags']);
    $merged['points'] = (int)$merged['points'];
    $merged['referral_code'] = trim((string)($merged['referral_code'] ?? ''));
    if ($merged['referral_code'] === '' && (int)($merged['id'] ?? 0) > 0) {
        $merged['referral_code'] = 'U' . (int)$merged['id'];
    }
    $merged['referred_by'] = trim((string)($merged['referred_by'] ?? ''));
    $merged['invite_rewarded_at'] = trim((string)($merged['invite_rewarded_at'] ?? ''));
    $merged['monthly_card_until'] = trim((string)($merged['monthly_card_until'] ?? ''));
    $merged['monthly_card_last_bonus_date'] = trim((string)($merged['monthly_card_last_bonus_date'] ?? ''));
    $merged['vip_until'] = trim((string)($merged['vip_until'] ?? ''));
    $merged['vip_plan'] = trim((string)($merged['vip_plan'] ?? ''));
    $merged['ai_daily_free_used'] = max(0, (int)($merged['ai_daily_free_used'] ?? 0));
    $merged['ai_daily_free_date'] = trim((string)($merged['ai_daily_free_date'] ?? date('Y-m-d')));
    if ($merged['ai_daily_free_date'] === '') $merged['ai_daily_free_date'] = date('Y-m-d');
    $merged['total_ai_generations'] = max(0, (int)($merged['total_ai_generations'] ?? 0));
    $merged['ai_reasoner_daily_bonus'] = max(0, (int)($merged['ai_reasoner_daily_bonus'] ?? 0));
    $merged['ai_reasoner_extra_credits'] = max(0, (int)($merged['ai_reasoner_extra_credits'] ?? 0));
    $merged['ai_reasoner_daily_used'] = max(0, (int)($merged['ai_reasoner_daily_used'] ?? 0));
    $merged['ai_reasoner_daily_date'] = trim((string)($merged['ai_reasoner_daily_date'] ?? date('Y-m-d')));
    if ($merged['ai_reasoner_daily_date'] === '') $merged['ai_reasoner_daily_date'] = date('Y-m-d');
    $merged['ai_reasoner_last_used_at'] = trim((string)($merged['ai_reasoner_last_used_at'] ?? ''));
    $levelMeta = user_level_meta((int)$merged['points']);
    $merged['level'] = (int)$levelMeta['level'];
    $merged['level_title'] = (string)$levelMeta['title'];
    $merged['level_progress'] = (int)$levelMeta['progress_percent'];
    $merged['next_level_points'] = (int)$levelMeta['next_min'];
    return $merged;
}

function users_all(): array {
    return array_map('user_defaults', read_store(USERS_FILE));
}

function save_users(array $users): void {
    write_store(USERS_FILE, array_map('user_defaults', array_values($users)));
}


// ---- AI admin upgrade: registration points compatibility helpers ----
// Older packages called these helpers from auth.php but did not define them,
// which caused fatal registration errors. Keep them here because auth.php
// loads db.php first and the helpers can share the existing MySQL/JSON store.
if (!function_exists('points_settings_compat')) {
    function points_settings_compat(): array {
        $defaults = [
            'enabled' => true,
            'rewards' => [
                'register' => 20,
                'daily_checkin' => CHECKIN_REWARD_POINTS,
                'invite' => 30,
                'share' => 5,
            ],
            'costs' => ['default' => 1],
            'advanced_model_multiplier' => 3,
        ];
        try {
            $settings = function_exists('db_read_document') ? db_read_document('data/points_settings.json', null) : null;
            if (!is_array($settings)) {
                $path = __DIR__ . '/data/points_settings.json';
                if (is_file($path)) {
                    $decoded = json_decode((string)file_get_contents($path), true);
                    if (is_array($decoded)) $settings = $decoded;
                }
            }
            if (!is_array($settings)) $settings = [];
            $settings = array_replace_recursive($defaults, $settings);
            if (isset($settings['register_reward_points']) && !isset($settings['rewards']['register'])) {
                $settings['rewards']['register'] = (int)$settings['register_reward_points'];
            }
            return $settings;
        } catch (Throwable $e) {
            return $defaults;
        }
    }
}

if (!function_exists('points_reward_value')) {
    function points_reward_value(string $key): int {
        $settings = points_settings_compat();
        if (empty($settings['enabled'])) return 0;
        $aliases = ['checkin' => 'daily_checkin', 'register_reward' => 'register'];
        $key = $aliases[$key] ?? $key;
        $value = $settings['rewards'][$key] ?? ($key === 'register' ? ($settings['register_reward_points'] ?? 0) : 0);
        return max(0, (int)$value);
    }
}

if (!function_exists('points_apply_register_reward_to_user')) {
    function points_apply_register_reward_to_user(array $user, string $inviteCode = ''): array {
        $reward = points_reward_value('register');
        $row = function_exists('user_defaults') ? user_defaults($user) : $user;
        $row['points'] = max(0, (int)($row['points'] ?? 0) + $reward);
        $row['updated_at'] = date('Y-m-d H:i:s');
        $inviteCode = trim($inviteCode);
        if ($inviteCode !== '') {
            $row['referred_by'] = mb_substr($inviteCode, 0, 80, 'UTF-8');
        }
        return function_exists('user_defaults') ? user_defaults($row) : $row;
    }
}

if (!function_exists('points_reward_inviter_after_register')) {
    function points_reward_inviter_after_register(array $newUser): void {
        $inviteReward = points_reward_value('invite');
        if ($inviteReward <= 0) return;
        $newUserId = (int)($newUser['id'] ?? 0);
        $inviteCode = trim((string)($newUser['referred_by'] ?? ''));
        if ($newUserId <= 0 || $inviteCode === '') return;
        try {
            $users = users_all();
            $newIdx = find_user_index_by_id($users, $newUserId);
            if ($newIdx < 0 || !empty($users[$newIdx]['invite_rewarded_at'])) return;
            $inviterIdx = -1;
            foreach ($users as $idx => $u) {
                if ((int)($u['id'] ?? 0) === $newUserId) continue;
                $code = trim((string)($u['referral_code'] ?? ''));
                $username = trim((string)($u['username'] ?? ''));
                if ($inviteCode === $code || $inviteCode === $username || $inviteCode === (string)((int)($u['id'] ?? 0))) {
                    $inviterIdx = (int)$idx;
                    break;
                }
            }
            if ($inviterIdx < 0) return;
            $now = date('Y-m-d H:i:s');
            $users[$inviterIdx]['points'] = max(0, (int)($users[$inviterIdx]['points'] ?? 0) + $inviteReward);
            $users[$inviterIdx]['updated_at'] = $now;
            $users[$newIdx]['invite_rewarded_at'] = $now;
            save_users($users);
            add_user_log((int)$users[$inviterIdx]['id'], '邀请奖励', '邀请新用户 ' . (string)($users[$newIdx]['username'] ?? $newUserId) . ' +' . $inviteReward);
            if (function_exists('create_notification')) {
                create_notification((int)$users[$inviterIdx]['id'], '邀请奖励到账', '你邀请的新用户已完成注册，获得 +' . $inviteReward . ' 积分。', 'points', 'account.html');
            }
        } catch (Throwable $e) {
            error_log('[points invite reward] ' . $e->getMessage());
        }
    }
}
// ---- /AI admin upgrade helpers ----


function user_reasoner_base_daily_limit(int $level): int {
    $map = [
        1 => 3,
        2 => 5,
        3 => 8,
        4 => 12,
        5 => 20,
        6 => 30,
    ];
    return $map[$level] ?? ($level > 6 ? 30 : 3);
}

function user_reasoner_refresh_daily_state(array $user): array {
    $row = user_defaults($user);
    $today = date('Y-m-d');
    if (($row['ai_reasoner_daily_date'] ?? '') !== $today) {
        $row['ai_reasoner_daily_date'] = $today;
        $row['ai_reasoner_daily_used'] = 0;
    }
    return $row;
}

function user_reasoner_quota_details(array $user): array {
    $row = user_reasoner_refresh_daily_state($user);
    $baseLimit = user_reasoner_base_daily_limit((int)($row['level'] ?? 1));
    $dailyBonus = max(0, (int)($row['ai_reasoner_daily_bonus'] ?? 0));
    $dailyLimit = max(0, $baseLimit + $dailyBonus);
    $dailyUsed = max(0, min($dailyLimit, (int)($row['ai_reasoner_daily_used'] ?? 0)));
    $dailyRemaining = max(0, $dailyLimit - $dailyUsed);
    $extraCredits = max(0, (int)($row['ai_reasoner_extra_credits'] ?? 0));
    return [
        'base_daily_limit' => $baseLimit,
        'daily_bonus' => $dailyBonus,
        'daily_limit' => $dailyLimit,
        'daily_used' => $dailyUsed,
        'daily_remaining' => $dailyRemaining,
        'extra_credits' => $extraCredits,
        'total_remaining' => $dailyRemaining + $extraCredits,
        'daily_date' => (string)($row['ai_reasoner_daily_date'] ?? date('Y-m-d')),
        'last_used_at' => (string)($row['ai_reasoner_last_used_at'] ?? ''),
        'level' => (int)($row['level'] ?? 1),
        'level_title' => (string)($row['level_title'] ?? ''),
    ];
}

function user_reasoner_quota_for_id(int $userId, bool $persist = true): ?array {
    if ($userId <= 0) return null;
    $users = users_all();
    $idx = find_user_index_by_id($users, $userId);
    if ($idx < 0) return null;
    $refreshed = user_reasoner_refresh_daily_state($users[$idx]);
    if ($persist && (
        (string)($users[$idx]['ai_reasoner_daily_date'] ?? '') !== (string)($refreshed['ai_reasoner_daily_date'] ?? '')
        || (int)($users[$idx]['ai_reasoner_daily_used'] ?? 0) !== (int)($refreshed['ai_reasoner_daily_used'] ?? 0)
    )) {
        $users[$idx] = $refreshed;
        $users[$idx]['updated_at'] = date('Y-m-d H:i:s');
        save_users($users);
    }
    return user_reasoner_quota_details($refreshed);
}

function update_user_reasoner_quota(int $userId, array $payload, string $reason = ''): ?array {
    if ($userId <= 0) return null;
    $users = users_all();
    $idx = find_user_index_by_id($users, $userId);
    if ($idx < 0) return null;
    $row = user_reasoner_refresh_daily_state($users[$idx]);
    if (array_key_exists('daily_bonus', $payload)) {
        $row['ai_reasoner_daily_bonus'] = max(0, (int)$payload['daily_bonus']);
    }
    if (array_key_exists('extra_credits', $payload)) {
        $row['ai_reasoner_extra_credits'] = max(0, (int)$payload['extra_credits']);
    }
    if (!empty($payload['reset_today'])) {
        $row['ai_reasoner_daily_date'] = date('Y-m-d');
        $row['ai_reasoner_daily_used'] = 0;
    }
    if (array_key_exists('daily_used', $payload)) {
        $limitAfterUpdate = user_reasoner_base_daily_limit((int)($row['level'] ?? 1)) + max(0, (int)($row['ai_reasoner_daily_bonus'] ?? 0));
        $row['ai_reasoner_daily_used'] = max(0, min(max(0, $limitAfterUpdate), (int)$payload['daily_used']));
    }
    $row['updated_at'] = date('Y-m-d H:i:s');
    $users[$idx] = $row;
    save_users($users);
    if ($reason !== '') add_user_log($userId, 'AI 进阶推理额度调整', $reason);
    return sanitize_user($users[$idx], true);
}

function consume_user_reasoner_quota(int $userId, string $reason = '使用进阶推理模型'): array {
    if ($userId <= 0) throw new RuntimeException('请先登录后使用进阶推理版');
    $users = users_all();
    $idx = find_user_index_by_id($users, $userId);
    if ($idx < 0) throw new RuntimeException('用户不存在');
    $row = user_reasoner_refresh_daily_state($users[$idx]);
    $quota = user_reasoner_quota_details($row);
    if ((int)($quota['daily_remaining'] ?? 0) > 0) {
        $row['ai_reasoner_daily_used'] = (int)($row['ai_reasoner_daily_used'] ?? 0) + 1;
    } elseif ((int)($quota['extra_credits'] ?? 0) > 0) {
        $row['ai_reasoner_extra_credits'] = max(0, (int)($row['ai_reasoner_extra_credits'] ?? 0) - 1);
    } else {
        throw new RuntimeException('今日进阶推理次数已用完，请明天再试或联系管理员补充次数');
    }
    $row['ai_reasoner_last_used_at'] = date('Y-m-d H:i:s');
    $row['updated_at'] = date('Y-m-d H:i:s');
    $users[$idx] = $row;
    save_users($users);
    if ($reason !== '') add_user_log($userId, '使用 AI 进阶推理', $reason);
    return user_reasoner_quota_details($row);
}


function external_provider_catalog(): array {
    return [
        'wechat' => ['key' => 'wechat', 'label' => '微信', 'icon' => 'weixin'],
        'qq' => ['key' => 'qq', 'label' => 'QQ', 'icon' => 'qq'],
        'alipay' => ['key' => 'alipay', 'label' => '支付宝', 'icon' => 'credit-card'],
        'github' => ['key' => 'github', 'label' => 'GitHub', 'icon' => 'github'],
    ];
}

function normalize_external_provider(string $provider): string {
    $provider = trim(mb_strtolower($provider, 'UTF-8'));
    return array_key_exists($provider, external_provider_catalog()) ? $provider : '';
}

function external_provider_meta(string $provider): array {
    $provider = normalize_external_provider($provider);
    $catalog = external_provider_catalog();
    return $catalog[$provider] ?? ['key' => $provider, 'label' => strtoupper($provider), 'icon' => 'plug'];
}

function mask_external_id(string $externalId): string {
    $externalId = trim($externalId);
    $len = strlen($externalId);
    if ($len <= 2) return str_repeat('*', max(1, $len));
    if ($len <= 6) return substr($externalId, 0, 1) . str_repeat('*', max(1, $len - 2)) . substr($externalId, -1);
    return substr($externalId, 0, 3) . str_repeat('*', max(4, $len - 5)) . substr($externalId, -2);
}

function external_account_defaults(array $row = []): array {
    $provider = normalize_external_provider((string)($row['provider'] ?? ''));
    $meta = external_provider_meta($provider);
    $defaults = [
        'id' => 0,
        'user_id' => 0,
        'provider' => $provider,
        'provider_label' => $meta['label'],
        'provider_icon' => $meta['icon'],
        'external_id' => '',
        'display_name' => '',
        'avatar_url' => '',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'last_login_at' => '',
    ];
    $merged = array_merge($defaults, $row);
    $meta = external_provider_meta((string)$merged['provider']);
    $merged['provider'] = normalize_external_provider((string)$merged['provider']);
    $merged['provider_label'] = $meta['label'];
    $merged['provider_icon'] = $meta['icon'];
    $merged['external_id'] = trim((string)$merged['external_id']);
    $merged['display_name'] = trim((string)$merged['display_name']);
    $merged['avatar_url'] = trim((string)$merged['avatar_url']);
    return $merged;
}

function external_accounts_all(): array {
    return array_map('external_account_defaults', read_store(EXTERNAL_ACCOUNTS_FILE));
}

function save_external_accounts(array $rows): void {
    write_store(EXTERNAL_ACCOUNTS_FILE, array_map('external_account_defaults', array_values($rows)));
}

function external_accounts_for_user(int $userId): array {
    $items = [];
    foreach (external_accounts_all() as $row) {
        if ((int)($row['user_id'] ?? 0) !== $userId) continue;
        $row['masked_external_id'] = mask_external_id((string)($row['external_id'] ?? ''));
        $items[] = $row;
    }
    usort($items, fn($a, $b) => strcmp((string)($a['provider'] ?? ''), (string)($b['provider'] ?? '')));
    return $items;
}

function find_external_account(string $provider, string $externalId): ?array {
    $provider = normalize_external_provider($provider);
    $externalId = trim($externalId);
    if ($provider === '' || $externalId === '') return null;
    foreach (external_accounts_all() as $row) {
        if (($row['provider'] ?? '') === $provider && ($row['external_id'] ?? '') === $externalId) return $row;
    }
    return null;
}

function find_external_account_for_user(int $userId, string $provider): ?array {
    $provider = normalize_external_provider($provider);
    if ($provider === '') return null;
    foreach (external_accounts_all() as $row) {
        if ((int)($row['user_id'] ?? 0) !== $userId) continue;
        if (($row['provider'] ?? '') === $provider) return $row;
    }
    return null;
}

function bind_external_account(int $userId, string $provider, string $externalId, string $displayName = '', string $avatarUrl = ''): array {
    $provider = normalize_external_provider($provider);
    $meta = external_provider_meta($provider);
    $rows = external_accounts_all();
    $matched = -1;
    foreach ($rows as $idx => $row) {
        if ((int)($row['user_id'] ?? 0) !== $userId) continue;
        if (($row['provider'] ?? '') !== $provider) continue;
        $matched = $idx;
        break;
    }
    if ($matched >= 0) {
        $rows[$matched]['external_id'] = trim($externalId);
        if ($displayName !== '') $rows[$matched]['display_name'] = trim($displayName);
        if ($avatarUrl !== '') $rows[$matched]['avatar_url'] = trim($avatarUrl);
        $rows[$matched]['updated_at'] = date('Y-m-d H:i:s');
        $rows[$matched]['last_login_at'] = date('Y-m-d H:i:s');
        save_external_accounts($rows);
        return external_account_defaults($rows[$matched]);
    }
    $new = external_account_defaults([
        'id' => next_id($rows),
        'user_id' => $userId,
        'provider' => $provider,
        'provider_label' => $meta['label'],
        'provider_icon' => $meta['icon'],
        'external_id' => trim($externalId),
        'display_name' => trim($displayName),
        'avatar_url' => trim($avatarUrl),
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'last_login_at' => date('Y-m-d H:i:s'),
    ]);
    $rows[] = $new;
    save_external_accounts($rows);
    return $new;
}

function touch_external_account_login(int $userId, string $provider): void {
    $provider = normalize_external_provider($provider);
    if ($provider === '') return;
    $rows = external_accounts_all();
    $changed = false;
    foreach ($rows as &$row) {
        if ((int)($row['user_id'] ?? 0) !== $userId) continue;
        if (($row['provider'] ?? '') !== $provider) continue;
        $row['last_login_at'] = date('Y-m-d H:i:s');
        $row['updated_at'] = date('Y-m-d H:i:s');
        $changed = true;
        break;
    }
    if ($changed) save_external_accounts($rows);
}

function unbind_external_account(int $userId, string $provider): bool {
    $provider = normalize_external_provider($provider);
    if ($provider === '') return false;
    $rows = external_accounts_all();
    $before = count($rows);
    $rows = array_values(array_filter($rows, fn($row) => !((int)($row['user_id'] ?? 0) === $userId && ($row['provider'] ?? '') === $provider)));
    if (count($rows) === $before) return false;
    save_external_accounts($rows);
    return true;
}

function external_login_username_base(string $provider, string $displayName = ''): string {
    $meta = external_provider_meta($provider);
    $base = trim($displayName) !== '' ? trim($displayName) : ($meta['label'] . '用户');
    $base = preg_replace('/\s+/u', '_', $base);
    $base = preg_replace('/[^A-Za-z0-9_\-\x{4e00}-\x{9fa5}]/u', '', $base);
    $base = trim((string)$base, '_-');
    if ($base === '') $base = $meta['key'] . '_user';
    return mb_substr($base, 0, 12, 'UTF-8');
}

function ensure_unique_username(string $base): string {
    $base = trim($base);
    if ($base === '') $base = 'music_user';
    $candidate = $base;
    $i = 1;
    while (find_user_by_username($candidate)) {
        $suffix = '_' . $i;
        $candidate = mb_substr($base, 0, max(1, 20 - mb_strlen($suffix, 'UTF-8')), 'UTF-8') . $suffix;
        $i++;
    }
    return $candidate;
}

function create_user_with_external(string $provider, string $externalId, string $displayName = '', string $avatarUrl = ''): array {
    $provider = normalize_external_provider($provider);
    $meta = external_provider_meta($provider);
    $users = users_all();
    $username = ensure_unique_username(external_login_username_base($provider, $displayName));
    $tempPassword = 'Ext@' . substr(bin2hex(random_bytes(6)), 0, 8);
    $nickname = trim($displayName) !== '' ? trim($displayName) : ($meta['label'] . '用户');
    $newUser = user_defaults([
        'id' => next_id($users),
        'username' => $username,
        'nickname' => mb_substr($nickname, 0, 20, 'UTF-8'),
        'password_hash' => password_hash($tempPassword, PASSWORD_DEFAULT),
        'role' => 'user',
        'status' => 'active',
        'avatar_url' => trim($avatarUrl),
        'bio' => '通过' . $meta['label'] . '快捷登录创建账号',
        'tags' => ['外置登录', $meta['label']],
        'points' => 0,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
    $users[] = $newUser;
    save_users($users);
    bind_external_account((int)$newUser['id'], $provider, $externalId, $displayName, $avatarUrl);
    add_user_log((int)$newUser['id'], '创建账号', '通过' . $meta['label'] . '快捷登录创建账号');
    return [
        'user' => $newUser,
        'generated_username' => $username,
        'temp_password' => $tempPassword,
        'provider_meta' => $meta,
    ];
}

function find_user_index_by_id(array $users, int $userId): int {
    foreach ($users as $idx => $user) if ((int)($user['id'] ?? 0) === $userId) return $idx;
    return -1;
}

function find_user_by_id(int $userId): ?array {
    foreach (users_all() as $user) if ((int)$user['id'] === $userId) return $user;
    return null;
}

function find_user_by_username(string $username): ?array {
    foreach (users_all() as $user) if (($user['username'] ?? '') === $username) return $user;
    return null;
}

function is_user_banned(array $user): bool {
    $until = strtotime((string)($user['ban_until'] ?? '')) ?: 0;
    return $until > time();
}

function sanitize_user(array $user, bool $includePrivate = false): array {
    $row = user_defaults($user);
    unset($row['password_hash']);
    $row['is_admin'] = ($row['role'] ?? 'user') === 'admin';
    $row['is_creator'] = ($row['role'] ?? 'user') === 'creator';
    $row['is_banned'] = is_user_banned($row);
    if ($includePrivate && !empty($row['id']) && empty($row['is_admin'])) {
        $row['external_accounts'] = external_accounts_for_user((int)$row['id']);
        $row['ai_quota'] = user_reasoner_quota_details($row);
    }
    if (!$includePrivate) {
        unset($row['ban_reason'], $row['ban_until'], $row['status'], $row['external_accounts'], $row['email'], $row['email_verified_at']);
    }
    return $row;
}

function user_stats_for(int $userId): array {
    $favorites = read_store(FAVORITES_FILE);
    $recs = read_store(RECOMMENDATIONS_FILE);
    $threads = file_exists(DATA_DIR . '/forum_threads.json') ? read_store(DATA_DIR . '/forum_threads.json') : [];
    $posts = file_exists(DATA_DIR . '/forum_posts.json') ? read_store(DATA_DIR . '/forum_posts.json') : [];
    $bookmarks = file_exists(FORUM_BOOKMARKS_FILE) ? read_store(FORUM_BOOKMARKS_FILE) : [];
    $favoriteCount = 0;
    $recommendCount = 0;
    $threadCount = 0;
    $replyCount = 0;
    $playCount = 0;
    $bookmarkCount = 0;
    $lastPlayedAt = '';
    $history = file_exists(PLAY_HISTORY_FILE) ? read_store(PLAY_HISTORY_FILE) : [];
    foreach ($favorites as $row) if ((int)($row['user_id'] ?? 0) === $userId) $favoriteCount++;
    foreach ($recs as $row) if ((int)($row['user_id'] ?? 0) === $userId) $recommendCount++;
    foreach ($threads as $row) if ((int)($row['user_id'] ?? 0) === $userId && empty($row['is_deleted'])) $threadCount++;
    foreach ($posts as $row) {
        if ((int)($row['user_id'] ?? 0) !== $userId) continue;
        if (!empty($row['is_author'])) continue;
        $replyCount++;
    }
    foreach ($bookmarks as $row) if ((int)($row['user_id'] ?? 0) === $userId) $bookmarkCount++;
    foreach ($history as $row) {
        if ((int)($row['user_id'] ?? 0) !== $userId) continue;
        $playCount++;
        $playedAt = (string)($row['played_at'] ?? '');
        if ($playedAt > $lastPlayedAt) $lastPlayedAt = $playedAt;
    }
    return [
        'favorites' => $favoriteCount,
        'recommendations' => $recommendCount,
        'threads' => $threadCount,
        'replies' => $replyCount,
        'plays' => $playCount,
        'bookmarks' => $bookmarkCount,
        'last_played_at' => $lastPlayedAt,
    ];
}

function build_user_achievements(array $user, ?array $stats = null): array {
    $safeUser = user_defaults($user);
    $stats = $stats ?: user_stats_for((int)($safeUser['id'] ?? 0));
    $rules = [
        ['key' => 'first_checkin', 'title' => '今日报到', 'icon' => 'calendar-check-o', 'target' => 1, 'value' => (int)($safeUser['checkin_count'] ?? 0), 'desc' => '完成至少 1 次签到'],
        ['key' => 'checkin_master', 'title' => '签到老朋友', 'icon' => 'calendar', 'target' => 7, 'value' => (int)($safeUser['checkin_count'] ?? 0), 'desc' => '累计签到 7 天'],
        ['key' => 'collector', 'title' => '收藏控', 'icon' => 'heart-o', 'target' => 10, 'value' => (int)($stats['favorites'] ?? 0), 'desc' => '收藏 10 首歌曲'],
        ['key' => 'listener', 'title' => '音乐发烧友', 'icon' => 'headphones', 'target' => 30, 'value' => (int)($stats['plays'] ?? 0), 'desc' => '累计播放 30 次'],
        ['key' => 'poster', 'title' => '发帖新星', 'icon' => 'pencil-square-o', 'target' => 3, 'value' => (int)($stats['threads'] ?? 0), 'desc' => '发布 3 篇帖子'],
        ['key' => 'replier', 'title' => '热心回复官', 'icon' => 'commenting-o', 'target' => 10, 'value' => (int)($stats['replies'] ?? 0), 'desc' => '累计回复 10 次'],
        ['key' => 'core', 'title' => '核心用户', 'icon' => 'star', 'target' => 4, 'value' => (int)($safeUser['level'] ?? 1), 'desc' => '等级达到 Lv.4'],
        ['key' => 'curator', 'title' => '内容收藏家', 'icon' => 'bookmark-o', 'target' => 5, 'value' => (int)($stats['bookmarks'] ?? 0), 'desc' => '收藏 5 篇社区帖子'],
    ];
    return array_map(function(array $item) {
        $target = max(1, (int)$item['target']);
        $value = max(0, (int)$item['value']);
        $item['achieved'] = $value >= $target;
        $item['progress_text'] = min($value, $target) . '/' . $target;
        $item['progress_percent'] = (int)max(0, min(100, round(($value / $target) * 100)));
        return $item;
    }, $rules);
}

function add_user_log(int $userId, string $action, string $detail = ''): void {
    if ($userId <= 0) return;
    $rows = read_store(USER_LOGS_FILE);
    $rows[] = [
        'id' => next_id($rows),
        'user_id' => $userId,
        'action' => $action,
        'detail' => $detail,
        'created_at' => date('Y-m-d H:i:s'),
    ];
    write_store(USER_LOGS_FILE, $rows);
}

function get_user_logs(int $userId, int $limit = 20): array {
    $items = array_values(array_filter(read_store(USER_LOGS_FILE), fn($row) => (int)($row['user_id'] ?? 0) === $userId));
    usort($items, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return array_slice($items, 0, $limit);
}

function get_play_history(int $userId, int $limit = 12): array {
    $items = array_values(array_filter(read_store(PLAY_HISTORY_FILE), fn($row) => (int)($row['user_id'] ?? 0) === $userId));
    usort($items, fn($a, $b) => strcmp((string)($b['played_at'] ?? ''), (string)($a['played_at'] ?? '')));
    return array_slice($items, 0, $limit);
}

function record_play_history(int $userId, array $track): array {
    $rows = read_store(PLAY_HISTORY_FILE);
    $trackId = trim((string)($track['track_id'] ?? $track['id'] ?? ''));
    if ($trackId === '') return [];
    $name = trim((string)($track['name'] ?? $track['title'] ?? ''));
    $now = date('Y-m-d H:i:s');
    foreach ($rows as &$row) {
        if ((int)($row['user_id'] ?? 0) === $userId && ($row['track_id'] ?? '') === $trackId) {
            $row['name'] = $name !== '' ? $name : ($row['name'] ?? '');
            $row['filename'] = trim((string)($track['filename'] ?? $row['filename'] ?? ''));
            $row['ext'] = strtolower(trim((string)($track['ext'] ?? $row['ext'] ?? '')));
            $row['source'] = trim((string)($track['source'] ?? $row['source'] ?? ''));
            $row['url'] = trim((string)($track['url'] ?? $row['url'] ?? ''));
            $row['played_at'] = $now;
            $row['play_count'] = (int)($row['play_count'] ?? 0) + 1;
            write_store(PLAY_HISTORY_FILE, $rows);
            return $row;
        }
    }
    $row = [
        'id' => next_id($rows),
        'user_id' => $userId,
        'track_id' => $trackId,
        'name' => $name !== '' ? $name : $trackId,
        'filename' => trim((string)($track['filename'] ?? '')),
        'ext' => strtolower(trim((string)($track['ext'] ?? ''))),
        'source' => trim((string)($track['source'] ?? '')),
        'url' => trim((string)($track['url'] ?? '')),
        'played_at' => $now,
        'play_count' => 1,
    ];
    $rows[] = $row;
    write_store(PLAY_HISTORY_FILE, $rows);
    return $row;
}

function normalize_notification_link(string $link): string {
    $link = trim($link);
    if ($link === '') return '';
    if (preg_match('#^(https?:)?//#i', $link)) return $link;
    return ltrim($link, '/');
}

function create_notification(int $userId, string $title, string $content, string $type = 'system', string $link = '', array $extra = []): ?array {
    if ($userId <= 0) return null;
    $rows = read_store(NOTIFICATIONS_FILE);
    $row = [
        'id' => next_id($rows),
        'user_id' => $userId,
        'title' => mb_substr(trim($title), 0, 60, 'UTF-8'),
        'content' => mb_substr(trim($content), 0, 220, 'UTF-8'),
        'type' => trim($type) ?: 'system',
        'link' => normalize_notification_link($link),
        'is_read' => false,
        'created_at' => date('Y-m-d H:i:s'),
        'meta' => $extra,
    ];
    $rows[] = $row;
    write_store(NOTIFICATIONS_FILE, $rows);
    return $row;
}

function broadcast_notification(string $title, string $content, string $type = 'broadcast', string $link = '', array $extra = []): int {
    $count = 0;
    foreach (users_all() as $user) {
        if ((int)($user['id'] ?? 0) <= 0) continue;
        create_notification((int)$user['id'], $title, $content, $type, $link, $extra);
        $count++;
    }
    return $count;
}

function get_user_notifications(int $userId, int $limit = 30): array {
    $items = array_values(array_filter(read_store(NOTIFICATIONS_FILE), fn($row) => (int)($row['user_id'] ?? 0) === $userId));
    usort($items, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return array_slice($items, 0, max(1, $limit));
}

function get_unread_notification_count(int $userId): int {
    $count = 0;
    foreach (read_store(NOTIFICATIONS_FILE) as $row) {
        if ((int)($row['user_id'] ?? 0) !== $userId) continue;
        if (empty($row['is_read'])) $count++;
    }
    return $count;
}

function mark_notification_read(int $userId, int $id): bool {
    $rows = read_store(NOTIFICATIONS_FILE);
    $changed = false;
    foreach ($rows as &$row) {
        if ((int)($row['user_id'] ?? 0) !== $userId) continue;
        if ((int)($row['id'] ?? 0) !== $id) continue;
        $row['is_read'] = true;
        $changed = true;
        break;
    }
    if ($changed) write_store(NOTIFICATIONS_FILE, $rows);
    return $changed;
}

function mark_all_notifications_read(int $userId): int {
    $rows = read_store(NOTIFICATIONS_FILE);
    $changed = 0;
    foreach ($rows as &$row) {
        if ((int)($row['user_id'] ?? 0) !== $userId) continue;
        if (!empty($row['is_read'])) continue;
        $row['is_read'] = true;
        $changed++;
    }
    if ($changed > 0) write_store(NOTIFICATIONS_FILE, $rows);
    return $changed;
}

function add_user_points(int $userId, int $delta, string $reason = ''): ?array {
    $users = users_all();
    $idx = find_user_index_by_id($users, $userId);
    if ($idx < 0) return null;
    $users[$idx]['points'] = max(0, (int)($users[$idx]['points'] ?? 0) + $delta);
    $users[$idx]['updated_at'] = date('Y-m-d H:i:s');
    save_users($users);
    if ($reason !== '') add_user_log($userId, $delta >= 0 ? '积分增加' : '积分调整', trim($reason . ' ' . ($delta >= 0 ? '+' : '') . $delta));
    return sanitize_user($users[$idx], true);
}

function remember_session_user(array $user): void {
    $_SESSION['uid'] = (int)($user['id'] ?? 0);
    $_SESSION['username'] = (string)($user['username'] ?? '');
    $_SESSION['role'] = (string)($user['role'] ?? 'user');
    if (($user['role'] ?? 'user') === 'admin') $_SESSION['is_admin'] = true;
    else unset($_SESSION['is_admin']);
}

function current_user(): ?array {
    if (!isset($_SESSION['uid'])) return null;
    if (!empty($_SESSION['is_admin'])) {
        return [
            'id' => (int)$_SESSION['uid'],
            'username' => $_SESSION['username'] ?? 'admin',
            'nickname' => '管理员',
            'role' => 'admin',
            'is_admin' => true,
            'points' => 999,
            'level' => 6,
            'level_title' => '系统管理员',
            'level_progress' => 100,
            'next_level_points' => 999,
            'avatar_url' => '',
            'bio' => '系统管理账号',
            'gender' => '保密',
            'city' => '',
            'birthday' => '',
            'tags' => ['管理'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'status' => 'active',
            'ban_until' => '',
            'ban_reason' => '',
            'last_checkin_date' => '',
            'checkin_count' => 0,
            'is_banned' => false,
            'referral_code' => 'ADMIN',
            'monthly_card_until' => '',
            'vip_until' => '',
            'vip_plan' => 'admin',
            'ai_daily_free_used' => 0,
            'ai_daily_free_date' => date('Y-m-d'),
            'total_ai_generations' => 0,
        ];
    }
    $user = find_user_by_id((int)$_SESSION['uid']);
    if (!$user) return null;
    return sanitize_user($user, true);
}

function auth_cookie_options(int $expire): array {
    return [
        'expires' => $expire,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ];
}

function auth_generate_token(): string {
    return bin2hex(random_bytes(32));
}

function auth_store_token(array $user): void {
    $tokens = read_store(AUTH_TOKENS_FILE);
    $raw = auth_generate_token();
    $expiresAt = time() + 60 * 60 * 24 * 30;
    $tokens[] = [
        'id' => next_id($tokens),
        'selector' => substr($raw, 0, 24),
        'token_hash' => hash('sha256', $raw),
        'uid' => (int)($user['id'] ?? 0),
        'username' => (string)($user['username'] ?? ''),
        'role' => (string)($user['role'] ?? 'user'),
        'is_admin' => !empty($user['is_admin']),
        'expires_at' => date('Y-m-d H:i:s', $expiresAt)
    ];
    write_store(AUTH_TOKENS_FILE, $tokens);
    setcookie(AUTH_COOKIE_NAME, $raw, auth_cookie_options($expiresAt));
}

function auth_clear_token(): void {
    $cookie = $_COOKIE[AUTH_COOKIE_NAME] ?? '';
    if ($cookie !== '') {
        $tokens = array_values(array_filter(read_store(AUTH_TOKENS_FILE), fn($row) => ($row['token_hash'] ?? '') !== hash('sha256', $cookie)));
        write_store(AUTH_TOKENS_FILE, $tokens);
    }
    setcookie(AUTH_COOKIE_NAME, '', auth_cookie_options(time() - 3600));
}

function auth_resume_from_cookie(): void {
    if (!empty($_SESSION['username']) || empty($_COOKIE[AUTH_COOKIE_NAME])) return;
    $cookie = (string)$_COOKIE[AUTH_COOKIE_NAME];
    $hash = hash('sha256', $cookie);
    $now = time();
    $tokens = read_store(AUTH_TOKENS_FILE);
    $newTokens = [];
    $matched = null;
    foreach ($tokens as $row) {
        $expires = strtotime((string)($row['expires_at'] ?? '')) ?: 0;
        if ($expires <= $now) continue;
        $newTokens[] = $row;
        if (($row['token_hash'] ?? '') === $hash) $matched = $row;
    }
    if (count($newTokens) !== count($tokens)) write_store(AUTH_TOKENS_FILE, $newTokens);
    if (!$matched) return;
    $_SESSION['uid'] = (int)($matched['uid'] ?? 0);
    $_SESSION['username'] = (string)($matched['username'] ?? '');
    $_SESSION['role'] = (string)($matched['role'] ?? 'user');
    if (!empty($matched['is_admin'])) $_SESSION['is_admin'] = true;
}

auth_resume_from_cookie();

function require_login(): array {
    $user = current_user();
    if (!$user) json_out(['ok' => false, 'msg' => '请先登录'], 401);
    return $user;
}

function require_active_user(): array {
    $user = require_login();
    if (!empty($user['is_admin'])) return $user;
    if (($user['status'] ?? 'active') !== 'active' || !empty($user['is_banned'])) {
        $until = !empty($user['ban_until']) ? ('，截止 ' . $user['ban_until']) : '';
        $reason = !empty($user['ban_reason']) ? ('，原因：' . $user['ban_reason']) : '';
        json_out(['ok' => false, 'msg' => '当前账号已被限制' . $until . $reason], 403);
    }
    return $user;
}

function require_admin(): void {
    if (empty($_SESSION['is_admin'])) json_out(['ok' => false, 'msg' => '需要管理员权限'], 403);
}
?>
