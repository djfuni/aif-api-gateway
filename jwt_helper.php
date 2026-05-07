<?php
function jwt_base64url_encode(string $data): string { return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); }
function jwt_base64url_decode(string $data): string { return base64_decode(strtr($data, '-_', '+/')); }
function jwt_secret_key(): string {
    $env = getenv('MUSIC_JWT_SECRET');
    if ($env) return $env;
    $fallback = __DIR__ . '/data/jwt_secret.key';
    if (!file_exists($fallback)) file_put_contents($fallback, bin2hex(random_bytes(32)));
    return trim((string)file_get_contents($fallback));
}
function jwt_issue(array $payload, int $ttl = 7200): string {
    $header = ['alg'=>'HS256','typ'=>'JWT'];
    $now = time();
    $payload = array_merge($payload, ['iat'=>$now, 'exp'=>$now + $ttl]);
    $segments = [jwt_base64url_encode(json_encode($header, JSON_UNESCAPED_UNICODE)), jwt_base64url_encode(json_encode($payload, JSON_UNESCAPED_UNICODE))];
    $signing = implode('.', $segments);
    $sig = hash_hmac('sha256', $signing, jwt_secret_key(), true);
    $segments[] = jwt_base64url_encode($sig);
    return implode('.', $segments);
}
function jwt_verify(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$h, $p, $s] = $parts;
    $calc = jwt_base64url_encode(hash_hmac('sha256', $h . '.' . $p, jwt_secret_key(), true));
    if (!hash_equals($calc, $s)) return null;
    $payload = json_decode(jwt_base64url_decode($p), true);
    if (!is_array($payload)) return null;
    if ((int)($payload['exp'] ?? 0) < time()) return null;
    return $payload;
}
