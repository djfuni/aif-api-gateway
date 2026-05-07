# NewAPI M3 remake notes

本次重做把前台、控制台、兑换页、账户页和后台统一为 NewAPI 式信息架构，并锁定 Google Material You / M3 视觉主题。

## UI changes
- 左侧导航按 NewAPI 管理台方式重组：首页、令牌管理、渠道管理、模型测试、兑换码、用户中心、后台控制台。
- 首页新增渠道状态表、MiMo 双密钥状态、令牌体系与模型渠道概览。
- 控制台保留模型列表、接入参数、调用示例、在线调试、用量、套餐、令牌与兑换记录。
- 主题系统移除多主题切换，只保留 Google M3。

## MiMo keys
- `mimo::mimo-v2.5-pro` 使用 MiMo Key 1。
- `mimo2::mimo-v2.5-pro` / `mimo-v2.5-pro-key2` 使用 MiMo Key 2。
- 两套凭据写入本地配置，并仍可被环境变量覆盖。
