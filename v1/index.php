<?php
require_once dirname(__DIR__) . '/ai_api_gateway_lib.php';

ai_api_preflight_or_continue();

$path = $_SERVER['PATH_INFO'] ?? '';
$path = trim((string)$path, '/');

if ($path === '') {
    ai_api_json([
        'ok' => true,
        'name' => 'KingDungeon OpenAI Compatible API',
        'base_url' => ai_api_base_url(),
        'endpoints' => ['/v1/models', '/v1/chat/completions', '/v1/completions', '/v1/responses'],
        'nginx_fallback_base_url' => rtrim(ai_api_base_url(), '/') . '/index.php',
        'compatibility' => [
            'authorization' => 'Bearer, X-API-Key, api-key',
            'cors' => true,
            'stream' => true,
        ],
    ]);
    exit;
}

switch ($path) {
    case 'models':
        ai_api_handle_models();
        break;
    case 'chat/completions':
        ai_api_handle_chat_completion();
        break;
    case 'completions':
        ai_api_handle_completions();
        break;
    case 'responses':
        ai_api_handle_responses();
        break;
    default:
        ai_api_openai_error('invalid_request_error', '未找到 API 路由: /' . $path, 404);
}
?>
