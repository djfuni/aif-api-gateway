<?php
require __DIR__ . '/db.php';
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$data = input_data();
define('AUTH_EMAIL_CODE_TTL', 600);
define('AUTH_CAPTCHA_TTL', 300);
define('AUTH_EMAIL_CODE_RESEND_SECONDS', 60);

// Optional SMTP config for sending verification codes. Keep real credentials in smtp_config.php or environment variables.
if (is_file(__DIR__ . '/smtp_config.php')) {
    require_once __DIR__ . '/smtp_config.php';
}

function auth_users_cached(): array {
    static $users = null;
    if ($users === null) $users = users_all();
    return $users;
}

function auth_session_bucket(string $key): array {
    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) $_SESSION[$key] = [];
    return $_SESSION[$key];
}

function auth_clean_email(string $email): string {
    $email = mb_strtolower(trim($email), 'UTF-8');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_out(['ok' => false, 'msg' => '请填写有效的邮箱地址'], 400);
    }
    if (strlen($email) > 120) json_out(['ok' => false, 'msg' => '邮箱地址过长'], 400);
    return $email;
}

function auth_random_captcha_code(): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 5; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    return $code;
}

function auth_create_image_captcha(): string {
    $code = auth_random_captcha_code();
    $_SESSION['auth_image_captcha'] = [
        'hash' => password_hash($code, PASSWORD_DEFAULT),
        'expires_at' => time() + AUTH_CAPTCHA_TTL,
        'created_at' => time(),
    ];
    return $code;
}

