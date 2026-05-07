# M3 Drawer Fix Notes

本包保留原来的 Google Material You / M3 视觉风格，修复手机端打开侧边栏后出现黑色遮罩覆盖、侧边栏功能无法点击的问题。

## 问题原因
- `.aif-shell` 在桌面端设置了 `position: relative; z-index: 1`，形成独立 stacking context。
- 手机端 `.aif-sidebar` 虽然设置了较高 `z-index`，但它仍被限制在 `.aif-shell` 的低层级上下文中。
- `.aif-backdrop` 是 `.aif-shell` 的兄弟节点，处在更高的根层级，因此实际盖在侧边栏上方，造成侧边栏发黑并拦截点击。

## 修复内容
- 手机端将 `.aif-shell` 的 `z-index` 重置为 `auto`，让 fixed 侧边栏回到根层级参与排序。
- 保持 `.aif-sidebar` 高于 `.aif-backdrop`，遮罩只覆盖主内容区，不再压住侧边栏。
- 去掉打开抽屉时 `body` 上的 `touch-action:none`，避免移动端触摸交互被过度抑制。
- 侧边栏显式启用 `pointer-events:auto` 与纵向滚动触控，确保菜单、按钮可点可滚动。
- 点击侧边栏导航项后自动收起抽屉，提升移动端使用体验。
- 增加 `aria-expanded`、`aria-hidden`、`aria-controls` 等状态同步，并在窗口变宽时自动关闭移动端抽屉状态。
- 更新 CSS / JS 版本号，减少浏览器继续读取旧缓存的概率。

## 修改文件
- assets/ai_site.css
- assets/ai_site.js
- index.html
- account.html
- console.html
- redeem.html
