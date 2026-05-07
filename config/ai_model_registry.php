<?php
return array(
  'version' => '2026-05-06-newapi-gateway',
  'providers' => array(
    'moonshot' => array(
      'label' => '月之暗面 Kimi',
      'prefix' => 'moonshot::',
      // 【修改｜兼容性优化｜风险等级：低】保留原中国区域名；支持 MOONSHOT_API_BASE/KIMI_API_BASE 切换全球区或 Kimi Code 端点。
      'base_url' => 'https://api.moonshot.cn/v1',
      'drop_request_fields' => array('logprobs', 'top_logprobs'),
      'chat_path' => '/chat/completions',
      'models_path' => '/models',
      'credential_key' => 'moonshot',
      'live_catalog' => true,
      'models' => array(
        array(
          'id' => 'kimi-k2.6',
          'label' => 'Kimi K2.6',
          'tags' => array(
            'agent',
            'coding',
            'latest'
          ),
          'thinking' => true,
          'context' => 'long'
        ),
        array(
          'id' => 'kimi-k2.5',
          'label' => 'Kimi K2.5',
          'tags' => array(
            'vision',
            'agent'
          ),
          'thinking' => true,
          'context' => '256K'
        ),
        array(
          'id' => 'kimi-k2',
          'label' => 'Kimi K2',
          'tags' => array(
            'coding',
            'agent'
          ),
          'thinking' => true,
          'context' => 'long'
        )
      )
    ),
    'bailian' => array(
      'label' => '阿里云百炼（新加坡免费额度）',
      'prefix' => 'bailian::',
      // 【修改｜兼容性优化｜风险等级：低】保留新加坡默认端点；支持 DASHSCOPE_API_BASE/BAILIAN_API_BASE 按区域覆盖。
      'base_url' => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',
      'drop_request_fields' => array('logprobs', 'top_logprobs'),
      'chat_path' => '/chat/completions',
      'credential_key' => 'bailian',
      'live_catalog' => false,
      'free_quota_region' => 'Singapore',
      'models' => array(
        array(
          'id' => 'qwen3.6-max-preview',
          'label' => 'qwen3.6-max-preview',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3-max',
          'label' => 'qwen3-max',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3-max-preview',
          'label' => 'qwen3-max-preview',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen-max',
          'label' => 'qwen-max',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen-max-latest',
          'label' => 'qwen-max-latest',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3.6-plus',
          'label' => 'qwen3.6-plus',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3.6-plus-2026-04-02',
          'label' => 'qwen3.6-plus-2026-04-02',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3.5-plus',
          'label' => 'qwen3.5-plus',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3.5-plus-2026-02-15',
          'label' => 'qwen3.5-plus-2026-02-15',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen-plus',
          'label' => 'qwen-plus',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen-plus-latest',
          'label' => 'qwen-plus-latest',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3.6-flash',
          'label' => 'qwen3.6-flash',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3.6-flash-2026-04-16',
          'label' => 'qwen3.6-flash-2026-04-16',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3.5-flash',
          'label' => 'qwen3.5-flash',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3.5-flash-2026-02-23',
          'label' => 'qwen3.5-flash-2026-02-23',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen-flash',
          'label' => 'qwen-flash',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen-flash-2025-07-28',
          'label' => 'qwen-flash-2025-07-28',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen-turbo',
          'label' => 'qwen-turbo',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen-turbo-latest',
          'label' => 'qwen-turbo-latest',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3-coder-plus',
          'label' => 'qwen3-coder-plus',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3-coder-flash',
          'label' => 'qwen3-coder-flash',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen-coder-plus',
          'label' => 'qwen-coder-plus',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen-coder-plus-latest',
          'label' => 'qwen-coder-plus-latest',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen-coder-turbo',
          'label' => 'qwen-coder-turbo',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen-coder-turbo-latest',
          'label' => 'qwen-coder-turbo-latest',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen-long',
          'label' => 'qwen-long',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen-long-latest',
          'label' => 'qwen-long-latest',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwq-plus',
          'label' => 'qwq-plus',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => true,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwq-plus-latest',
          'label' => 'qwq-plus-latest',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => true,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen-math-plus',
          'label' => 'qwen-math-plus',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen-math-plus-latest',
          'label' => 'qwen-math-plus-latest',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen-math-turbo',
          'label' => 'qwen-math-turbo',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen-math-turbo-latest',
          'label' => 'qwen-math-turbo-latest',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3.6-35b-a3b',
          'label' => 'qwen3.6-35b-a3b',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3.5-397b-a17b',
          'label' => 'qwen3.5-397b-a17b',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3.5-122b-a10b',
          'label' => 'qwen3.5-122b-a10b',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3.5-27b',
          'label' => 'qwen3.5-27b',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3.5-35b-a3b',
          'label' => 'qwen3.5-35b-a3b',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3-next-80b-a3b-thinking',
          'label' => 'qwen3-next-80b-a3b-thinking',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => true,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3-next-80b-a3b-instruct',
          'label' => 'qwen3-next-80b-a3b-instruct',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3-235b-a22b-thinking-2507',
          'label' => 'qwen3-235b-a22b-thinking-2507',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => true,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3-235b-a22b-instruct-2507',
          'label' => 'qwen3-235b-a22b-instruct-2507',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3-30b-a3b-thinking-2507',
          'label' => 'qwen3-30b-a3b-thinking-2507',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => true,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3-30b-a3b-instruct-2507',
          'label' => 'qwen3-30b-a3b-instruct-2507',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3-235b-a22b',
          'label' => 'qwen3-235b-a22b',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3-32b',
          'label' => 'qwen3-32b',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3-30b-a3b',
          'label' => 'qwen3-30b-a3b',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3-14b',
          'label' => 'qwen3-14b',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3-8b',
          'label' => 'qwen3-8b',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3-4b',
          'label' => 'qwen3-4b',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3-1.7b',
          'label' => 'qwen3-1.7b',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen3-0.6b',
          'label' => 'qwen3-0.6b',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwq-32b',
          'label' => 'qwq-32b',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => true,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwq-32b-preview',
          'label' => 'qwq-32b-preview',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => true,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen2.5-14b-instruct-1m',
          'label' => 'qwen2.5-14b-instruct-1m',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen2.5-7b-instruct-1m',
          'label' => 'qwen2.5-7b-instruct-1m',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen2.5-72b-instruct',
          'label' => 'qwen2.5-72b-instruct',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen2.5-32b-instruct',
          'label' => 'qwen2.5-32b-instruct',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen2.5-14b-instruct',
          'label' => 'qwen2.5-14b-instruct',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen2.5-7b-instruct',
          'label' => 'qwen2.5-7b-instruct',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen2.5-math-72b-instruct',
          'label' => 'qwen2.5-math-72b-instruct',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen2.5-math-7b-instruct',
          'label' => 'qwen2.5-math-7b-instruct',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen2.5-coder-32b-instruct',
          'label' => 'qwen2.5-coder-32b-instruct',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen2.5-coder-14b-instruct',
          'label' => 'qwen2.5-coder-14b-instruct',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'qwen2.5-coder-7b-instruct',
          'label' => 'qwen2.5-coder-7b-instruct',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'codeqwen1.5-7b-chat',
          'label' => 'codeqwen1.5-7b-chat',
          'tags' => array(
            'bailian',
            'free-quota',
            'singapore'
          ),
          'thinking' => false,
          'context' => 'varies'
        )
      )
    ),
    'github' => array(
      'label' => 'GitHub Models',
      'prefix' => 'github::',
      'base_url' => 'https://models.github.ai/inference',
      'catalog_url' => 'https://models.github.ai/catalog/models',
      'chat_path' => '/chat/completions',
      'credential_key' => 'github',
      // 【新增｜兼容性优化｜风险等级：低】GitHub Models REST Inference 需要 vendor Accept 与版本头；网关出站专用，不影响对外 API。
      'accept_header' => 'application/vnd.github+json',
      'extra_headers' => array(
        'X-GitHub-Api-Version' => '2026-03-10',
      ),
      'drop_request_fields' => array('logprobs', 'top_logprobs', 'user'),
      'live_catalog' => true,
      'models' => array(
        array(
          'id' => 'openai/gpt-4.1',
          'label' => 'gpt-4.1',
          'tags' => array(
            'github-models',
            'free-tier',
            'catalog'
          ),
          'thinking' => false,
          'context' => 'catalog'
        ),
        array(
          'id' => 'openai/gpt-4.1-mini',
          'label' => 'gpt-4.1-mini',
          'tags' => array(
            'github-models',
            'free-tier',
            'catalog'
          ),
          'thinking' => false,
          'context' => 'catalog'
        ),
        array(
          'id' => 'openai/gpt-4.1-nano',
          'label' => 'gpt-4.1-nano',
          'tags' => array(
            'github-models',
            'free-tier',
            'catalog'
          ),
          'thinking' => false,
          'context' => 'catalog'
        ),
        array(
          'id' => 'openai/gpt-4o',
          'label' => 'gpt-4o',
          'tags' => array(
            'github-models',
            'free-tier',
            'catalog'
          ),
          'thinking' => false,
          'context' => 'catalog'
        ),
        array(
          'id' => 'openai/gpt-4o-mini',
          'label' => 'gpt-4o-mini',
          'tags' => array(
            'github-models',
            'free-tier',
            'catalog'
          ),
          'thinking' => false,
          'context' => 'catalog'
        ),
        array(
          'id' => 'openai/o3-mini',
          'label' => 'o3-mini',
          'tags' => array(
            'github-models',
            'free-tier',
            'catalog'
          ),
          'thinking' => true,
          'context' => 'catalog'
        ),
        array(
          'id' => 'deepseek/DeepSeek-R1',
          'label' => 'DeepSeek-R1',
          'tags' => array(
            'github-models',
            'free-tier',
            'catalog'
          ),
          'thinking' => true,
          'context' => 'catalog'
        ),
        array(
          'id' => 'deepseek/DeepSeek-V3-0324',
          'label' => 'DeepSeek-V3-0324',
          'tags' => array(
            'github-models',
            'free-tier',
            'catalog'
          ),
          'thinking' => false,
          'context' => 'catalog'
        ),
        array(
          'id' => 'microsoft/Phi-4',
          'label' => 'Phi-4',
          'tags' => array(
            'github-models',
            'free-tier',
            'catalog'
          ),
          'thinking' => false,
          'context' => 'catalog'
        ),
        array(
          'id' => 'microsoft/Phi-4-mini-instruct',
          'label' => 'Phi-4-mini-instruct',
          'tags' => array(
            'github-models',
            'free-tier',
            'catalog'
          ),
          'thinking' => false,
          'context' => 'catalog'
        ),
        array(
          'id' => 'microsoft/Phi-4-multimodal-instruct',
          'label' => 'Phi-4-multimodal-instruct',
          'tags' => array(
            'github-models',
            'free-tier',
            'catalog'
          ),
          'thinking' => false,
          'context' => 'catalog'
        ),
        array(
          'id' => 'meta/Llama-3.3-70B-Instruct',
          'label' => 'Llama-3.3-70B-Instruct',
          'tags' => array(
            'github-models',
            'free-tier',
            'catalog'
          ),
          'thinking' => false,
          'context' => 'catalog'
        ),
        array(
          'id' => 'meta/Llama-3.2-11B-Vision-Instruct',
          'label' => 'Llama-3.2-11B-Vision-Instruct',
          'tags' => array(
            'github-models',
            'free-tier',
            'catalog'
          ),
          'thinking' => false,
          'context' => 'catalog'
        ),
        array(
          'id' => 'mistral-ai/Mistral-Large-2411',
          'label' => 'Mistral-Large-2411',
          'tags' => array(
            'github-models',
            'free-tier',
            'catalog'
          ),
          'thinking' => false,
          'context' => 'catalog'
        ),
        array(
          'id' => 'mistral-ai/Ministral-3B',
          'label' => 'Ministral-3B',
          'tags' => array(
            'github-models',
            'free-tier',
            'catalog'
          ),
          'thinking' => false,
          'context' => 'catalog'
        ),
        array(
          'id' => 'cohere/Cohere-command-r',
          'label' => 'Cohere-command-r',
          'tags' => array(
            'github-models',
            'free-tier',
            'catalog'
          ),
          'thinking' => false,
          'context' => 'catalog'
        ),
        array(
          'id' => 'ai21-labs/AI21-Jamba-1.5-Large',
          'label' => 'AI21-Jamba-1.5-Large',
          'tags' => array(
            'github-models',
            'free-tier',
            'catalog'
          ),
          'thinking' => false,
          'context' => 'catalog'
        )
      )
    ),
    'siliconflow' => array(
      'label' => '硅基流动 SiliconFlow',
      'prefix' => 'siliconflow::',
      'base_url' => 'https://api.siliconflow.cn/v1',
      'models_path' => '/models',
      'chat_path' => '/chat/completions',
      'credential_key' => 'siliconflow',
      'live_catalog' => true,
      'models' => array(
        array(
          'id' => 'deepseek-ai/DeepSeek-V4-Flash',
          'label' => 'DeepSeek-V4-Flash',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'deepseek-ai/DeepSeek-R1',
          'label' => 'DeepSeek-R1',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => true,
          'context' => 'varies'
        ),
        array(
          'id' => 'deepseek-ai/DeepSeek-V3.2',
          'label' => 'DeepSeek-V3.2',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'deepseek-ai/DeepSeek-V3.2-Exp',
          'label' => 'DeepSeek-V3.2-Exp',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'deepseek-ai/DeepSeek-V3.1',
          'label' => 'DeepSeek-V3.1',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'deepseek-ai/DeepSeek-V3',
          'label' => 'DeepSeek-V3',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'deepseek-ai/DeepSeek-R1-Distill-Qwen-32B',
          'label' => 'DeepSeek-R1-Distill-Qwen-32B',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => true,
          'context' => 'varies'
        ),
        array(
          'id' => 'Qwen/Qwen3-Next-80B-A3B-Thinking',
          'label' => 'Qwen3-Next-80B-A3B-Thinking',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => true,
          'context' => 'varies'
        ),
        array(
          'id' => 'Qwen/Qwen3-Next-80B-A3B-Instruct',
          'label' => 'Qwen3-Next-80B-A3B-Instruct',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'Qwen/Qwen3-235B-A22B',
          'label' => 'Qwen3-235B-A22B',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'Qwen/Qwen3-235B-A22B-Instruct-2507',
          'label' => 'Qwen3-235B-A22B-Instruct-2507',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'Qwen/Qwen3-235B-A22B-Thinking-2507',
          'label' => 'Qwen3-235B-A22B-Thinking-2507',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => true,
          'context' => 'varies'
        ),
        array(
          'id' => 'Qwen/Qwen3-30B-A3B-Instruct-2507',
          'label' => 'Qwen3-30B-A3B-Instruct-2507',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'Qwen/Qwen3-30B-A3B-Thinking-2507',
          'label' => 'Qwen3-30B-A3B-Thinking-2507',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => true,
          'context' => 'varies'
        ),
        array(
          'id' => 'Qwen/Qwen3-32B',
          'label' => 'Qwen3-32B',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'Qwen/Qwen3-14B',
          'label' => 'Qwen3-14B',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'Qwen/Qwen3-8B',
          'label' => 'Qwen3-8B',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'Qwen/QwQ-32B',
          'label' => 'QwQ-32B',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => true,
          'context' => 'varies'
        ),
        array(
          'id' => 'Qwen/Qwen2.5-72B-Instruct',
          'label' => 'Qwen2.5-72B-Instruct',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'Qwen/Qwen2.5-72B-Instruct-128K',
          'label' => 'Qwen2.5-72B-Instruct-128K',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'Qwen/Qwen2.5-32B-Instruct',
          'label' => 'Qwen2.5-32B-Instruct',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'Qwen/Qwen2.5-14B-Instruct',
          'label' => 'Qwen2.5-14B-Instruct',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'Qwen/Qwen2.5-7B-Instruct',
          'label' => 'Qwen2.5-7B-Instruct',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'Qwen/Qwen2.5-Coder-32B-Instruct',
          'label' => 'Qwen2.5-Coder-32B-Instruct',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'openai/gpt-oss-120b',
          'label' => 'gpt-oss-120b',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'openai/gpt-oss-20b',
          'label' => 'gpt-oss-20b',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'moonshotai/Kimi-K2-Instruct',
          'label' => 'Kimi-K2-Instruct',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'moonshotai/Kimi-K2-Instruct-0905',
          'label' => 'Kimi-K2-Instruct-0905',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'moonshotai/Kimi-K2-Thinking',
          'label' => 'Kimi-K2-Thinking',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => true,
          'context' => 'varies'
        ),
        array(
          'id' => 'MiniMaxAI/MiniMax-M2.5',
          'label' => 'MiniMax-M2.5',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'MiniMaxAI/MiniMax-M2.1',
          'label' => 'MiniMax-M2.1',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'THUDM/GLM-4-32B-0414',
          'label' => 'GLM-4-32B-0414',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'THUDM/GLM-4-9B-0414',
          'label' => 'GLM-4-9B-0414',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'THUDM/GLM-Z1-32B-0414',
          'label' => 'GLM-Z1-32B-0414',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'zai-org/GLM-5.1',
          'label' => 'GLM-5.1',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'zai-org/GLM-5',
          'label' => 'GLM-5',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'zai-org/GLM-4.7',
          'label' => 'GLM-4.7',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'zai-org/GLM-4.6',
          'label' => 'GLM-4.6',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'zai-org/GLM-4.5',
          'label' => 'GLM-4.5',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'baidu/ERNIE-4.5-300B-A47B',
          'label' => 'ERNIE-4.5-300B-A47B',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'ByteDance-Seed/Seed-OSS-36B-Instruct',
          'label' => 'Seed-OSS-36B-Instruct',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'inclusionAI/Ring-flash-2.0',
          'label' => 'Ring-flash-2.0',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'inclusionAI/Ling-mini-2.0',
          'label' => 'Ling-mini-2.0',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'inclusionAI/Ling-flash-2.0',
          'label' => 'Ling-flash-2.0',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'tencent/Hunyuan-A13B-Instruct',
          'label' => 'Hunyuan-A13B-Instruct',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        ),
        array(
          'id' => 'tencent/Hunyuan-MT-7B',
          'label' => 'Hunyuan-MT-7B',
          'tags' => array(
            'siliconflow',
            'live-catalog'
          ),
          'thinking' => false,
          'context' => 'varies'
        )
      )
    ),

    'mimo' => array(
      'label' => '小米 MiMo Token Plan',
      'prefix' => 'mimo::',
      'base_url' => getenv('MIMO_API_BASE') ?: getenv('XIAOMI_MIMO_API_BASE') ?: getenv('MIMO_TOKEN_PLAN_API_BASE') ?: 'https://token-plan-cn.xiaomimimo.com/v1',
      'anthropic_base_url' => 'https://token-plan-cn.xiaomimimo.com/anthropic',
      'chat_path' => '/chat/completions',
      'audio_speech_path' => '/audio/speech',
      'models_path' => '/models',
      'credential_key' => 'mimo',
      'live_catalog' => false,
      'catalog_requires_key' => true,
      'drop_request_fields' => array('logprobs', 'top_logprobs'),
      'models' => array(
        array(
          'id' => 'mimo-v2.5-pro',
          'label' => 'MiMo v2.5 Pro',
          'aliases' => array('MiMo-v2.5-pro', 'mimov2.5pro', 'mimo-v2.5pro', 'mimo-v25-pro', 'xiaomi-mimo-v2.5-pro'),
          'tags' => array('mimo', 'token-plan', 'pro', 'reasoning'),
          'thinking' => true,
          'context' => 'plan',
          'max_tokens' => 8192,
          'token_multiplier' => 1.2
        ),
        array(
          'id' => 'mimo-v2.5',
          'label' => 'MiMo v2.5',
          'aliases' => array('MiMo-v2.5', 'mimov2.5', 'mimo-v25'),
          'tags' => array('mimo', 'token-plan', 'chat'),
          'thinking' => false,
          'context' => 'plan',
          'max_tokens' => 4096,
          'token_multiplier' => 1.0
        ),
        array(
          'id' => 'mimo-v2-pro',
          'label' => 'MiMo v2 Pro',
          'aliases' => array('MiMo-v2-pro', 'mimov2pro'),
          'tags' => array('mimo', 'token-plan', 'pro'),
          'thinking' => true,
          'context' => 'plan',
          'max_tokens' => 8192,
          'token_multiplier' => 1.0
        ),
        array(
          'id' => 'mimo-v2-omni',
          'label' => 'MiMo v2 Omni',
          'aliases' => array('MiMo-v2-Omni', 'mimov2omni'),
          'tags' => array('mimo', 'token-plan', 'omni', 'multimodal'),
          'thinking' => false,
          'vision' => true,
          'context' => 'plan',
          'max_tokens' => 4096,
          'token_multiplier' => 1.0
        ),
        array(
          'id' => 'mimo-v2.5-tts-voiceclone',
          'label' => 'MiMo v2.5 TTS VoiceClone',
          'aliases' => array('MiMo-v2.5-TTS-VoiceClone', 'mimov2.5ttsvoiceclone'),
          'type' => 'audio_tts',
          'supports_chat' => false,
          'tags' => array('mimo', 'token-plan', 'tts', 'voice-clone'),
          'context' => 'audio',
          'token_multiplier' => 1.0
        ),
        array(
          'id' => 'mimo-v2.5-tts-voicedesign',
          'label' => 'MiMo v2.5 TTS VoiceDesign',
          'aliases' => array('MiMo-v2.5-TTS-VoiceDesign', 'mimov2.5ttsvoicedesign'),
          'type' => 'audio_tts',
          'supports_chat' => false,
          'tags' => array('mimo', 'token-plan', 'tts', 'voice-design'),
          'context' => 'audio',
          'token_multiplier' => 1.0
        ),
        array(
          'id' => 'mimo-v2.5-tts',
          'label' => 'MiMo v2.5 TTS',
          'aliases' => array('MiMo-v2.5-TTS', 'mimov2.5tts'),
          'type' => 'audio_tts',
          'supports_chat' => false,
          'tags' => array('mimo', 'token-plan', 'tts'),
          'context' => 'audio',
          'token_multiplier' => 1.0
        ),
        array(
          'id' => 'mimo-v2-tts',
          'label' => 'MiMo v2 TTS',
          'aliases' => array('MiMo-v2-TTS', 'mimov2tts'),
          'type' => 'audio_tts',
          'supports_chat' => false,
          'tags' => array('mimo', 'token-plan', 'tts'),
          'context' => 'audio',
          'token_multiplier' => 1.0
        )
      )
    ),

    'mimo2' => array(
      'label' => '小米 MiMo Key 2（二号密钥）',
      'prefix' => 'mimo2::',
      'base_url' => getenv('MIMO2_API_BASE') ?: getenv('MIMO_V2_API_BASE') ?: getenv('XIAOMI_MIMO2_API_BASE') ?: getenv('XIAOMI_MIMO_V2_API_BASE') ?: getenv('MIMO_TOKEN_PLAN_2_API_BASE') ?: getenv('MIMO_API_BASE') ?: getenv('XIAOMI_MIMO_API_BASE') ?: getenv('MIMO_TOKEN_PLAN_API_BASE') ?: 'https://token-plan-cn.xiaomimimo.com/v1',
      'anthropic_base_url' => 'https://token-plan-cn.xiaomimimo.com/anthropic',
      'chat_path' => '/chat/completions',
      'audio_speech_path' => '/audio/speech',
      'models_path' => '/models',
      'credential_key' => 'mimo2',
      'live_catalog' => false,
      'catalog_requires_key' => true,
      'drop_request_fields' => array('logprobs', 'top_logprobs'),
      'models' => array(
        array(
          'id' => 'mimo-v2.5-pro',
          'label' => 'MiMo v2.5 Pro · Key 2',
          'aliases' => array('MiMo-v2.5-pro', 'mimov2.5pro', 'mimo-v2.5pro', 'MiMo-v2.5-pro-Key2', 'mimov2.5pro-key2', 'mimo-v2.5-pro-key2', 'mimo2-v2.5-pro', 'xiaomi-mimo-v2.5-pro-key2'),
          'tags' => array('mimo', 'mimo2', 'token-plan', 'key-2', 'pro', 'reasoning'),
          'thinking' => true,
          'context' => 'plan',
          'max_tokens' => 8192,
          'token_multiplier' => 1.2
        ),
        array(
          'id' => 'mimo-v2.5',
          'label' => 'MiMo v2.5 · Key 2',
          'aliases' => array('MiMo-v2.5', 'mimov2.5', 'mimo-v25', 'MiMo-v2.5-Key2', 'mimov2.5-key2', 'mimo-v2.5-key2', 'mimo2-v2.5'),
          'tags' => array('mimo', 'mimo2', 'token-plan', 'key-2', 'chat'),
          'thinking' => false,
          'context' => 'plan',
          'max_tokens' => 4096,
          'token_multiplier' => 1.0
        ),
        array(
          'id' => 'mimo-v2-pro',
          'label' => 'MiMo v2 Pro · Key 2',
          'aliases' => array('MiMo-v2-pro', 'mimov2pro', 'MiMo-v2-pro-Key2', 'mimov2pro-key2', 'mimo-v2-pro-key2', 'mimo2-v2-pro'),
          'tags' => array('mimo', 'mimo2', 'token-plan', 'key-2', 'pro'),
          'thinking' => true,
          'context' => 'plan',
          'max_tokens' => 8192,
          'token_multiplier' => 1.0
        )
      )
    ),
    'openrouter' => array(
      'label' => 'OpenRouter（全部免费模型）',
      'prefix' => 'openrouter::',
      'base_url' => 'https://openrouter.ai/api/v1',
      'chat_path' => '/chat/completions',
      'models_path' => '/models',
      'credential_key' => 'openrouter',
      'live_catalog' => true,
      'catalog_requires_key' => false,
      'free_only_catalog' => true,
      'cache_ttl_seconds' => 21600,
      'extra_headers' => array(
        'HTTP-Referer' => '{origin}',
        'X-OpenRouter-Title' => 'KingDungeon AI API Gateway'
      ),
      'models' => array(
        array(
          'id' => 'openrouter/free',
          'label' => 'OpenRouter Free Router',
          'tags' => array('openrouter', 'free', 'router'),
          'thinking' => false,
          'context' => 'auto',
          'free' => true
        ),
        array(
          'id' => 'openrouter/owl-alpha',
          'label' => 'Owl Alpha',
          'tags' => array('openrouter', 'free', 'agent'),
          'thinking' => true,
          'context' => '1048756',
          'free' => true
        ),
        array(
          'id' => 'nvidia/nemotron-3-nano-omni-30b-a3b-reasoning:free',
          'label' => 'NVIDIA Nemotron 3 Nano Omni Reasoning Free',
          'tags' => array('openrouter', 'free', 'nvidia', 'multimodal'),
          'thinking' => true,
          'vision' => true,
          'context' => '256000',
          'free' => true
        ),
        array(
          'id' => 'poolside/laguna-m.1:free',
          'label' => 'Poolside Laguna M.1 Free',
          'tags' => array('openrouter', 'free', 'coding'),
          'thinking' => true,
          'context' => '131072',
          'free' => true
        ),
        array(
          'id' => 'poolside/laguna-xs.2:free',
          'label' => 'Poolside Laguna XS.2 Free',
          'tags' => array('openrouter', 'free', 'coding'),
          'thinking' => true,
          'context' => '131072',
          'free' => true
        ),
        array(
          'id' => 'deepseek/deepseek-r1-0528:free',
          'label' => 'DeepSeek R1 0528 Free',
          'tags' => array('openrouter', 'free', 'reasoning'),
          'thinking' => true,
          'context' => 'varies',
          'free' => true
        ),
        array(
          'id' => 'z-ai/glm-4.5-air:free',
          'label' => 'GLM 4.5 Air Free',
          'tags' => array('openrouter', 'free', 'z-ai'),
          'thinking' => true,
          'context' => 'varies',
          'free' => true
        ),
        array(
          'id' => 'openai/gpt-oss-120b:free',
          'label' => 'OpenAI GPT-OSS 120B Free',
          'tags' => array('openrouter', 'free', 'openai'),
          'thinking' => true,
          'context' => 'varies',
          'free' => true
        ),
        array(
          'id' => 'meta-llama/llama-3.3-70b-instruct:free',
          'label' => 'Meta Llama 3.3 70B Instruct Free',
          'tags' => array('openrouter', 'free', 'llama'),
          'thinking' => false,
          'context' => '65536',
          'free' => true
        )
      )
    )
  )
);