function auth_captcha_svg(string $code): string {
    $chars = preg_split('//u', $code, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $text = '';
    foreach ($chars as $i => $char) {
        $x = 24 + $i * 28 + random_int(-3, 3);
        $y = 39 + random_int(-6, 5);
        $rotate = random_int(-16, 16);
        $escaped = htmlspecialchars($char, ENT_QUOTES, 'UTF-8');
        $text .= "<text x=\"{$x}\" y=\"{$y}\" transform=\"rotate({$rotate} {$x} {$y})\" class=\"captcha-text\">{$escaped}</text>";
    }
    $lines = '';
    for ($i = 0; $i < 7; $i++) {
        $x1 = random_int(0, 160); $y1 = random_int(0, 56); $x2 = random_int(0, 160); $y2 = random_int(0, 56);
        $opacity = random_int(18, 42) / 100;
        $lines .= "<path d=\"M{$x1} {$y1} C " . random_int(20, 140) . ' ' . random_int(0, 56) . ', ' . random_int(20, 140) . ' ' . random_int(0, 56) . ", {$x2} {$y2}\" stroke=\"rgba(255,255,255,{$opacity})\" stroke-width=\"1.2\" fill=\"none\"/>";
    }
    $dots = '';
    for ($i = 0; $i < 36; $i++) {
        $cx = random_int(4, 156); $cy = random_int(4, 52); $r = random_int(1, 3) / 2;
        $dots .= "<circle cx=\"{$cx}\" cy=\"{$cy}\" r=\"{$r}\" fill=\"rgba(255,255,255,.28)\"/>";
    }
    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="160" height="56" viewBox="0 0 160 56" role="img" aria-label="验证码">
  <defs>
    <linearGradient id="g" x1="0" x2="1" y1="0" y2="1">
      <stop offset="0" stop-color="#67e8f9" stop-opacity=".92"/>
      <stop offset=".48" stop-color="#a78bfa" stop-opacity=".74"/>
      <stop offset="1" stop-color="#fb7185" stop-opacity=".86"/>
    </linearGradient>
    <filter id="blur"><feGaussianBlur stdDeviation=".2"/></filter>
    <style><![CDATA[
      .captcha-text{font:900 25px 'Segoe UI',Arial,sans-serif;letter-spacing:2px;fill:#ffffff;paint-order:stroke;stroke:rgba(15,23,42,.38);stroke-width:2px;filter:url(#blur)}
    ]]></style>
  </defs>
  <rect width="160" height="56" rx="18" fill="rgba(15,23,42,.72)"/>
  <rect x="1" y="1" width="158" height="54" rx="17" fill="url(#g)" opacity=".54"/>
  <path d="M-10 46 C 30 15, 54 72, 94 28 S 154 10, 175 38" stroke="rgba(255,255,255,.32)" stroke-width="9" fill="none"/>
  {$dots}
  {$lines}
  {$text}
</svg>
SVG;
}

function auth_verify_image_captcha_or_fail(string $value, bool $consume = true): void {
    $value = strtoupper(trim($value));
    $captcha = $_SESSION['auth_image_captcha'] ?? null;
    if (!is_array($captcha) || empty($captcha['hash']) || (int)($captcha['expires_at'] ?? 0) < time()) {
        unset($_SESSION['auth_image_captcha']);
        json_out(['ok' => false, 'msg' => '图片验证码已过期，请刷新后重试'], 400);
    }
    if ($value === '' || !password_verify($value, (string)$captcha['hash'])) {
        json_out(['ok' => false, 'msg' => '图片验证码错误'], 400);
    }
    if ($consume) unset($_SESSION['auth_image_captcha']);
}

function auth_email_key(string $email): string {
    return hash('sha256', mb_strtolower(trim($email), 'UTF-8'));
}

function auth_config_value(string $constantName, string $envName, $default = null) {
    $envValue = getenv($envName);
    if ($envValue !== false && trim((string)$envValue) !== '') return $envValue;
    if (defined($constantName) && trim((string)constant($constantName)) !== '') return constant($constantName);
    return $default;
}

function auth_smtp_config(): array {
    $username = trim((string)auth_config_value('SMTP_USERNAME', 'SMTP_USERNAME', ''));
    $fromEmail = trim((string)auth_config_value('SMTP_FROM_EMAIL', 'SMTP_FROM_EMAIL', $username));
    return [
        'host' => trim((string)auth_config_value('SMTP_HOST', 'SMTP_HOST', 'smtp.126.com')),
        'port' => (int)auth_config_value('SMTP_PORT', 'SMTP_PORT', 465),
        'secure' => strtolower(trim((string)auth_config_value('SMTP_SECURE', 'SMTP_SECURE', 'ssl'))), // ssl, tls, none
        'username' => $username,
        'password' => (string)auth_config_value('SMTP_PASSWORD', 'SMTP_PASSWORD', ''),
        'from_email' => $fromEmail,
        'from_name' => trim((string)auth_config_value('SMTP_FROM_NAME', 'SMTP_FROM_NAME', 'Music Site')),
        'timeout' => (int)auth_config_value('SMTP_TIMEOUT', 'SMTP_TIMEOUT', 20),
    ];
}

function auth_mime_header(string $text): string {
    if (function_exists('mb_encode_mimeheader')) {
        return mb_encode_mimeheader($text, 'UTF-8', 'B', "\r\n");
    }
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

function auth_smtp_read($socket): array {
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') break;
    }
    $code = (int)substr($response, 0, 3);
    return [$code, trim($response)];
}

function auth_smtp_command($socket, string $command, array $expectCodes, string $errorLabel): array {
    if ($command !== '') {
        fwrite($socket, $command . "\r\n");
    }
    [$code, $response] = auth_smtp_read($socket);
    if (!in_array($code, $expectCodes, true)) {
        throw new RuntimeException($errorLabel . '：' . $response);
    }
    return [$code, $response];
}

function auth_smtp_address(string $email): string {
    $email = trim($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('发件邮箱地址无效');
    }
    return $email;
}

function auth_smtp_send_mail(string $toEmail, string $subject, string $body, ?string &$error = null): bool {
    $cfg = auth_smtp_config();
    $error = null;

    if ($cfg['host'] === '' || $cfg['port'] <= 0) {
        $error = 'SMTP 服务器未配置';
        return false;
    }
    if ($cfg['username'] === '' || $cfg['password'] === '') {
        $error = 'SMTP 账号或授权码未配置';
        return false;
    }

    try {
        $fromEmail = auth_smtp_address((string)$cfg['from_email']);
        $toEmail = auth_smtp_address($toEmail);
        $secure = (string)$cfg['secure'];
        $transport = ($secure === 'ssl') ? 'ssl://' : 'tcp://';
        $host = (string)$cfg['host'];
        $port = (int)$cfg['port'];
        $timeout = max(5, (int)$cfg['timeout']);

        $socket = @stream_socket_client(
            $transport . $host . ':' . $port,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT
        );
        if (!$socket) throw new RuntimeException("连接 SMTP 服务器失败：{$errno} {$errstr}");
        stream_set_timeout($socket, $timeout);

        auth_smtp_command($socket, '', [220], 'SMTP 服务未就绪');
        auth_smtp_command($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250], 'EHLO 失败');

        if ($secure === 'tls') {
            auth_smtp_command($socket, 'STARTTLS', [220], 'STARTTLS 失败');
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('TLS 加密握手失败');
            }
            auth_smtp_command($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250], 'TLS 后 EHLO 失败');
        }

        auth_smtp_command($socket, 'AUTH LOGIN', [334], 'SMTP 认证初始化失败');
        auth_smtp_command($socket, base64_encode((string)$cfg['username']), [334], 'SMTP 账号认证失败');
        auth_smtp_command($socket, base64_encode((string)$cfg['password']), [235], 'SMTP 授权码认证失败');

        auth_smtp_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250], '发件人被拒绝');
        auth_smtp_command($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251], '收件人被拒绝');
        auth_smtp_command($socket, 'DATA', [354], 'DATA 命令失败');

        $fromName = trim((string)$cfg['from_name']);
        $headers = [
            'Date: ' . date('r'),
            'From: ' . auth_mime_header($fromName ?: 'Music Site') . ' <' . $fromEmail . '>',
            'To: <' . $toEmail . '>',
            'Subject: ' . auth_mime_header($subject),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'localhost') . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        $message = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body));
        $message = preg_replace('/^\./m', '..', $message);
        fwrite($socket, $message . "\r\n.\r\n");
        [$code, $response] = auth_smtp_read($socket);
        if (!in_array($code, [250], true)) throw new RuntimeException('邮件发送失败：' . $response);

        @fwrite($socket, "QUIT\r\n");
        @fclose($socket);
        return true;
    } catch (Throwable $e) {
        if (isset($socket) && is_resource($socket)) @fclose($socket);
        $error = $e->getMessage();
        error_log('[auth smtp] ' . $error);
        return false;
    }
}

