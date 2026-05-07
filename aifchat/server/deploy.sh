#!/bin/bash
# ================================================================
# AIF Chat - 服务端一键部署脚本
# 在宝塔面板的「终端」中运行，或通过 SSH 执行
# ================================================================

set -e

echo "=== AIF Chat 服务端部署 ==="
echo ""

# 1. 检查环境
echo "🔍 检查环境..."
php -v | head -1
composer -V | head -1
echo ""

# 2. 进入项目目录
PROJECT_DIR="/www/wwwroot/你的网站目录"
if [ ! -d "$PROJECT_DIR" ]; then
    echo "❌ 项目目录 $PROJECT_DIR 不存在"
    echo "   请先在宝塔创建网站，然后 git clone 项目"
    exit 1
fi

cd "$PROJECT_DIR/server"

# 3. 安装依赖
echo "📦 安装 PHP 依赖..."
composer install --no-dev --no-interaction 2>/dev/null || composer install --no-interaction

# 4. 配置 .env
if [ ! -f ".env" ]; then
    echo "📝 创建 .env 配置文件..."
    cp .env.example .env
    echo "⚠️  请编辑 .env 填入数据库和 API 配置"
    echo "   文件位置: $PROJECT_DIR/server/.env"
fi

# 5. 初始化数据库
echo "🗄️  初始化数据库..."
if [ -f ".env" ]; then
    source .env 2>/dev/null || true
    if [ ! -z "$DB_USER" ]; then
        mysql -u"$DB_USER" -p"$DB_PASS" -h"${DB_HOST:-127.0.0.1}" < database.sql 2>/dev/null && echo "   ✅ 数据库初始化成功" || echo "   ⚠️  数据库初始化失败，请手动执行: mysql -u root -p < database.sql"
    fi
else
    echo "   ⚠️  请先配置 .env 文件"
fi

# 6. 设置权限
echo "🔒 设置目录权限..."
chmod -R 755 "$PROJECT_DIR/server"
chmod -R 777 "$PROJECT_DIR/server/cache" 2>/dev/null || mkdir -p "$PROJECT_DIR/server/cache"
chmod -R 777 "$PROJECT_DIR/server/logs" 2>/dev/null || mkdir -p "$PROJECT_DIR/server/logs"

# 7. Nginx 配置提醒
echo ""
echo "=== ✅ 部署完成 ==="
echo ""
echo "📋 接下来请做："
echo "1. 编辑 .env 文件："
echo "   nano $PROJECT_DIR/server/.env"
echo ""
echo "2. 在宝塔面板设置网站运行目录为："
echo "   /server"
echo ""
echo "3. 添加伪静态规则（Nginx）："
echo "   location / {"
echo "       try_files \$uri \$uri/ /app_api.php?\$query_string;"
echo "   }"
echo ""
echo "4. 修改 config.ts 指向你的域名："
echo "   EXPO_PUBLIC_API_BASE_URL=https://你的域名"
echo ""
