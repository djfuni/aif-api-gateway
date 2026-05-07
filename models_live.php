<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/Cache.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function ai_site_json($payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (empty($_GET['refresh'])) {
    $cached = aif_cache_get('models_live_registry', 300);
    if (is_array($cached)) ai_site_json(['ok' => true, 'registry' => $cached, 'cached' => true]);
}

function ai_site_registry(): array {
    $path = __DIR__ . '/config/ai_model_registry.php';
    $registry = is_file($path) ? include $path : [];
    return is_array($registry) ? $registry : ['providers' => []];
}

function ai_site_provider_secrets(): array {
    $secrets = [];
    $path = DATA_DIR . '/ai_providers_private.php';
    if (is_file($path)) {
        $loaded = include $path;
        if (is_array($loaded)) $secrets = $loaded;
    }
    $envMap = [
        'moonshot' => ['MOONSHOT_API_KEY', 'KIMI_API_KEY'],
        'bailian' => ['DASHSCOPE_API_KEY', 'BAILIAN_API_KEY'],
        'github' => ['GITHUB_TOKEN', 'GITHUB_MODELS_TOKEN'],
        'siliconflow' => ['SILICONFLOW_API_KEY'],
        'openrouter' => ['OPENROUTER_API_KEY', 'OPENROUTER_TOKEN'],
        'mimo' => ['MIMO_TOKEN_PLAN_API_KEY', 'MIMO_API_KEY', 'XIAOMI_MIMO_API_KEY', 'MIMOTOKENPLAN_API_KEY'],
        'mimo2' => ['MIMO2_API_KEY', 'MIMO_V2_API_KEY', 'XIAOMI_MIMO2_API_KEY', 'XIAOMI_MIMO_V2_API_KEY', 'MIMO_TOKEN_PLAN_2_API_KEY'],
    ];
    foreach ($envMap as $provider => $names) {
        foreach ($names as $name) {
            $value = getenv($name);
            if ($value !== false && trim((string)$value) !== '') {
                if (!isset($secrets[$provider]) || !is_array($secrets[$provider])) $secrets[$provider] = [];
                if (empty($secrets[$provider]['api_key'])) $secrets[$provider]['api_key'] = trim((string)$value);
            }
        }
    }
    return $secrets;
}




/**
 * 【新增｜兼容性优化｜风险等级：低】模型广场动态拉取时统一 provider 别名和 Base URL 环境变量覆盖。
 */
function ai_site_provider_base_url(string $provider, string $fallback): string {
    $provider = strtolower(trim($provider));
    $envMap = [
        'moonshot' => ['MOONSHOT_API_BASE', 'KIMI_API_BASE', 'KIMI_BASE_URL'],
        'bailian' => ['DASHSCOPE_API_BASE', 'BAILIAN_API_BASE', 'DASHSCOPE_BASE_URL'],
        'github' => ['GITHUB_MODELS_API_BASE', 'GITHUB_MODELS_BASE_URL'],
        'siliconflow' => ['SILICONFLOW_API_BASE', 'SILICONFLOW_BASE_URL'],
        'openrouter' => ['OPENROUTER_API_BASE', 'OPENROUTER_BASE_URL'],
        'mimo' => ['MIMO_TOKEN_PLAN_API_BASE', 'MIMO_API_BASE', 'XIAOMI_MIMO_API_BASE', 'MIMOTOKENPLAN_API_BASE'],
        'mimo2' => ['MIMO2_API_BASE', 'MIMO_V2_API_BASE', 'XIAOMI_MIMO2_API_BASE', 'XIAOMI_MIMO_V2_API_BASE', 'MIMO_TOKEN_PLAN_2_API_BASE', 'MIMO_TOKEN_PLAN_API_BASE', 'MIMO_API_BASE', 'XIAOMI_MIMO_API_BASE'],
    ];
    foreach ($envMap[$provider] ?? [] as $name) {
        $value = getenv($name);
        if ($value !== false && trim((string)$value) !== '') return rtrim(trim((string)$value), '/');
    }
    return rtrim($fallback, '/');
}

function ai_site_price_is_zero($value): bool {
    if ($value === null || $value === '') return false;
    return abs((float)$value) < 0.0000000001;
}

function ai_site_openrouter_model_is_free(array $m): bool {
    if (!empty($m['free'])) return true;
    $id = strtolower(trim((string)($m['id'] ?? '')));
    $tags = array_map('strtolower', array_map('strval', (array)($m['tags'] ?? [])));
    if ($id === 'openrouter/free' || str_ends_with($id, ':free') || in_array('free', $tags, true)) return true;
    $pricing = (array)($m['pricing'] ?? []);
    return ai_site_price_is_zero($pricing['prompt'] ?? null) && ai_site_price_is_zero($pricing['completion'] ?? null);
}

function ai_site_fetch_json(string $url, string $apiKey = '', array $headers = []): ?array {
    if (!function_exists('curl_init')) return null;
    $h = array_merge(['Accept: application/json'], $headers);
    if ($apiKey !== '') $h[] = 'Authorization: Bearer ' . $apiKey;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $h,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status < 200 || $status >= 300 || !is_string($body) || $body === '') return null;
    $json = json_decode($body, true);
    return is_array($json) ? $json : null;
}

