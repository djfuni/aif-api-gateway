<?php

function sponsor_site_profile(): array {
    $defaults = [
        'site_name' => '小付music',
        'payment_site_name' => '小付music',
    ];
    $file = __DIR__ . '/site_info.json';
    if (!is_file($file)) return $defaults;
    $raw = @file_get_contents($file);
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) return $defaults;
    return array_merge($defaults, $data);
}

function sponsor_payment_provider_catalog(): array {
    return [
        'epay' => [
            'key' => 'epay',
            'label' => '易支付',
            'default_apiurl' => 'https://mzf.yuvps.com/xpay/epay/',
        ],
        'codepay' => [
            'key' => 'codepay',
            'label' => '码支付',
            'default_apiurl' => '',
        ],
    ];
}

function sponsor_payment_config_defaults(): array {
    return [
        'provider' => 'epay',
        'epay' => [
            'apiurl' => 'https://mzf.yuvps.com/xpay/epay/',
            'pid' => '10478',
            'key' => 'fM74EyWDJZBbvwXre8r3',
            'sign_type' => 'MD5',
        ],
        'codepay' => [
            'apiurl' => '',
            'pid' => '',
            'key' => '',
            'sign_type' => 'MD5',
        ],
    ];
}

function sponsor_normalize_provider(string $value): string {
    $provider = strtolower(trim($value));
    return array_key_exists($provider, sponsor_payment_provider_catalog()) ? $provider : 'epay';
}

function sponsor_payment_config(): array {
    $defaults = sponsor_payment_config_defaults();
    $file = __DIR__ . '/config/payment_config.php';
    if (!is_file($file)) {
        return $defaults;
    }
    $loaded = require $file;
    if (!is_array($loaded)) {
        return $defaults;
    }

    $provider = sponsor_normalize_provider((string)($loaded['provider'] ?? $defaults['provider']));
    $config = [
        'provider' => $provider,
        'epay' => array_merge($defaults['epay'], is_array($loaded['epay'] ?? null) ? $loaded['epay'] : []),
        'codepay' => array_merge($defaults['codepay'], is_array($loaded['codepay'] ?? null) ? $loaded['codepay'] : []),
    ];

    foreach (['epay', 'codepay'] as $item) {
        $config[$item]['apiurl'] = rtrim(trim((string)($config[$item]['apiurl'] ?? '')), '/');
        $config[$item]['pid'] = trim((string)($config[$item]['pid'] ?? ''));
        $config[$item]['key'] = trim((string)($config[$item]['key'] ?? ''));
        $config[$item]['sign_type'] = strtoupper(trim((string)($config[$item]['sign_type'] ?? 'MD5'))) ?: 'MD5';
    }

    return $config;
}

function sponsor_gateway_config(): array {
    $catalog = sponsor_payment_provider_catalog();
    $config = sponsor_payment_config();
    $provider = sponsor_normalize_provider((string)($config['provider'] ?? 'epay'));
    $providerMeta = $catalog[$provider] ?? $catalog['epay'];
    $providerConfig = is_array($config[$provider] ?? null) ? $config[$provider] : [];
    $site = sponsor_site_profile();
    $siteName = trim((string)($site['payment_site_name'] ?? $site['site_name'] ?? '小付music')) ?: '小付music';
    $apiurl = trim((string)($providerConfig['apiurl'] ?? ''));
    if ($apiurl === '') {
        $apiurl = rtrim((string)($providerMeta['default_apiurl'] ?? ''), '/');
    }

    return [
        'provider' => $provider,
        'provider_label' => (string)($providerMeta['label'] ?? '支付网关'),
        'apiurl' => $apiurl,
        'pid' => trim((string)($providerConfig['pid'] ?? '')),
        'key' => trim((string)($providerConfig['key'] ?? '')),
        'sign_type' => strtoupper(trim((string)($providerConfig['sign_type'] ?? 'MD5'))) ?: 'MD5',
        'sitename' => $siteName,
    ];
}

function sponsor_gateway_summary(): array {
    $cfg = sponsor_gateway_config();
    return [
        'key' => $cfg['provider'],
        'label' => $cfg['provider_label'],
    ];
}

