<?php
/**
 * 【新增｜验证工具｜风险等级：无】
 * AIF API 网关上游适配自检脚本：仅检查 provider 解析、出站头、payload 清洗逻辑，不发起真实上游请求、不消耗 Token。
 * CLI: php tools/aif_api_adapter_selftest.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
require_once dirname(__DIR__) . '/ai_api_gateway_lib.php';

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'api.aifmusic.top';
$_SERVER['HTTPS'] = $_SERVER['HTTPS'] ?? 'on';

$tests = [];
$github = ai_api_dynamic_provider_model('github::openai/gpt-4.1');
$tests['github_dynamic_resolve'] = is_array($github) && (($github['model']['accept_header'] ?? '') === 'application/vnd.github+json');

$payload = ai_api_request_payload([
    'model' => 'github::openai/gpt-4.1',
    'messages' => [['role' => 'user', 'content' => 'ping']],
    'logprobs' => true,
    'top_logprobs' => 2,
    'user' => 'selftest',
    'max_completion_tokens' => 8,
], 'github::openai/gpt-4.1', $github['model']);
$tests['github_payload_sanitized'] = !isset($payload['logprobs'], $payload['top_logprobs'], $payload['user']) && isset($payload['max_tokens']);

$openrouter = ai_api_dynamic_provider_model('openrouter::openai/gpt-oss-120b:free');
$headers = ai_api_build_headers([], $openrouter['model'], false);
$tests['openrouter_headers'] = (bool)array_filter($headers, fn($h) => str_starts_with($h, 'HTTP-Referer:'))
    && (bool)array_filter($headers, fn($h) => str_starts_with($h, 'X-OpenRouter-Title:'));

$failed = array_keys(array_filter($tests, fn($ok) => !$ok));
echo json_encode(['ok' => empty($failed), 'tests' => $tests, 'failed' => $failed], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
exit(empty($failed) ? 0 : 1);
