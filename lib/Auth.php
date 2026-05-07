<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
final class AifAuth {
    public static function currentUser(): ?array { return current_user(); }
    public static function requireLogin(): array { return require_login(); }
    public static function requireAdmin(): void { require_admin(); }
}
