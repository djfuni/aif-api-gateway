<?php
declare(strict_types=1);
require_once __DIR__ . '/../ai_api_gateway_lib.php';
final class AifWallet {
    public static function forUser(int $userId): array { return ai_api_wallet_for_user($userId); }
    public static function update(int $userId, int $delta, string $type, array $meta = []): array { return ai_api_update_wallet($userId, $delta, $type, $meta); }
    public static function ledger(int $userId, int $limit = 20): array { return ai_api_user_ledger($userId, $limit); }
}