function auth_send_verification_mail(string $email, string $code, ?string &$error = null): bool {
    $subject = '注册邮箱验证码';
    $body = "你的注册邮箱验证码是：{$code}\n\n验证码 10 分钟内有效。如非本人操作，请忽略本邮件。";
    return auth_smtp_send_mail($email, $subject, $body, $error);
}

function auth_store_email_code(string $email, string $code): void {
    if (!isset($_SESSION['auth_email_codes']) || !is_array($_SESSION['auth_email_codes'])) $_SESSION['auth_email_codes'] = [];
    $_SESSION['auth_email_codes'][auth_email_key($email)] = [
        'email' => $email,
        'hash' => password_hash($code, PASSWORD_DEFAULT),
        'expires_at' => time() + AUTH_EMAIL_CODE_TTL,
        'attempts' => 0,
        'created_at' => time(),
    ];
}

function auth_verify_email_code_or_fail(string $email, string $code, bool $consume = true): void {
    $email = auth_clean_email($email);
    $code = trim($code);
    $key = auth_email_key($email);
    $bucket = $_SESSION['auth_email_codes'][$key] ?? null;
    if (!is_array($bucket) || empty($bucket['hash']) || (int)($bucket['expires_at'] ?? 0) < time()) {
        unset($_SESSION['auth_email_codes'][$key]);
        json_out(['ok' => false, 'msg' => '邮箱验证码已过期，请重新获取'], 400);
    }
    $attempts = (int)($bucket['attempts'] ?? 0);
    if ($attempts >= 6) {
        unset($_SESSION['auth_email_codes'][$key]);
        json_out(['ok' => false, 'msg' => '邮箱验证码错误次数过多，请重新获取'], 400);
    }
    if ($code === '' || !password_verify($code, (string)$bucket['hash'])) {
        $_SESSION['auth_email_codes'][$key]['attempts'] = $attempts + 1;
        json_out(['ok' => false, 'msg' => '邮箱验证码错误'], 400);
    }
    if ($consume) unset($_SESSION['auth_email_codes'][$key]);
}

function auth_email_already_used(array $users, string $email): bool {
    foreach ($users as $u) {
        if (mb_strtolower(trim((string)($u['email'] ?? '')), 'UTF-8') === $email) return true;
    }
    return false;
}

function external_auth_provider_or_fail(array $data): array {
    $provider = normalize_external_provider((string)($data['provider'] ?? ''));
    if ($provider === '') json_out(['ok' => false, 'msg' => '暂不支持该外置登录平台'], 400);
    return external_provider_meta($provider);
}

