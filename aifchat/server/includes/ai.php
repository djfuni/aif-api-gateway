<?php
/**
 * AIF Chat - AI API 代理模块
 * 
 * 将移动端的聊天请求转发到 OpenAI 兼容 API
 * 支持流式（SSE）和非流式响应
 */

declare(strict_types=1);

/**
 * 获取可用模型列表
 *
 * @return array{default_model: string, models: array}
 */
function getModelList(): array
{
    $models = [
        [
            'id' => 'lite',
            'label' => '轻量快速',
            'type' => 'chat',
            'provider' => 'OpenAI',
            'supports_image_input' => true,
            'is_free' => true,
            'price_label' => '免费',
        ],
        [
            'id' => 'pro',
            'label' => '专业均衡',
            'type' => 'chat',
            'provider' => 'OpenAI',
            'supports_image_input' => true,
            'is_free' => false,
            'price_label' => '按量计费',
        ],
        [
            'id' => 'reasoning',
            'label' => '深度推理',
            'type' => 'reasoning',
            'provider' => 'OpenAI',
            'supports_image_input' => false,
            'is_free' => false,
            'price_label' => '按量计费',
        ],
        [
            'id' => 'github',
            'label' => 'GitHub Models',
            'type' => 'chat',
            'provider' => 'GitHub',
            'supports_image_input' => true,
            'is_free' => true,
            'price_label' => '免费',
        ],
        [
            'id' => 'mimov2.5pro',
            'label' => 'Xiaomi MiMo v2.5 Pro · Key 1',
            'type' => 'reasoning',
            'provider' => 'Xiaomi MiMo Key 1',
            'supports_image_input' => false,
            'is_free' => false,
            'price_label' => 'Token Plan',
        ],
        [
            'id' => 'mimov2.5pro-key2',
            'label' => 'Xiaomi MiMo v2.5 Pro · Key 2',
            'type' => 'reasoning',
            'provider' => 'Xiaomi MiMo Key 2',
            'supports_image_input' => false,
            'is_free' => false,
            'price_label' => 'Token Plan 2',
        ],
        // NVIDIA models
        [
            'id' => 'nvidia-seed-oss-36b',
            'label' => 'NVIDIA Seed-OSS-36B',
            'type' => 'chat',
            'provider' => 'NVIDIA',
            'supports_image_input' => false,
            'is_free' => true,
            'price_label' => '免费',
        ],
        [
            'id' => 'nvidia-minimax-m2-7',
            'label' => 'NVIDIA MiniMax-M2.7',
            'type' => 'chat',
            'provider' => 'NVIDIA',
            'supports_image_input' => false,
            'is_free' => true,
            'price_label' => '免费',
        ],
        [
            'id' => 'nvidia-qwen3-5-122b',
            'label' => 'NVIDIA Qwen3.5-122B',
            'type' => 'chat',
            'provider' => 'NVIDIA',
            'supports_image_input' => false,
            'is_free' => true,
            'price_label' => '免费',
        ],
        [
            'id' => 'nvidia-qwen3-5-397b',
            'label' => 'NVIDIA Qwen3.5-397B',
            'type' => 'chat',
            'provider' => 'NVIDIA',
            'supports_image_input' => false,
            'is_free' => true,
            'price_label' => '免费',
        ],
        [
            'id' => 'nvidia-gemma-4-31b',
            'label' => 'NVIDIA Gemma-4-31B-IT',
            'type' => 'chat',
            'provider' => 'NVIDIA',
            'supports_image_input' => false,
            'is_free' => true,
            'price_label' => '免费',
        ],
    ];

    return [
        'default_model' => 'lite',
        'models' => $models,
    ];
}

/**
 * 构建上游 API 的请求消息体
 */
function buildUpstreamMessages(array $messages): array
{
    $result = [];
    foreach ($messages as $msg) {
        $role = $msg['role'] ?? 'user';
        $content = $msg['content'] ?? '';

        // 如果是数组格式（多模态）
        if (is_array($content)) {
            $result[] = ['role' => $role, 'content' => $content];
        } else {
            $result[] = ['role' => $role, 'content' => $content];
        }
    }
    return $result;
}


