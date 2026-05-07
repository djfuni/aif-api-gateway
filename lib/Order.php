<?php
declare(strict_types=1);
require_once __DIR__ . '/../ai_api_gateway_lib.php';
final class AifOrder {
    public static function create(int $userId, string $packageId, string $paymentType = 'alipay'): array { return ai_api_create_order($userId, $packageId, $paymentType); }
    public static function approve(int $orderId, int $adminId = 0): array { return ai_api_approve_order($orderId, $adminId); }
    public static function reject(int $orderId, string $note = ''): array { return ai_api_reject_order($orderId, $note); }
}
