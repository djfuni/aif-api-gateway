# NewAPI 适配配置指南

本文档将你当前 AI API 网关项目的全部模型配置转换为 **NewAPI**（基于 songquanpeng/one-api 的衍生版）可用的格式，包含渠道配置、模型映射和定价方案。

---

## 目录

1. [项目当前模型总览](#1-项目当前模型总览)
2. [NewAPI 渠道配置](#2-newapi-渠道配置渠道)
3. [NewAPI 模型与定价](#3-newapi-模型与定价)
4. [操作步骤](#4-操作步骤)
5. [定价策略建议](#5-定价策略建议)
6. [常见问题](#6-常见问题)

---

## 1. 项目当前模型总览

你的网站目前对接了 **7 大上游供应商**，涵盖百款模型：

| 供应商 | 协议类型 | 模型数量 | 典型模型 |
|--------|---------|---------|---------|
| **月之暗面 Kimi** | OpenAI 兼容 | 3+（动态拉取） | kimi-k2.6, kimi-k2.5, kimi-k2 |
| **阿里云百炼（新加坡）** | OpenAI 兼容 | 60+ | qwen3.6-max, qwq-plus, qwen-turbo... |
| **GitHub Models** | GitHub REST API | 18+（动态拉取） | gpt-4.1, DeepSeek-R1, Llama-3.3 |
| **硅基流动 SiliconFlow** | OpenAI 兼容 | 50+（动态拉取） | DeepSeek-V4-Flash, Qwen3-Next, GLM-5 |
| **OpenRouter** | OpenAI 兼容 | 200+（动态免费拉取） | 社区聚合模型 |
| **小米 MiMo Token Plan** | OpenAI 兼容 | 4+ | mimo-v2.5-pro, mimo-v2.5 |
| **小米 MiMo Key 2** | OpenAI 兼容 | 同 MIMO | mimo-v2.5-pro-key2 |
| **内置免费模型** | 多种协议 | 8+ | Spark Lite, NVIDIA 系列, Z-Image-Turbo |

---

## 2. NewAPI 渠道配置

### 2.1 渠道-模型映射速查表

| NewAPI 渠道名称 | 渠道类型 | 模型映射 | 默认 Base URL |
|----------------|---------|---------|--------------|
| `moonshot` | 月之暗面 | kimi-k2.6, kimi-k2.5, kimi-k2 | `https://api.moonshot.cn/v1` |
| `bailian` | 阿里云百炼(SG) | qwen3.6-max, qwq-plus, qwen-* 等 | `https://dashscope-intl.aliyuncs.com/compatible-mode/v1` |
| `github` | GitHub Models | gpt-4.1, DeepSeek-R1 等 | `https://models.github.ai/inference` |
| `siliconflow` | 硅基流动 | DeepSeek-V4, Qwen3, GLM-5 等 | `https://api.siliconflow.cn/v1` |
| `openrouter` | OpenRouter | 社区聚合全线模型 | `https://openrouter.ai/api/v1` |
| `mimo` | 小米 MiMo | mimo-v2.5-pro, mimo-v2.5 | `https://token-plan-cn.xiaomimimo.com/v1` |
| `mimo2` | 小米 MiMo Key2 | mimo-v2.5-pro (备用密钥) | `https://token-plan-cn.xiaomimimo.com/v1` |
| `nvidia_free` | 自定义 OpenAI | 内置免费 NVIDIA 模型 | `https://integrate.api.nvidia.com/v1` |
| `xfyun_free` | 自定义 OpenAI | 内置免费讯飞模型 | `https://spark-api-open.xf-yun.com/v1` |
| `zhipu` | 智谱 ChatGLM | glm-4.7 (已禁用) | `https://open.bigmodel.cn/api/paas/v4` |

### 2.2 渠道配置 JSON

详见配套文件 **[newapi_channels.json](./newapi_channels.json)**。

每条渠道的格式示例：

```json
{
  "type": 1,
  "name": "moonshot",
  "base_url": "https://api.moonshot.cn/v1",
  "key": "sk-xxx",
  "models": "kimi-k2.6,kimi-k2.5,kimi-k2",
  "model_mapping": "{}",
  "rate_limit": 60,
  "priority": 1
}
```

**渠道 type 编号参考（NewAPI 内置类型）：**

| Type | 供应商 |
|------|--------|
| 1 | OpenAI |
| 3 | 自定义（OpenAI 兼容） |
| 4 | 阿里云百炼 |
| 7 | 月之暗面 |
| 14 | 智谱 ChatGLM |
| 24 | 自定义(多模态) |
| 25 | GitHub Models |

---

## 3. NewAPI 模型与定价

### 3.1 定价原则

根据你当前项目的 `api_token_multiplier` 规则，转换为 NewAPI 的 **每千 Token 单价（¥/1K tokens）**：

**定价转换公式：**
```
NewAPI 单价 = 基础单价 × multiplier
```

建议**基础单价**设定为：
- 普通对话模型：**¥0.005 / 1K tokens**（5厘/千Token）
- 推理/思考模型：**¥0.01 / 1K tokens**（1分/千Token）
- 免费模型：**¥0.00 / 1K tokens**（内部免费）

### 3.2 multiplier → 定价映射表

| multiplier | 含义 | 建议单价(¥/1K) | 适���模型 |
|-----------|------|---------------|---------|
| 0.0 | 免费 | 0 | Spark Lite, NVIDIA 系列, GLM-5 |
| 1.0 | 标准 | 0.005 | kimi-k2, mimo-v2.5, Qwen 小参数 |
| 1.2 | 稍高 | 0.006 | mimo-v2.5-pro |
| 2.0 | 两倍 | 0.01 | GLM, MiniMax, GPT-OSS, Seed-36B |
| 3.0 | 三倍 | 0.015 | 122B 参数模型 |
| 8.0 | 八倍 | 0.04 | 397B 参数模型 |

### 3.3 完整模型定价表

详见配套文件 **[newapi_pricing.json](./newapi_pricing.json)**。

部分示例：

```json
{
  "kimi-k2.6": {
    "label": "Kimi K2.6",
    "provider": "moonshot",
    "type": "chat",
    "multiplier": 1.0,
    "price_input": 0.005,
    "price_output": 0.005,
    "is_free": false,
    "thinking": true
  },
  "qwq-plus": {
    "label": "qwq-plus (百炼免费额度)",
    "provider": "bailian",
    "type": "chat",
    "multiplier": 1.0,
    "price_input": 0,
    "price_output": 0,
    "is_free": true,
    "thinking": true
  },
  "mimo-v2.5-pro": {
    "label": "MiMo v2.5 Pro",
    "provider": "mimo",
    "type": "chat",
    "multiplier": 1.2,
    "price_input": 0.006,
    "price_output": 0.006,
    "is_free": false,
    "thinking": true
  }
}
```

### 3.4 套餐定价参考（从你的站点迁移）

| 套餐名 | Token 量 | 价格(¥) | 单价(¥/1K Token) |
|-------|---------|---------|-----------------|
| 注册试用包 | 20K | 免费 | 0 |
| 轻量月卡 | 2M | 19 | 0.0095 |
| 进阶月卡 | 8M | 49 | 0.0061 |
| 专业月卡 | 25M | 99 | 0.0040 |
| 团队月卡 | 80M | 299 | 0.0037 |
| 1M 加量包 | 1M | 10 | 0.0100 |
| 6M 加量包 | 6M | 50 | 0.0083 |
| 20M 加量包 | 20M | 150 | 0.0075 |

---

## 4. 操作步骤

### 步骤 1：部署 NewAPI

```bash
# 使用 Docker 部署（推荐）
docker run --name new-api -d \
  -p 3000:3000 \
  -v /home/newapi/data:/data \
  -e SQL_DSN="newapi.db" \
  -e SESSION_SECRET="your-secret-key" \
  -e TZ=Asia/Shanghai \
  calciumion/new-api:latest
```

或使用宝塔面板：
1. 在宝塔应用商店搜索 "New-API"
2. 一键部署，设置域名和端口
3. 确保 PHP 和 MySQL 已安装

### 步骤 2：初始化登录

1. 访问 `http://你的IP:3000`
2. 默认账号密码：`root / 123456`
3. 进入 **管理面板 → 设置**，修改密码

### 步骤 3：配置渠道

**方法一：通过 Web 管理界面逐个添加**

进入 **渠道 → 添加渠道**，按以下表格逐个创建：

| 字段 | 填写内容 |
|------|---------|
| 名称 | 例如 `moonshot`、`bailian`、`github` |
| 类型 | 见上面 type 编号表 |
| 密钥 | 从 `data/ai_providers_private.php` 中对应字段复制 |
| 模型 | 填写该渠道需要映射的模型名，逗号分隔 |
| 模型映射 | 如需要重命名模型，使用 JSON 映射格式 |
| 代理 | 留空（直连）或设置代理 |
| 速率限制 | 建议 60 次/分钟 |

**方法二：通过数据库批量导入**

1. 停止 NewAPI 服务
2. 将配套的 `newapi_channels.json` 转换为 SQL INSERT 语句
3. 导入到 NewAPI 的数据库
4. 重启服务

### 步骤 4：配置定价

1. 进入 **管理面板 → 模型**
2. 点击每个模型，设置：
   - **输入价格**（¥/1K tokens）
   - **输出价格**（¥/1K tokens）
   - **计费倍数**
3. 或参考配套的 `newapi_pricing.json` 批量导入

### 步骤 5：配置用户/令牌

1. 进入 **令牌 → 添加令牌**
2. 设置令牌名称、额度、可用模型等
3. 生成 API Key，格式 `sk-xxx`
4. 用户端直接用 `https://你的newapi站点/v1` + 该 Key 调用

### 步骤 6：迁移你的套餐到 NewAPI

NewAPI 自带充值码系统，可参考你当前的套餐：
1. 在 **充值码 → 添加充值码** 中创建
2. 设置面额（对应你的 Token 包）
3. 用户可以在 **用户中心 → 充值** 兑换

---

## 5. 定价策略建议

### 5.1 推荐最终零售价

| 模型分组 | 建议输入价(¥/1K) | 建议输出价(¥/1K) | 说明 |
|---------|-----------------|-----------------|------|
| 免费模型 | 0 | 0 | Spark Lite, NVIDIA 系列 |
| 百炼免费额度 | 0 | 0 | 新加坡区免费额度模型 |
| 标准模型 | 0.005 | 0.005 | kimi-k2, mimo-v2.5 |
| 推理模型 | 0.008 | 0.012 | kimi-k2.6, mimo-v2.5-pro |
| 大型模型(122B) | 0.01 | 0.02 | qwen3.5-122B 类 |
| 超大型(235B+) | 0.02 | 0.04 | qwen3-235B |
| 旗舰(397B) | 0.03 | 0.05 | qwen3.5-397B |

### 5.2 利润率建议

- **免费模型（百炼/NVIDIA）**：成本为 0，可用于引流
- **GitHub Models**：免费 Tier 模型低成本，建议定价 ¥0.003
- **月之暗面 Kimi**：按量计费，建议加价 20-30%
- **MiMo**：按量计费，建议加价 15-20%
- **硅基流动**：按量计费，建议加价 30-50%
- **OpenRouter**：动态定价，建议加价 20%

---

## 6. 常见问题

### Q1：NewAPI 和当前项目能共存吗？

**可以。** 部署 NewAPI 后，你的旧网关和新网关可以同时运行。逐步将用户迁移到 NewAPI 即可。

### Q2：动态目录（live_catalog）的模型怎么处理？

对于 `live_catalog: true`（如 GitHub、硅基流动、月之暗面）的供应商，它们会定期更新模型列表。需要在 NewAPI 中开启 **定时同步** 或手动添加上新模型。

### Q3：超长上下文模型怎么配置？

在 NewAPI 的模型配置中，设置对应模型的 **最大上下文** 参数，例如 Kimi K2.5 可设为 256K。

### Q4：流式输出支持吗？

NewAPI 原生支持流式输出（stream=true），所有 OpenAI 兼容渠道自动支持。

### Q5：怎么把当前用户的 API Key 迁移？

方案一：在 NewAPI 中为每个现有用户创建新令牌，通过旧站发送通知。
方案二：修改旧站的 `ai_api_gateway_lib.php` 中的代理目标地址，指向 NewAPI 的出站端。

---

## 附录：关键数据引用

### 供应商 API Key 来源

当前你的 API Key 存在 `data/ai_providers_private.php` 中：

| 供应商 | 环境变量 | 默认 Key（已脱敏） |
|--------|---------|------------------|
| moonshot | `MOONSHOT_API_KEY` / `KIMI_API_KEY` | sk-AujY... |
| bailian | `DASHSCOPE_API_KEY` | sk-b73... |
| github | `GITHUB_MODELS_TOKEN` | github_pat_... |
| siliconflow | `SILICONFLOW_API_KEY` | sk-zpw... |
| openrouter | `OPENROUTER_API_KEY` | sk-or-v1... |
| mimo | `MIMO_API_KEY` | tp-ceg... |
| mimo2 | `MIMO2_API_KEY` | tp-cwc... |

### 内置免费模型 API Key

在 `config/spark_lite.php` 中，部分免费模型未配置 API Key（通过空 Key 访问）：

| 模型 | 端点 | 备�� |
|------|------|------|
| Spark Lite | `https://spark-api-open.xf-yun.com/v1` | 公开可用 |
| NVIDIA 系列 | `https://integrate.api.nvidia.com/v1` | 公开可用 |
| Z-Image-Turbo | `https://maas-api.cn-huabei-1.xf-yun.com` | 需配置 app_id/api_key/secret |
| Zhipu GLM-4.7 | `https://open.bigmodel.cn/api/paas/v4` | 已禁用(enabled=false) |