function normalizeMimoModelId(string $modelId): string
{
    $raw = preg_replace('/^(mimo2|mimo-2|mimo_v2|xiaomi-mimo2|mimo|xiaomi|mi)[\/:_]+/i', '', trim($modelId));
    $compact = strtolower(preg_replace('/[^a-z0-9]+/', '', $raw));
    $map = [
        'mimov25pro' => 'mimo-v2.5-pro',
        'xiaomimimov25pro' => 'mimo-v2.5-pro',
        'mimov25prokey2' => 'mimo-v2.5-pro',
        'mimov25pro2' => 'mimo-v2.5-pro',
        'mimo2mimov25pro' => 'mimo-v2.5-pro',
        'xiaomimimov25prokey2' => 'mimo-v2.5-pro',
        'mimov25' => 'mimo-v2.5',
        'mimov2pro' => 'mimo-v2-pro',
        'mimov2omni' => 'mimo-v2-omni',
    ];
    return $map[$compact] ?? '';
}

function resolveUpstreamTarget(string $modelId): array
{
    $mimoModel = normalizeMimoModelId($modelId);
    if ($mimoModel !== '') {
        $isKey2 = (bool) preg_match('/^(mimo2|mimo-2|mimo_v2|xiaomi-mimo2)[\/:_-]/i', trim($modelId)) || (bool) preg_match('/(key2|key-2|2)$/i', preg_replace('/[^a-z0-9-]+/i', '', $modelId));
        return [
            'base' => rtrim((string) ($isKey2 ? (config('MIMO2_API_BASE', '') ?: config('MIMO_V2_API_BASE', '') ?: config('XIAOMI_MIMO2_API_BASE', '') ?: config('XIAOMI_MIMO_V2_API_BASE', '') ?: config('MIMO_TOKEN_PLAN_2_API_BASE', '') ?: config('MIMO_API_BASE', 'https://token-plan-cn.xiaomimimo.com/v1')) : config('MIMO_API_BASE', 'https://token-plan-cn.xiaomimimo.com/v1')), '/'),
            'key' => (string) ($isKey2 ? (config('MIMO2_API_KEY', '') ?: config('MIMO_V2_API_KEY', '') ?: config('XIAOMI_MIMO2_API_KEY', '') ?: config('XIAOMI_MIMO_V2_API_KEY', '') ?: config('MIMO_TOKEN_PLAN_2_API_KEY', '')) : (config('MIMO_API_KEY', '') ?: config('MIMO_TOKEN_PLAN_API_KEY', '') ?: config('XIAOMI_MIMO_API_KEY', ''))),
            'model' => (string) ($isKey2 ? (config('MIMO2_DEFAULT_MODEL', '') ?: $mimoModel) : config('MIMO_DEFAULT_MODEL', $mimoModel)),
            'provider' => $isKey2 ? 'mimo2' : 'mimo',
        ];
    }

    return [
        'base' => rtrim((string) config('AI_API_BASE', 'https://api.openai.com/v1'), '/'),
        'key' => (string) config('AI_API_KEY', ''),
        'model' => resolveUpstreamModel($modelId),
        'provider' => 'default',
    ];
}

function addMimoIdentityGuard(array $messages, string $modelName): array
{
    array_unshift($messages, [
        'role' => 'system',
        'content' => '你正在通过小米 MiMo 模型服务回答。若用户询问你的模型、开发方或身份，只能说明当前请求路由到 Xiaomi MiMo（模型标签 ' . $modelName . '）；不要自称 GLM、Z.ai、智谱、NVIDIA 或其他模型。',
    ]);
    return $messages;
}

/**
 * 映射模型 ID 到上游 API 模型名
 */
function resolveUpstreamModel(string $modelId): string
{
    $map = [
        'lite' => config('AI_DEFAULT_MODEL', 'Qwen/Qwen2.5-7B-Instruct'),
        'pro' => config('AI_PRO_MODEL', 'Qwen/Qwen2.5-72B-Instruct'),
        'reasoning' => config('AI_REASONING_MODEL', 'deepseek-ai/DeepSeek-R1'),
        'github' => config('AI_GITHUB_MODEL', 'gpt-4o-mini'),
        'nvidia-seed-oss-36b' => 'bytedance/seed-oss-36b-instruct',
        'nvidia-minimax-m2-7' => 'minimaxai/minimax-m2.7',
        'nvidia-qwen3-5-122b' => 'qwen/qwen3.5-122b-a10b',
        'nvidia-qwen3-5-397b' => 'qwen/qwen3.5-397b-a17b',
        'nvidia-gemma-4-31b' => 'google/gemma-4-31b-it',
    ];
    return $map[$modelId] ?? config('AI_DEFAULT_MODEL', 'Qwen/Qwen2.5-7B-Instruct');
}


