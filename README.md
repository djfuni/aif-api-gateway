# AIF API Gateway

> 多供应商 AI API 代理网关 | 聚合管理 | Token 计费系统

AIF API Gateway 是一个基于 PHP 构建的 AI API 代理网关，聚合了多家 AI 供应商的模型接口，提供统一的 OpenAI 兼容 API、用户管理、Token 计费、套餐系统和可视化管理后台。

---

## 目录

- [功能概览](#功能概览)
- [环境要求](#环境要求)
- [快速部署](#快速部署)
  - [方式一：宝塔面板部署（推荐）](#方式一宝塔面板部署推荐)
  - [方式二：手动 Nginx 部署](#方式二手动-nginx-部署)
  - [方式三：Docker 部署](#方式三docker-部署)
- [配置指南](#配置指南)
  - [1. 基础配置](#1-基础配置)
  - [2. 管理员账号](#2-管理员账号)
  - [3. 配置 AI 供应商密钥](#3-配置-ai-供应商密钥)
  - [4. 支付配置（可选）](#4-支付配置可选)
  - [5. SMTP 邮件配置（可选）](#5-smtp-邮件配置可选)
- [Nginx 配置示例](#nginx-配置示例)
- [管理后台使用](#管理后台使用)
- [AIF Chat 聊天服务部署](#aif-chat-聊天服务部署)
- [API 接口说明](#api-接口说明)
- [安全须知](#安全须知)
- [常见问题](#常见问题)

---

## 功能概览

| 功能 | 说明 |
|------|------|
| **多供应商聚合** | 支持 Kimi、阿里百炼、GitHub Models、硅基流动、OpenRouter、小米 MiMo 等 |
| **统一 API** | 提供 OpenAI 兼容的 `/v1/chat/completions` 接口 |
| **Token 计费** | 基于 Token 用量的精细化计费系统 |
| **用户管理** | 注册、登录、API Key 管理、使用量查看 |
| **套餐系统** | 支持月卡、加量包等多种套餐 |
| **充值码** | 用于兑换额度或套餐 |
| **开发者应用** | 开发者可申请 API 应用接入 |
| **管理后台** | Web 可视化后台管理所有功能 |
| **PWA 支持** | 支持作为 PWA 安装到桌面 |
| **支付集成** | 可对接支付宝/微信支付 |

### 已集成供应商

| 供应商 | 协议 | 模型数量 |
|--------|------|---------|
| 月之暗面 Kimi | OpenAI 兼容 | 3+ |
| 阿里云百炼（新加坡） | OpenAI 兼容 | 60+ |
| GitHub Models | GitHub REST API | 18+ |
| 硅基流动 SiliconFlow | OpenAI 兼容 | 50+ |
| OpenRouter | OpenAI 兼容 | 200+ |
| 小米 MiMo | OpenAI 兼容 | 4+ |
| 内置免费模型 | 多种 | 8+（Spark Lite、NVIDIA 等） |

---

## 环境要求

| 项目 | 最低要求 | 推荐 |
|------|---------|------|
| Web 服务器 | Nginx / Apache | Nginx |
| PHP 版本 | 7.4 | 8.1+ |
| PHP 扩展 | curl, json, mbstring, openssl, fileinfo | 同上 + opcache, redis |
| 存储空间 | 100MB | 1GB+ |
| 内存 | 256MB | 512MB+ |
| 域名 | 可选 | 建议绑定域名+SSL |

### PHP 扩展检查清单

部署前请确保以下 PHP 扩展已安装启用：

```bash
# 宝塔面板：PHP设置 → 安装扩展
curl, json, mbstring, openssl, fileinfo, pdo, pdo_mysql, gd, exif
```

---

## 快速部署

### 方式一：宝塔面板部署（推荐）

#### 步骤 1：新建站点

1. 登录宝塔面板 → **网站** → **添加站点**
2. 填写：
   - **域名**：你的域名（或使用 IP:端口）
   - **PHP 版本**：选择 PHP 8.0+
   - **创建数据库**：不勾选（本系统使用 JSON 文件存储，无需 MySQL）
3. 点击「提交」

#### 步骤 2：上传代码

1. 将本项目所有文件上传到站点根目录（宝塔中一般为 `/www/wwwroot/你的域名/`）
2. 或者使用 Git 克隆：
   ```bash
   cd /www/wwwroot/你的域名/
   git clone https://github.com/djfuni/aif-api-gateway.git .
   ```

#### 步骤 3：设置目录权限

```bash
# 在宝塔面板的「文件」中，右键站点目录 → 权限设置
# 或执行命令：
chmod -R 755 /www/wwwroot/你的域名/
chmod -R 777 /www/wwwroot/你的域名/data/
chmod -R 777 /www/wwwroot/你的域名/aifchat/server/cache/
```

> ⚠️ **关键**：`data/` 目录必须可写（777），系统会在该目录下生成运行时数据文件。

#### 步骤 4：配置伪静态

在宝塔站点设置中 → **伪静态** → 选择 **`laravel5`** 或自定义：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location /v1/ {
    try_files $uri $uri/ /v1/index.php?$query_string;
}

# 禁止访问敏感目录
location ^~ /data/ {
    deny all;
}
location ^~ /config/ {
    deny all;
}
```

#### 步骤 5：访问安装

1. 访问 `http://你的域名/` 查看前端首页
2. 访问 `http://你的域名/admin/setup.php` 执行初始化安装
3. 系统会自动创建初始管理员账号和管理员数据文件

---

### 方式二：手动 Nginx 部署

#### 1. 安装 LNMP 环境

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install nginx php8.1 php8.1-fpm php8.1-curl php8.1-json php8.1-mbstring php8.1-openssl php8.1-xml php8.1-gd

# CentOS/RHEL
sudo yum install epel-release
sudo yum install nginx php php-fpm php-curl php-json php-mbstring php-openssl php-xml php-gd
```

#### 2. 部署代码

```bash
# 创建站点目录
sudo mkdir -p /var/www/aif-gateway
cd /var/www/aif-gateway

# 克隆代码
sudo git clone https://github.com/djfuni/aif-api-gateway.git .

# 设置权限
sudo chown -R www-data:www-data /var/www/aif-gateway
sudo chmod -R 755 /var/www/aif-gateway
sudo chmod -R 777 /var/www/aif-gateway/data
```

#### 3. 配置 Nginx

参见下方 [Nginx 配置示例](#nginx-配置示例) 部分。

---

### 方式三：Docker 部署

> 需要 Docker 和 docker-compose 环境

创建 `docker-compose.yml`：

```yaml
version: '3.8'

services:
  gateway:
    image: php:8.1-fpm
    container_name: aif-gateway
    volumes:
      - ./:/var/www/html
    working_dir: /var/www/html
    ports:
      - "9000:9000"
    command: |
      bash -c "
      docker-php-ext-install curl json mbstring openssl pdo pdo_mysql &&
      php-fpm
      "

  nginx:
    image: nginx:alpine
    container_name: aif-nginx
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./:/var/www/html
      - ./nginx.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - gateway
```

然后执行：

```bash
docker-compose up -d
```

---

## 配置指南

### 1. 基础配置

复制并修改以下配置文件：

```bash
# 管理员登录配置（可选，系统会自动生成）
cp config/admin_auth.example.php config/admin_auth.local.php
```

编辑 `config/admin_auth.local.php` 设置管理员密码哈希（可使用 `tools/generate_admin_hash.php` 生成）：

```bash
# 生成密码哈希
php tools/generate_admin_hash.php
```

### 2. 管理员账号

首次访问 `http://你的域名/admin/` 会自动跳转到初始安装页面，按引导完成设置。

**默认信息**：
- 管理员账号：安装时自行设置
- 登录地址：`http://你的域名/admin/`

### 3. 配置 AI 供应商密钥

系统使用 `data/ai_providers_private.php` 存储各家 AI 供应商的 API Key。首次运行时系统会自动创建该文件。

你需要将各家供应商的 API Key 填入该文件：

```php
<?php
return [
    'MOONSHOT_API_KEY' => 'sk-xxx',          // 月之暗面
    'DASHSCOPE_API_KEY' => 'sk-xxx',          // 阿里云百炼
    'GITHUB_MODELS_TOKEN' => 'github_pat_xxx', // GitHub Models
    'SILICONFLOW_API_KEY' => 'sk-xxx',        // 硅基流动
    'OPENROUTER_API_KEY' => 'sk-or-v1-xxx',   // OpenRouter
    'MIMO_API_KEY' => 'tp-xxx',               // 小米 MiMo
    'MIMO2_API_KEY' => 'tp-xxx',              // 小米 MiMo（备用密钥）
];
```

也可以通过管理后台 → **设置** 页面进行配置。

### 4. 支付配置（可选）

复制并编辑支付配置：

```bash
cp config/payment_config.example.php config/payment_config.php
```

编辑 `config/payment_config.php` 配置支付宝/微信支付的商户信息。

### 5. SMTP 邮件配置（可选）

编辑根目录的 `smtp_config.php`，配置邮件发送功能（用于用户注册验证、通知等）。

---

## Nginx 配置示例

完整的 Nginx 站点配置参考：

```nginx
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;

    # SSL 证书配置
    ssl_certificate /path/to/ssl/fullchain.pem;
    ssl_certificate_key /path/to/ssl/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers on;

    root /var/www/aif-gateway;
    index index.html index.php;

    # PHP 解析
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    # API 路由
    location /v1/ {
        try_files $uri $uri/ /v1/index.php?$query_string;
    }

    # 前端路由
    location / {
        try_files $uri $uri/ /index.html;
    }

    # 安全：禁止访问敏感目录
    location ~ ^/(data|config|\.git|\.workbuddy)/ {
        deny all;
        return 404;
    }

    # 安全：禁止访问敏感文件
    location ~ \.(key|log|bak|sql)$ {
        deny all;
        return 404;
    }

    # 静态资源缓存
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|webp|woff|woff2)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # PWA Service Worker 不缓存
    location = /sw.js {
        add_header Cache-Control "no-cache";
        expires -1;
    }

    # 请求体大小限制（允许大上下文模型）
    client_max_body_size 20M;

    # 日志
    access_log /var/log/nginx/aif-gateway.access.log;
    error_log /var/log/nginx/aif-gateway.error.log;
}
```

Apache 用户可参考 `apache-app-api.htaccess.example` 文件配置 `.htaccess` 重写规则。

---

## 管理后台使用

### 访问地址

```
http://你的域名/admin/
```

### 功能模块

| 模块 | 路径 | 功能说明 |
|------|------|---------|
| 仪表盘 | `admin/index.php` | 系统概览、统计数据 |
| 用户管理 | `admin/users.php` | 查看/编辑/禁用用户 |
| API 密钥 | `admin/api-keys.php` | 管理用户 API Key |
| 模型管理 | `admin/models.php` | 配置可用模型及定价 |
| 套餐管理 | `admin/packages.php` | 创建/编辑套餐（月卡、加量包） |
| 充值码 | `admin/redeem-codes.php` | 生成充值码 |
| 订单管理 | `admin/orders.php` | 查看支付订单 |
| 应用管理 | `admin/developer-applications.php` | 审核开发者应用 |
| 系统设置 | `admin/settings.php` | 全局配置、API Key 设置 |
| 操作日志 | `admin/logs.php` | 查看系统操作记录 |
| 服务器状态 | `admin/status.php` | 服务器资源监控 |

---

## AIF Chat 聊天服务部署

项目附带 AIF Chat 聊天前端服务（位于 `aifchat/server/` 目录），提供完整的 AI 对话界面。

### 部署步骤

```bash
cd aifchat/server/

# 安装 PHP 依赖（需要 Composer）
composer install --no-dev

# 复制环境配置
cp .env.example .env

# 编辑 .env 配置文件
nano .env
```

### 环境变量说明

| 变量 | 说明 | 示例 |
|------|------|------|
| `API_GATEWAY_URL` | AIF API 网关地址 | `https://your-domain.com` |
| `API_GATEWAY_KEY` | 网关 API Key | `sk-xxx` |
| `DB_TYPE` | 数据库类型 | `sqlite` 或 `mysql` |

详细部署说明见 `aifchat/server/README.md`。

---

## API 接口说明

本项目兼容 OpenAI API 格式，所有标准 OpenAI 客户端均可直接使用。

### 基础用法

```bash
# Chat Completions
curl https://your-domain.com/v1/chat/completions \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer sk-your-api-key" \
  -d '{
    "model": "qwq-plus",
    "messages": [{"role": "user", "content": "Hello!"}],
    "stream": true
  }'

# 列出可用模型
curl https://your-domain.com/v1/models \
  -H "Authorization: Bearer sk-your-api-key"

# 语音合成
curl https://your-domain.com/v1/audio/speech \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer sk-your-api-key" \
  -d '{
    "model": "tts-1",
    "input": "你好，世界",
    "voice": "alloy"
  }'
```

### 前端配置示例

**ChatGPT Next Web (LobeChat / NextChat)**：
```
API 地址：https://your-domain.com
API Key：sk-xxx
```

**OpenCat / ChatBox**：
```
API 地址：https://your-domain.com/v1
API Key：sk-xxx
```

---

## 安全管理

> ⚠️ 部署到生产环境前，请务必执行以下安全措施

### 1. 配置 HTTPS

- 使用 Let's Encrypt 免费 SSL 证书（宝塔面板一键申请）
- 必须开启 HTTPS，避免 API Key 传输泄露

### 2. 目录权限

```bash
# 生产环境最小权限
chmod 644 /www/wwwroot/你的域名/*.php
chmod 644 /www/wwwroot/你的域名/*.html
chmod 755 /www/wwwroot/你的域名/admin/
chmod 755 /www/wwwroot/你的域名/assets/
chmod 755 /www/wwwroot/你的域名/config/
chmod 700 /www/wwwroot/你的域名/data/     # 仅所有者可读写
chmod 644 /www/wwwroot/你的域名/data/.htaccess
chmod 644 /www/wwwroot/你的域名/.user.ini
```

### 3. 敏感文件保护

`.user.ini`（已存在项目根目录）已配置禁止外部访问 PHP 源码：

```ini
; 禁止直接访问 PHP 文件
; 仅允许 index.php 和 admin/index.php 等入口文件被访问
```

### 4. Nginx 安全配置

参考 `nginx-admin-security.example.conf` 和 `nginx-app-api.example.conf` 进行加固。

### 5. 定期备份

```bash
# 备份运行时数据（JSON 文件）
tar czf backup-$(date +%Y%m%d).tar.gz data/*.json data/*.key
```

建议使用 cron 定时备份 `data/` 目录下的所有 JSON 文件。

---

## 常见问题

### Q1：访问页面显示 404

检查 Nginx 伪静态配置是否正确，确认 `try_files` 规则已配置。

### Q2：提示 data 目录不可写

```bash
chmod -R 777 /www/wwwroot/你的域名/data/
```
并确认所有者是 PHP-FPM 运行用户（通常是 `www-data` 或 `www`）。

### Q3：无法登录管理后台

1. 删除 `config/admin_auth.local.php` 或 `data/admin_users.json` 后重新访问 `/admin/setup.php`
2. 检查 PHP session 配置是否正常

### Q4：API 请求返回 500

- 检查 PHP 错误日志：`tail -f /var/log/php_errors.log`
- 确认 `data/ai_providers_private.php` 中的 API Key 是否正确
- 确认 PHP 扩展是否全部安装（curl, json, mbstring 等）

### Q5：模型列表为空

1. 确认 `data/ai_providers_private.php` 中至少配置了一个供应商的 API Key
2. 系统会自动从各供应商拉取可用模型列表
3. 也可以手动在管理后台 → 模型管理 中添加

### Q6：使用宝塔面板部署后无法访问

- 检查站点配置中 PHP 版本是否选择正确
- 尝试「设置」→ 「伪静态」中选择 `laravel5`
- 确认站点目录权限正确

### Q7：如何迁移到 NewAPI？

参考项目附带的 `newapi_config_guide.md` 文档，其中包含了完整的 NewAPI 适配配置指南和渠道转换方案。

---

## 技术栈

- **后端**：PHP 7.4+（纯原生，无框架）
- **数据存储**：JSON 文件系统（无需数据库）
- **前端**：原生 HTML/CSS/JS + PWA
- **API 协议**：OpenAI API 兼容

## 许可证

本项目仅限个人和学习使用，未经授权不得用于商业用途。

---

> **GitHub 仓库**：[https://github.com/djfuni/aif-api-gateway](https://github.com/djfuni/aif-api-gateway)
>
> 如有问题，欢迎提交 [Issue](https://github.com/djfuni/aif-api-gateway/issues)
