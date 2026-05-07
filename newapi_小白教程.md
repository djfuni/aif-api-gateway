# 🐣 小白也能看懂的 NewAPI 部署教程

> 你不需要懂代码！跟着每一步点鼠标就行！👆

---

## 📌 这教程能帮你干嘛？

把你现在这个 **AI API 网关网站** 里的所有 AI 模型，搬家到 **NewAPI** 上管理。

**搬完以后你就能：**
- ✅ 给你的朋友/客户开 API Key（像 OpenAI 那样）
- ✅ 给不同模型设置不同价格
- ✅ 在线查看谁用了多少 Token
- ✅ 生成兑换码卖套餐

---

## 🧭 快速导航 — 一共 5 步

| 步骤 | 内容 | 难度 |
|:----:|------|:----:|
| **①** | 装个 NewAPI | ⭐ |
| **②** | 登录后台 | ⭐ |
| **③** | 添加渠道（接上 AI 供应商） | ⭐⭐ |
| **④** | 设置价格 | ⭐⭐⭐ |
| **⑤** | 给你的朋友开 API Key | ⭐ |

---

# 📦 第①步：装 NewAPI

## 方法一：用宝塔面板装（推荐 ✅）

如果你在用宝塔面板，这是最简单的：

```
1. 登录宝塔面板
2. 点左边「软件商店」
3. 在搜索框输入「New-API」回车
4. 找到「New-API」点「一键部署」
5. 设置一个域名（比如 api.你的网站.com）
6. 端口写 3000
7. 点「提交」

搞定！
```

装好后访问：`http://你的服务器IP:3000` 或 `http://你设置的域名:3000`

---

## 方法二：用命令行装（Linux 服务器）

打开你的服务器终端（SSH），复制粘贴下面这一整段回车：

```bash
docker run --name new-api -d \
  -p 3000:3000 \
  -v /home/newapi/data:/data \
  -e TZ=Asia/Shanghai \
  calciumion/new-api:latest
```

装好后访问：`http://你的服务器IP:3000`

> ❓ 如果你的服务器没装 Docker，先运行：
> ```bash
> curl -fsSL https://get.docker.com | bash
> ```

---

# 🔑 第②步：登录后台

打开 NewAPI 页面后：

```
1. 默认账号：root
2. 默认密码：123456
3. 点「登录」
4. ⚠️ 进去后立刻点右上角头像 → 修改密码！
```

---

# 🔌 第③步：添加 AI 渠道（最关键！）

**什么是「渠道」？**
> 渠道 = 你当前网站连的那些 AI 供应商。
> 比如 Kimi 是一个渠道，硅基流动是另一个。

下面把所有渠道一个个加进去。

---

## 3.1 加第一个渠道：月之暗面 Kimi

```
1. 点左边菜单「渠道」→「添加渠道」
2. 名称填写：月之暗面 Kimi
3. 类型选择：月之暗面（Moonshot）
4. 密钥填写：【去你的旧网站找】
   打开网站根目录下的 data/ai_providers_private.php 文件
   找到 'moonshot' 那一行，把 sk-xxx... 那串复制过来
5. 模型填写：kimi-k2.6,kimi-k2.5,kimi-k2
6. 其他不用改，点「提交」
```

✅ 搞定！Kimi 接上了！

---

## 3.2 加第二个渠道：阿里云百炼

```
1. 「渠道」→「添加渠道」
2. 名称：阿里云百炼
3. 类型选择：阿里云百炼
4. 密钥：【去 data/ai_providers_private.php 找 'bailian' 的 key】
5. 请求地址写：https://dashscope-intl.aliyuncs.com/compatible-mode/v1
6. 模型填写：（复制下面一整行）

qwen3.6-max-preview,qwen3-max,qwen3.6-plus,qwen3.5-plus,qwen-plus,qwen3.6-flash,qwen3.5-flash,qwen-flash,qwen-turbo,qwq-plus,qwen3.5-397b-a17b,qwen3.5-122b-a10b,qwen3-coder-plus,qwen-coder-plus

7. 点「提交」
```

---

## 3.3 加第三个渠道：硅基流动

```
1. 「渠道」→「添加渠道」
2. 名称：硅基流动
3. 类型选择：OpenAI
4. 密钥：【去 data/ai_providers_private.php 找 'siliconflow' 的 key】
5. 请求地址写：https://api.siliconflow.cn/v1
6. 模型填写：（复制下面一整行）

DeepSeek-V4-Flash,DeepSeek-R1,DeepSeek-V3.2,Qwen3-Next-80B-A3B-Thinking,Qwen3-235B-A22B,Qwen3-32B,Qwen3-14B,Qwen3-8B,QwQ-32B,MiniMax-M2.5,GLM-5.1,GLM-4.7,Seed-OSS-36B-Instruct,Kimi-K2-Instruct,Kimi-K2-Thinking

7. 点「提交」
```

---

## 3.4 加第四个渠道：小米 MiMo

```
1. 「渠道」→「添加渠道」
2. 名称：小米 MiMo
3. 类型选择：OpenAI
4. 密钥：【去 data/ai_providers_private.php 找 'mimo' 的 key】
5. 请求地址写：https://token-plan-cn.xiaomimimo.com/v1
6. 模型填写：mimo-v2.5-pro,mimo-v2.5
7. 点「提交」
```

