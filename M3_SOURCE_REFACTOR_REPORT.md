# NewAPI M3 source refactor report

Date: 20260506-modular

## Frontend

- Split `assets/ai_site.css` into modular entry imports:
  - `assets/css/layout.css`
  - `assets/css/components.css`
  - `assets/css/themes.css`
  - `assets/css/mobile.css`
- Extracted inline styles from:
  - `account.html` → `assets/css/account.css`
  - `developer-plan.html` → `assets/developer_plan.css`
- Extracted inline script from `developer-plan.html` → `assets/developer_plan.js`.
- Replaced Font Awesome 4.7 CDN with Font Awesome 6.5.2 + v4 shims and a local pure-CSS fallback (`assets/icons-fallback.css`). No font files are bundled.
- Added global Toast/Skeleton helpers in `assets/aif_shared.js` and wired existing pages to use the shared toast.
- Added hash-based SPA routing (`#home`, `#console`, `#models`, `#chat`) with browser back/forward support.
- Added mobile bottom navigation and touch gestures for the drawer.
- Added light/dark/system theme switching in `assets/ai_theme.js` and CSS variables in `assets/css/themes.css`.
- Added PWA registration, offline page, Service Worker cache, install prompt, and offline POST queue skeleton.

## Backend

- Added modular PHP façade classes under `lib/` for Auth, Wallet, Gateway, Provider, Order, Redeem, Database and Cache.
- Added file cache helpers in `lib/Cache.php` and cached `models_live.php` registry responses for 300 seconds unless `?refresh=1` is used.
- Expanded MySQL migration source list to include gateway JSON stores (`ai_api_keys.json`, `ai_api_wallets.json`, orders, usage, redeem data, etc.). The migration marker was bumped to v3 so existing installs can re-run the migration.
- Added `idx_store_key_updated_at` to the generic MySQL row store schema.
- Added optional SQL notes in `database/migrations/20260506_mysql_indexes_and_cache.sql`.

## Compatibility notes

- Existing `.html` entry files remain available.
- PHP includes are provided under `includes/` for progressive migration to shared headers/navigation.
- Existing endpoint URLs were not changed.