/**
 * 处理非流式聊天请求
 *
 * @return array{message: string, model?: string, usage?: array}
 */
function handleChat(array $messages, string $modelId, bool $deepThinking = false, ?string $systemPrompt = null): array
{
    $target = resolveUpstreamTarget($modelId);
    $apiBase = $target['base'];
    $apiKey = $target['key'];
    $upstreamModel = $target['model'];

    $upstreamMessages = buildUpstreamMessages($messages);
    if (in_array(($target['provider'] ?? ''), ['mimo', 'mimo2'], true)) {
        if ($apiKey === '') throw new \RuntimeException('MiMo API Key 未配置：Key 1 请设置 MIMO_API_KEY，Key 2 请设置 MIMO2_API_KEY');
        $upstreamMessages = addMimoIdentityGuard($upstreamMessages, $upstreamModel);
    }

    // 插入 system prompt
    if ($systemPrompt) {
        array_unshift($upstreamMessages, ['role' => 'system', 'content' => $systemPrompt]);
    }

    $url = $apiBase . '/chat/completions';

    $payload = [
        'model' => $upstreamModel,
        'messages' => $upstreamMessages,
        'stream' => false,
    ];

    // 深度思考模式（仅对支持推理的模型）
    if ($deepThinking && $modelId === 'reasoning') {
        $payload['reasoning_effort'] = 'high';
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new \RuntimeException('AI 服务请求失败: ' . $error);
    }

    if ($httpCode !== 200) {
        $body = json_decode($response, true);
        $errorMsg = $body['error']['message'] ?? 'AI 服务返回错误 (' . $httpCode . ')';
        throw new \RuntimeException($errorMsg);
    }

    $data = json_decode($response, true);

    return [
        'message' => $data['choices'][0]['message']['content'] ?? '',
        'model' => $data['model'] ?? $upstreamModel,
        'usage' => $data['usage'] ?? null,
    ];
}

/**
 * 处理流式聊天请求（SSE）
 * 直接输出 SSE 响应流
 */
function handleChatStream(array $messages, string $modelId, bool $deepThinking = false, ?string $systemPrompt = null): void
{
    $target = resolveUpstreamTarget($modelId);
    $apiBase = $target['base'];
    $apiKey = $target['key'];
    $upstreamModel = $target['model'];

    $upstreamMessages = buildUpstreamMessages($messages);
    if (in_array(($target['provider'] ?? ''), ['mimo', 'mimo2'], true)) {
        if ($apiKey === '') throw new \RuntimeException('MiMo API Key 未配置：Key 1 请设置 MIMO_API_KEY，Key 2 请设置 MIMO2_API_KEY');
        $upstreamMessages = addMimoIdentityGuard($upstreamMessages, $upstreamModel);
    }

    if ($systemPrompt) {
        array_unshift($upstreamMessages, ['role' => 'system', 'content' => $systemPrompt]);
    }

    $url = $apiBase . '/chat/completions';

    $payload = [
        'model' => $upstreamModel,
        'messages' => $upstreamMessages,
        'stream' => true,
        'stream_options' => ['include_usage' => true],
    ];

    if ($deepThinking && $modelId === 'reasoning') {
        $payload['reasoning_effort'] = 'high';
    }

    // 设置 SSE 响应头
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_WRITEFUNCTION => function ($ch, $data) {
            // 转发上游 SSE 数据到客户端
            echo $data;
            ob_flush();
            flush();
            return strlen($data);
        },
    ]);

    curl_exec($ch);

    if (curl_error($ch)) {
        // 流式出错时输出错误事件
        echo "data: " . json_encode([
            'error' => true,
            'message' => 'AI 服务流式请求失败: ' . curl_error($ch),
        ]) . "\n\n";
        ob_flush();
        flush();
    }

    // 发送结束标记
    echo "data: [DONE]\n\n";
    ob_flush();
    flush();

    curl_close($ch);
}
