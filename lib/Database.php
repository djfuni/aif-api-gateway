<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
final class AifDatabase {
    public static function rows(string $file): array { return read_store($file); }
    public static function writeRows(string $file, array $rows): void { write_store($file, $rows); }
    public static function migrateLegacyJson(): void { db_force_migrate_legacy_files(); }
    public static function pdo(): PDO { return db(); }
}
