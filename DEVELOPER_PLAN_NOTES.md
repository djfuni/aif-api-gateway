# 开发者激励计划新增说明

本次新增一个类似 Xiaomi MiMo 100T 活动页的「开发者激励计划」闭环功能。

## 新增前台页面

- `developer-plan.html`
- `assets/developer_plan.css`
- `assets/developer_plan.js`

前台能力：

- 展示发放进行中、100T 权益池、已发放 Token、待审核数量。
- 展示申请流程和 FAQ。
- 登录用户可提交开发者申请。
- 表单字段包括联系邮箱、申请身份、项目名称、项目阶段、项目链接、证明材料、使用工具/模型、项目介绍、Token 使用计划、期望套餐。
- 同一账号同时只能存在一份待审核申请。
- 页面会展示当前用户自己的申请记录、审核状态、已发放套餐和审核备注。

## 新增后台页面

- `admin/developer-applications.php`

后台能力：

- 查看申请总数、待审核数、通过数、累计发放 Token。
- 按全部 / 待审核 / 已通过 / 未通过筛选。
- 查看申请人、项目材料、使用计划和期望套餐。
- 管理员通过申请时必须选择一个现有 Token 套餐。
- 通过后系统调用钱包发放逻辑，写入 `package_grant` 流水并立即增加用户 Token 余额。
- 拒绝时记录审核备注，用户可重新提交。

## 新增接口与存储

- `ai_api_console_api.php?action=developer_plan_overview`
- `ai_api_console_api.php?action=submit_developer_application`
- `ai_api_console_api.php?action=admin_review_developer_application`
- `data/ai_api_developer_applications.json`

核心业务函数位于 `ai_api_gateway_lib.php`：

- `ai_api_submit_developer_application()`
- `ai_api_review_developer_application()`
- `ai_api_user_developer_applications()`
- `ai_api_developer_application_stats()`

## 入口改动

- 首页侧边栏新增「开发者激励」入口。
- 首页顶部新增「激励计划」入口。
- 后台导航新增「开发者激励」。
- 后台首页增加待审核开发者申请统计和快捷入口。

## 校验结果

已执行：

- `php -l ai_api_gateway_lib.php`
- `php -l ai_api_console_api.php`
- `php -l admin/developer-applications.php`
- `php -l admin/index.php`
- `php -l admin/_common.php`
- `node --check assets/developer_plan.js`
- CSS 大括号平衡检查
- zip 完整性测试

本地接口 HTTP 烟测受当前容器缺少 `pdo_mysql` 扩展限制，无法连接该源码配置的 MySQL；语法与静态资源检查已通过。