---

## 3.5 加第五个渠道：GitHub Models

```
1. 「渠道」→「添加渠道」
2. 名称：GitHub Models
3. 类型选择：OpenAI
4. 密钥：【去 data/ai_providers_private.php 找 'github' 的 key】
5. 请求地址写：https://models.github.ai/inference
6. 模型填写：gpt-4.1,gpt-4.1-mini,gpt-4.1-nano,gpt-4o,gpt-4o-mini,o3-mini,DeepSeek-R1,DeepSeek-V3-0324
7. 点「提交」
```

---

## 3.6 加第六个渠道：OpenRouter（可选）

```
1. 「渠道」→「添加渠道」
2. 名称：OpenRouter
3. 类型选择：OpenAI
4. 密钥：【去 data/ai_providers_private.php 找 'openrouter' 的 key】
5. 请求地址写：https://openrouter.ai/api/v1
6. 模型填写：openrouter/free,openrouter/auto
7. 额外请求头填写：
   HTTP-Referer: https://你的网站.com
   X-OpenRouter-Title: AI API Gateway
8. 点「提交」
```

---

## 3.7 加免费渠道：NVIDIA 免费

```
1. 「渠道」→「添加渠道」
2. 名称：NVIDIA 免费模型
3. 类型选择：OpenAI
4. 密钥：留空不填
5. 请求地址写：https://integrate.api.nvidia.com/v1
6. 模型填写：gpt-oss-20b,seed-oss-36b
7. 点「提交」
```

---

# 💰 第④步：设置价格

### 4.1 进到定价页面

```
左边菜单 → 点击「模型」
你会看到所有刚才添加的模型列表
```

### 4.2 逐个设置价格

点击每个模型右边的「编辑」按钮，填写：

| 模型 | 输入价格（¥/1K tokens） | 输出价格 |
|:----|:---------------------:|:--------:|
| **kimi-k2.6** | 0.005 | 0.005 |
| **kimi-k2.5** | 0.005 | 0.005 |
| **kimi-k2** | 0.005 | 0.005 |
| **DeepSeek-R1** | 0.008 | 0.016 |
| **DeepSeek-V4-Flash** | 0.002 | 0.002 |
| **QwQ-32B** | 0.005 | 0.008 |
| **mimo-v2.5-pro** | 0.006 | 0.006 |
| **mimo-v2.5** | 0.005 | 0.005 |
| **gpt-4o** | 0.003 | 0.003 |
| **gpt-4.1** | 0.002 | 0.002 |
| **NVIDIA 免费系列** | 0 | 0 |
| **百炼免费额度系列** | 0 | 0 |

> 💡 **偷懒技巧：**
> 不太重要的模型统一设 0.005/0.005 就行。
> 价格先设低一点，以后可以随时改。

### 4.3 添加充值套餐

```
1. 左边菜单 → 点击「充值码」
2. 点「添加充值码」
3. 参考下面的套餐来设置：
```

| 套餐名 | 金额(元) | Token 数量 |
|:-----|:-------:|:----------:|
| 试用包 | 0（免费） | 20,000 |
| 轻量月卡 | 19 | 2,000,000 |
| 进阶月卡 | 49 | 8,000,000 |
| 专业月卡 | 99 | 25,000,000 |
| 1M 加量包 | 10 | 1,000,000 |
| 6M 加量包 | 50 | 6,000,000 |

---

# 🎫 第⑤步：给你的朋友开 API Key

### 5.1 创建令牌

```
1. 左边菜单 → 点击「令牌」
2. 点「添加令牌」
3. 名称：写你朋友的名字 或 用途
4. 额度：填你想给他多少 Token（比如 100000）
5. 可用模型：选「全部」或勾选特定模型
6. 点「提交」
```

系统会生成一个 Key，格式像这样：
```
sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

把这个 Key 复制发给你朋友就行了！

### 5.2 你的朋友怎么用？

发给朋友的使用方式：

```
请求地址：https://你的域名/v1
API Key：sk-xxxx...（刚才生成的）
模型名：kimi-k2.6（随便选一个）

用 ChatBox、LobeChat、OpenCat 等软件，
设置里填上面三个信息就能用了！
```

---

# 🎯 总结

**你一共做了这些事：**

```
✅ 部署了 NewAPI
✅ 添加了 6~7 个 AI 渠道（联通了所有模型）
✅ 设置了每个模型的价格
✅ 可以给朋友开 API Key 了
```

**总共耗时：大约 20~30 分钟**

---

# ❓ 常见问题

**Q：我加错了渠道怎么办？**
A：在渠道列表点「删除」重新加就行，不影响其他。

**Q：价格设低了会亏钱吗？**
A：可以先设高一点，以后随时可以改低。

**Q：原来的网站还能用吗？**
A：可以！新老网站可以同时运行，互不影响。

**Q：我想导入更多模型怎么办？**
A：在渠道设置里，模型那一栏继续加逗号写新模型名就行。

**Q：报错了怎么办？**
A：一般是 API Key 填错了，去 `data/ai_providers_private.php` 里重新复制一次试试。

---

> 💬 **还是看不懂？直接问我就行！告诉我在哪一步卡住了 😊**