$registry = ai_site_registry();
$secrets = ai_site_provider_secrets();

if (!isset($registry['providers']) || !is_array($registry['providers'])) $registry['providers'] = [];

if (!empty($_GET['refresh'])) {
    foreach ($registry['providers'] as $provider => &$cfg) {
        $key = trim((string)($secrets[$provider]['api_key'] ?? ''));
        $catalogRequiresKey = array_key_exists('catalog_requires_key', $cfg) ? !empty($cfg['catalog_requires_key']) : true;
        if (empty($cfg['live_catalog'])) continue;
        if ($key === '' && $catalogRequiresKey) continue;

        $liveModels = null;
        if ($provider === 'github' && !empty($cfg['catalog_url'])) {
            // 【修改｜兼容性优化｜风险等级：低】GitHub Models Catalog 使用官方版本头和 vendor Accept，避免动态目录拉取失败。
            $liveModels = ai_site_fetch_json((string)$cfg['catalog_url'], $key, ['Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2026-03-10']);
            if (is_array($liveModels)) {
                $cfg['models'] = array_map(function($m) {
                    return [
                        'id' => (string)($m['id'] ?? ''),
                        'label' => (string)($m['name'] ?? $m['id'] ?? ''),
                        'tags' => array_values((array)($m['tags'] ?? [])),
                        'thinking' => false,
                        'context' => (string)($m['limits']['max_input_tokens'] ?? 'catalog'),
                    ];
                }, array_values(array_filter($liveModels, fn($m) => !empty($m['id']))));
            }
        } elseif (in_array($provider, ['moonshot', 'siliconflow', 'mimo', 'mimo2'], true)) {
            $url = ai_site_provider_base_url($provider, (string)($cfg['base_url'] ?? '')) . (string)($cfg['models_path'] ?? '/models');
            $liveModels = ai_site_fetch_json($url, $key);
            $rows = is_array($liveModels['data'] ?? null) ? $liveModels['data'] : (is_array($liveModels) ? $liveModels : []);
            if ($rows) {
                $cfg['models'] = array_map(function($m) {
                    $id = is_array($m) ? (string)($m['id'] ?? $m['model'] ?? $m['name'] ?? '') : (string)$m;
                    return [
                        'id' => $id,
                        'label' => is_array($m) ? (string)($m['name'] ?? $id) : $id,
                        'tags' => ['live'],
                        'thinking' => (bool)preg_match('/(thinking|r1|qwq|k2|pro)/i', $id),
                        'context' => 'live',
                    ];
                }, array_values(array_filter($rows, fn($m) => is_array($m) ? !empty($m['id'] ?? $m['model'] ?? $m['name'] ?? '') : trim((string)$m) !== '')));
            }
        } elseif ($provider === 'openrouter') {
            $url = ai_site_provider_base_url($provider, (string)($cfg['base_url'] ?? '')) . (string)($cfg['models_path'] ?? '/models') . '?output_modalities=all';
            $liveModels = ai_site_fetch_json($url, $key, ['HTTP-Referer: https://example.com', 'X-OpenRouter-Title: KingDungeon AI API Gateway']);
            $rows = is_array($liveModels['data'] ?? null) ? $liveModels['data'] : [];
            if (!empty($cfg['free_only_catalog'])) {
                $rows = array_values(array_filter($rows, fn($m) => is_array($m) && ai_site_openrouter_model_is_free($m)));
            }
            if ($rows) {
                $cfg['models'] = array_map(function($m) {
                    $id = (string)($m['id'] ?? '');
                    $pricing = (array)($m['pricing'] ?? []);
                    $arch = (array)($m['architecture'] ?? []);
                    $supported = array_map('strval', (array)($m['supported_parameters'] ?? []));
                    $tags = ['openrouter', 'live-catalog'];
                    $tags[] = 'free';
                    return [
                        'id' => $id,
                        'label' => (string)($m['name'] ?? $id),
                        'tags' => array_values(array_unique($tags)),
                        'thinking' => in_array('reasoning', $supported, true) || in_array('include_reasoning', $supported, true) || (bool)preg_match('/(thinking|r1|reason|o3|o4|gpt-oss|nemotron)/i', $id),
                        'vision' => in_array('image', array_map('strval', (array)($arch['input_modalities'] ?? [])), true),
                        'context' => (string)($m['context_length'] ?? 'live'),
                        'free' => true,
                    ];
                }, array_values(array_filter($rows, fn($m) => is_array($m) && !empty($m['id']))));
            }
        }
    }
    unset($cfg);
}

foreach ($registry['providers'] as $provider => &$cfg) {
    $cfg['configured'] = !empty($secrets[$provider]['api_key']);
}
unset($cfg);

aif_cache_set('models_live_registry', $registry);
ai_site_json(['ok' => true, 'registry' => $registry]);
?>