function external_auth_id_or_fail(array $data): string {
    $externalId = trim((string)($data['external_id'] ?? ''));
    if ($externalId === '' || strlen($externalId) < 3) json_out(['ok' => false, 'msg' => '请填写外部平台唯一标识（如 openid / unionid / GitHub id）'], 400);
    if (strlen($externalId) > 80) json_out(['ok' => false, 'msg' => '外部平台唯一标识过长'], 400);
    return $externalId;
}

if ($action === 'captcha_svg') {
    $code = auth_create_image_captcha();
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo auth_captcha_svg($code);
    exit;
}
if ($action === 'send_email_code') {
    $users = auth_users_cached();
    $email = auth_clean_email((string)($data['email'] ?? ''));
    auth_verify_image_captcha_or_fail((string)($data['image_captcha'] ?? $data['captcha'] ?? ''), true);
    if (auth_email_already_used($users, $email)) json_out(['ok' => false, 'msg' => '该邮箱已被注册，请直接登录'], 400);

    $now = time();
    $lastSentAt = (int)($_SESSION['auth_email_last_sent_at'] ?? 0);
    if ($lastSentAt && $now - $lastSentAt < AUTH_EMAIL_CODE_RESEND_SECONDS) {
        json_out(['ok' => false, 'msg' => '验证码发送过于频繁，请稍后再试'], 429);
    }
    $code = (string)random_int(100000, 999999);
    auth_store_email_code($email, $code);
    $mailError = null;
    if (!auth_send_verification_mail($email, $code, $mailError)) {
        unset($_SESSION['auth_email_codes'][auth_email_key($email)]);
        json_out(['ok' => false, 'msg' => '邮箱验证码发送失败：' . ($mailError ?: '请检查 126 SMTP 配置和客户端授权码')], 500);
    }
    $_SESSION['auth_email_last_sent_at'] = $now;
    json_out(['ok' => true, 'msg' => '邮箱验证码已通过 126 SMTP 发送，10 分钟内有效']);
}

