# AIF Chat 服务端部署指南

## 环境要求

- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.3+
- Composer
- cURL PHP 扩展
- PDO MySQL 扩展
- OpenSSL PHP 扩展

## 快速部署

### 1. 下载源码

```bash
git clone https://github.com/你的用户名/aifchat-mobile.git
cd aifchat-mobile/server
```

### 2. 安装依赖

```bash
composer install --no-dev
```

### 3. 配置环境

```bash
cp .env.example .env
# 编辑 .env 文件，填入你的数据库和 API 配置
```

### 4. 初始化数据库

```bash
mysql -u root -p < database.sql
```

### 5. 配置 Web 服务器

#### Nginx 示例
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/aifchat-mobile/server;
    index app_api.php;

    location / {
        try_files $uri $uri/ /app_api.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.env {
        deny all;
    }
}
```

#### Apache 示例
确保启用 mod_rewrite 和 mod_headers：
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /path/to/aifchat-mobile/server
    <Directory /path/to/aifchat-mobile/server>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### 6. 移动端配置

编辑 `app.json` 中的 `extra.apiBaseUrl` 或直接设置环境变量：
```bash
EXPO_PUBLIC_API_BASE_URL=https://your-domain.com
```

## API 接口文档

所有请求通过 `app_api.php?action={action}` 访问。
默认请求方式为 POST（JSON body）。

### 公共接口

| 动作 | 说明 | 认证 |
|------|------|------|
| `captcha` | 获取图形验证码 | 否 |
| `send_email_code` | 发送邮箱验证码 | 否 |
| `register` | 用户注册 | 否 |
| `login` | 用户登录 | 否 |

### 认证接口

| 动作 | 说明 | 认证 |
|------|------|------|
| `refresh` | 刷新 Access Token | 否（需 refresh_token） |
| `logout` | 退出登录 | 是 |
| `me` | 获取用户信息 | 是 |
| `models` | 获取模型列表 | 是 |
| `chat` | AI 聊天 | 是 |

### 响应格式

```json
{
    "ok": true,
    "msg": "操作成功",
    "data": { ... },
    "user": { ... },
    "models": [...],
    "message": "..."
}
```

## 注意事项

1. **JWT 密钥**: 务必修改 `.env` 中的 `JWT_SECRET` 为随机字符串
2. **HTTPS**: 生产环境务必配置 HTTPS
3. **Token 安全**: Refresh Token 自动过期，Access Token 短期有效
4. **AI API 密钥**: 配置 `AI_API_KEY` 为你的 OpenAI 兼容 API Key
5. **邮件服务**: 配置 SMTP 用于发送邮箱验证码，不配置则使用 PHP 内置 mail()

## 目录结构

```
server/
├── app_api.php          # API 主入口
├── config.php           # 配置加载
├── database.sql         # 数据库初始化
├── composer.json        # PHP 依赖
├── .env.example         # 环境配置模板
├── .htaccess            # Apache 重写规则
├── includes/
│   ├── auth.php         # JWT 认证
│   ├── captcha.php      # 图形验证码
│   └── ai.php           # AI API 代理
└── vendor/              # Composer 依赖
```
