<?php
declare(strict_types=1);
require_once __DIR__ . '/../ai_api_gateway_lib.php';
final class AifRedeem {
    public static function createCodes(int $count, int $tokens, array $options = []): array { return ai_api_create_redeem_codes($count, $tokens, $options); }
    public static function redeem(int $userId, string $code, array $meta = []): array { return ai_api_redeem_code($userId, $code, $meta); }
    public static function records(int $userId, int $limit = 20): array { return ai_api_user_redeem_records($userId, $limit); }
}