if ($action === 'check') {
    $user = current_user();
    json_out(['status' => 'success', 'ok' => true, 'is_logged_in' => !empty($_SESSION['is_admin']), 'user' => $user]);
}
if ($action === 'external_providers') {
    json_out(['ok' => true, 'data' => array_values(external_provider_catalog())]);
}
if ($action === 'admin_login') {
    $password = (string)($data['password'] ?? '');
    if ($password !== ADMIN_PASSWORD) json_out(['status' => 'error', 'ok' => false, 'message' => '管理员密码错误'], 400);
    remember_session_user(['id' => 0, 'username' => 'admin', 'role' => 'admin']);
    auth_store_token(current_user() ?: ['id' => 0, 'username' => 'admin', 'role' => 'admin', 'is_admin' => true]);
    json_out(['status' => 'success', 'ok' => true, 'message' => '管理员登录成功', 'user' => current_user()]);
}
if ($action === 'register') {
    $users = auth_users_cached();
    $username = trim((string)($data['username'] ?? ''));
    $password = (string)($data['password'] ?? '');
    $email = auth_clean_email((string)($data['email'] ?? ''));
    $emailCode = trim((string)($data['email_code'] ?? ''));
    if ($username === '' || $password === '') json_out(['ok' => false, 'msg' => '用户名和密码不能为空'], 400);
    if (!preg_match('/^[A-Za-z0-9_\-\x{4e00}-\x{9fa5}]{3,20}$/u', $username)) {
        json_out(['ok' => false, 'msg' => '用户名需为 3-20 位中英文、数字、下划线或短横线'], 400);
    }
    if (strlen($password) < 6) json_out(['ok' => false, 'msg' => '密码至少 6 位'], 400);
    foreach ($users as $u) if (($u['username'] ?? '') === $username) json_out(['ok' => false, 'msg' => '用户名已存在'], 400);
    if (auth_email_already_used($users, $email)) json_out(['ok' => false, 'msg' => '该邮箱已被注册，请直接登录'], 400);
    auth_verify_image_captcha_or_fail((string)($data['image_captcha'] ?? ''), true);
    auth_verify_email_code_or_fail($email, $emailCode, true);
    $inviteCode = trim((string)($data['invite_code'] ?? $data['ref'] ?? $_GET['invite'] ?? $_GET['ref'] ?? ''));
    $newUser = user_defaults([
        'id' => next_id($users),
        'username' => $username,
        'nickname' => $username,
        'email' => $email,
        'email_verified_at' => date('Y-m-d H:i:s'),
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'user',
        'status' => 'active',
        'tags' => ['新用户'],
        'points' => 0,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    $newUser = points_apply_register_reward_to_user($newUser, $inviteCode);
    $users[] = $newUser;
    save_users($users);
    add_user_log((int)$newUser['id'], '注册账号', '完成初始注册，注册奖励 +' . points_reward_value('register'));
    points_reward_inviter_after_register($newUser);
    json_out(['ok' => true, 'msg' => '注册成功，已赠送 ' . points_reward_value('register') . ' 积分，请登录']);
}
if ($action === 'user_login') {
    $users = auth_users_cached();
    $username = trim((string)($data['username'] ?? ''));
    $password = (string)($data['password'] ?? '');
    auth_verify_image_captcha_or_fail((string)($data['image_captcha'] ?? ''), true);
    foreach ($users as $u) {
        if (($u['username'] ?? '') === $username && password_verify($password, (string)($u['password_hash'] ?? ''))) {
            remember_session_user($u);
            auth_store_token(sanitize_user($u, true));
            add_user_log((int)$u['id'], '登录账号', '前台登录');
            $user = current_user();
            $msg = !empty($user['is_banned']) ? '登录成功，但当前账号已被限制操作' : '登录成功';
            json_out(['ok' => true, 'msg' => $msg, 'user' => $user]);
        }
    }
    json_out(['ok' => false, 'msg' => '用户名或密码错误'], 400);
}
if ($action === 'external_login') {
    $providerMeta = external_auth_provider_or_fail($data);
    $provider = (string)$providerMeta['key'];
    $externalId = external_auth_id_or_fail($data);
    $displayName = trim((string)($data['display_name'] ?? ''));
    $avatarUrl = trim((string)($data['avatar_url'] ?? ''));
    $binding = find_external_account($provider, $externalId);

    if ($binding) {
        $user = find_user_by_id((int)($binding['user_id'] ?? 0));
        if (!$user) json_out(['ok' => false, 'msg' => '该外置账号绑定的数据异常，请重新绑定'], 409);
        remember_session_user($user);
        auth_store_token(sanitize_user($user, true));
        touch_external_account_login((int)$user['id'], $provider);
        add_user_log((int)$user['id'], '外置登录', '通过' . $providerMeta['label'] . '快捷登录');
        json_out(['ok' => true, 'msg' => '已通过' . $providerMeta['label'] . '快捷登录', 'user' => current_user(), 'provider' => $providerMeta]);
    }

    $created = create_user_with_external($provider, $externalId, $displayName, $avatarUrl);
    $user = points_apply_register_reward_to_user($created['user'], trim((string)($data['invite_code'] ?? $_GET['invite'] ?? $_GET['ref'] ?? '')));
    $usersForReward = users_all();
    $rewardIdx = find_user_index_by_id($usersForReward, (int)$user['id']);
    if ($rewardIdx >= 0) {
        $usersForReward[$rewardIdx] = $user;
        save_users($usersForReward);
        add_user_log((int)$user['id'], '注册奖励', '外置登录自动创建账号，注册奖励 +' . points_reward_value('register'));
        points_reward_inviter_after_register($user);
    }
    remember_session_user($user);
    auth_store_token(sanitize_user($user, true));
    create_notification((int)$user['id'], '已启用外置登录', '你已经通过' . $providerMeta['label'] . '完成首次快捷登录，并自动创建了站内账号。', 'security', 'profile.html');
    json_out([
        'ok' => true,
        'msg' => '已通过' . $providerMeta['label'] . '快捷登录，并自动创建站内账号',
        'user' => current_user(),
        'provider' => $providerMeta,
        'is_auto_created' => true,
        'generated_username' => $created['generated_username'],
        'temp_password' => $created['temp_password']
    ]);
}
if ($action === 'external_list') {
    $user = require_login();
    if (!empty($user['is_admin'])) json_out(['ok' => true, 'data' => []]);
    json_out(['ok' => true, 'data' => external_accounts_for_user((int)$user['id'])]);
}
if ($action === 'external_bind') {
    $user = require_active_user();
    if (!empty($user['is_admin'])) json_out(['ok' => false, 'msg' => '管理员不支持绑定外置账号'], 400);
    $providerMeta = external_auth_provider_or_fail($data);
    $provider = (string)$providerMeta['key'];
    $externalId = external_auth_id_or_fail($data);
    $displayName = trim((string)($data['display_name'] ?? ''));
    $avatarUrl = trim((string)($data['avatar_url'] ?? ''));

    $existing = find_external_account($provider, $externalId);
    if ($existing && (int)($existing['user_id'] ?? 0) !== (int)$user['id']) {
        json_out(['ok' => false, 'msg' => '该' . $providerMeta['label'] . '账号已经绑定到其他站内用户'], 409);
    }

    bind_external_account((int)$user['id'], $provider, $externalId, $displayName, $avatarUrl);
    add_user_log((int)$user['id'], '绑定外置账号', '绑定了' . $providerMeta['label'] . '登录');
    create_notification((int)$user['id'], '绑定成功', '你的账号已绑定' . $providerMeta['label'] . '快捷登录。', 'security', 'profile.html');
    json_out(['ok' => true, 'msg' => '已绑定' . $providerMeta['label'] . '登录', 'data' => external_accounts_for_user((int)$user['id'])]);
}
if ($action === 'external_unbind') {
    $user = require_active_user();
    if (!empty($user['is_admin'])) json_out(['ok' => false, 'msg' => '管理员不支持解绑外置账号'], 400);
    $providerMeta = external_auth_provider_or_fail($data);
    $provider = (string)$providerMeta['key'];
    if (!unbind_external_account((int)$user['id'], $provider)) json_out(['ok' => false, 'msg' => '当前未绑定该外置账号'], 404);
    add_user_log((int)$user['id'], '解绑外置账号', '移除了' . $providerMeta['label'] . '登录');
    create_notification((int)$user['id'], '解绑成功', '已解除' . $providerMeta['label'] . '快捷登录，请确保你仍记得站内账号密码。', 'security', 'profile.html');
    json_out(['ok' => true, 'msg' => '已解绑' . $providerMeta['label'] . '登录，请确保仍可使用本地密码登录', 'data' => external_accounts_for_user((int)$user['id'])]);
}
if ($action === 'change_password') {
    $user = require_active_user();
    if (!empty($user['is_admin'])) json_out(['ok' => false, 'msg' => '管理员请使用后台密码'], 400);
    $currentPassword = (string)($data['current_password'] ?? '');
    $newPassword = (string)($data['new_password'] ?? '');
    $confirmPassword = (string)($data['confirm_password'] ?? '');
    if ($currentPassword === '' || $newPassword === '') json_out(['ok' => false, 'msg' => '请完整填写密码信息'], 400);
    if ($newPassword !== $confirmPassword) json_out(['ok' => false, 'msg' => '两次输入的新密码不一致'], 400);
    if (strlen($newPassword) < 6) json_out(['ok' => false, 'msg' => '新密码至少 6 位'], 400);
    $users = users_all();
    $idx = find_user_index_by_id($users, (int)$user['id']);
    if ($idx < 0) json_out(['ok' => false, 'msg' => '用户不存在'], 404);
    if (!password_verify($currentPassword, (string)($users[$idx]['password_hash'] ?? ''))) json_out(['ok' => false, 'msg' => '当前密码错误'], 400);
    if (password_verify($newPassword, (string)($users[$idx]['password_hash'] ?? ''))) json_out(['ok' => false, 'msg' => '新密码不能与当前密码相同'], 400);
    $users[$idx]['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    $users[$idx]['updated_at'] = date('Y-m-d H:i:s');
    save_users($users);
    add_user_log((int)$user['id'], '修改密码', '已更新账号密码');
    json_out(['ok' => true, 'msg' => '密码已更新，请牢记新密码']);
}
if ($action === 'me') json_out(['ok' => true, 'user' => current_user()]);
if ($action === 'logout') {
    $user = current_user();
    if ($user && empty($user['is_admin'])) add_user_log((int)$user['id'], '退出登录', '主动退出');
    auth_clear_token();
    session_unset();
    session_destroy();
    json_out(['ok' => true, 'msg' => '已退出']);
}
json_out(['ok' => false, 'msg' => '未知操作'], 400);
?>
