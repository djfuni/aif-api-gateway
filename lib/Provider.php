<?php
declare(strict_types=1);
require_once __DIR__ . '/../ai_api_gateway_lib.php';
final class AifProvider {
    public static function registry(): array { return ai_api_provider_registry(); }
    public static function publicModels(): array { return ai_api_public_models(); }
    public static function resolve(string $model): array { return ai_api_resolve_model($model); }
}