class SponsorPaymentGatewayCore
{
    private string $apiurl;
    private string $pid;
    private string $key;
    private string $sign_type;
    private string $sitename;
    private string $providerLabel;

    public function __construct(array $config)
    {
        $this->apiurl = rtrim((string)($config['apiurl'] ?? ''), '/');
        $this->pid = trim((string)($config['pid'] ?? ''));
        $this->key = trim((string)($config['key'] ?? ''));
        $this->sign_type = strtoupper(trim((string)($config['sign_type'] ?? 'MD5')));
        $this->sitename = trim((string)($config['sitename'] ?? '')) ?: trim((string)(sponsor_site_profile()['payment_site_name'] ?? sponsor_site_profile()['site_name'] ?? '小付music'));
        $this->providerLabel = trim((string)($config['provider_label'] ?? '支付网关')) ?: '支付网关';
        if ($this->sitename === '') $this->sitename = '小付music';
        if ($this->apiurl === '' || $this->pid === '' || $this->key === '') {
            throw new Exception($this->providerLabel . '配置不完整');
        }
    }

    public function submitEndpoint(): string
    {
        return $this->apiurl . '/submit.php';
    }

    public function buildPayPayload(array $params): array
    {
        $payload = array_merge([
            'pid' => $this->pid,
            'sign_type' => $this->sign_type,
        ], $params);
        if ($this->sitename !== '') {
            $payload['sitename'] = $this->sitename;
        }
        $payload['sign'] = $this->sign($payload);
        return $payload;
    }

    public function buildPayUrl(array $params): string
    {
        return $this->submitEndpoint() . '?' . http_build_query($this->buildPayPayload($params));
    }

    public function verify(array $params): bool
    {
        $sign = strtolower(trim((string)($params['sign'] ?? '')));
        if ($sign === '') {
            return false;
        }
        return hash_equals($sign, strtolower($this->sign($params)));
    }

    public function queryOrder(string $outTradeNo): array
    {
        $url = $this->apiurl . '/api.php?' . http_build_query([
            'act' => 'order',
            'pid' => $this->pid,
            'key' => $this->key,
            'out_trade_no' => $outTradeNo,
        ]);

        $context = stream_context_create([
            'http' => [
                'timeout' => 12,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\nUser-Agent: XiaofuMusic/1.0\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false || trim($body) == '') {
            throw new Exception('订单查询失败，请检查服务器是否可访问' . $this->providerLabel . '网关');
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new Exception('订单查询返回格式异常');
        }
        return $data;
    }

    public function sign(array $params): string
    {
        $signStr = $this->buildSignContent($params);
        return strtolower(md5($signStr . $this->key));
    }

    public function buildSignContent(array $params): string
    {
        ksort($params, SORT_STRING);
        $parts = [];
        foreach ($params as $key => $value) {
            if ($key === 'sign' || $key === 'sign_type') {
                continue;
            }
            if (is_array($value) || $value === null) {
                continue;
            }
            $text = (string)$value;
            if ($text === '') {
                continue;
            }
            $parts[] = $key . '=' . $text;
        }
        return implode('&', $parts);
    }
}

function sponsor_gateway(): SponsorPaymentGatewayCore
{
    static $instance = null;
    if ($instance instanceof SponsorPaymentGatewayCore) {
        return $instance;
    }
    $instance = new SponsorPaymentGatewayCore(sponsor_gateway_config());
    return $instance;
}

function sponsor_request_scheme(): string {
    $https = $_SERVER['HTTPS'] ?? '';
    $forwarded = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    if ($https === 'on' || $https === '1' || strtolower((string)$forwarded) === 'https') return 'https';
    return 'http';
}

function sponsor_base_url(): string {
    $scheme = sponsor_request_scheme();
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $scriptDir = rtrim($scriptDir, '/');
    return $scheme . '://' . $host . ($scriptDir !== '' ? $scriptDir : '');
}

function sponsor_absolute_url(string $script): string {
    return rtrim(sponsor_base_url(), '/') . '/' . ltrim($script, '/');
}
