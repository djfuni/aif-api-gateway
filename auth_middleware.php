
<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt_helper.php';
require_once __DIR__ . '/api_security.php';

function auth_resolve_user(bool $allowSession = true): ?array {
    $token = api_bearer_token();
    if ($token !== '') {
        $payload = jwt_verify($token);
        if ($payload && !empty($payload['uid'])) {
            $user = find_user_by_id((int)$payload['uid']);
            if ($user) return $user;
        }
    }
    if ($allowSession) return current_user();
    return null;
}
function auth_require_user(bool $allowSession = true): array {
    $user = auth_resolve_user($allowSession);
    if (!$user) json_out(['ok'=>false,'msg'=>'未登录或凭证已失效'], 401);
    return $user;
}
function auth_require_admin(bool $allowSession = true): array {
    $user = auth_require_user($allowSession);
    if (empty($user['is_admin'])) json_out(['ok'=>false,'msg'=>'仅管理员可访问'], 403);
    return $user;
}
?>
