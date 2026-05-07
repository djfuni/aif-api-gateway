# PHP include components

These includes centralize shared head assets, sidebar navigation, and mobile bottom navigation for PHP-rendered pages.

Existing `.html` files are kept for compatibility. When you migrate pages to `.php`, use:

```php
<?php $assetVersion = '20260506-modular'; include __DIR__ . '/includes/head_assets.php'; ?>
<?php $activeView = 'home'; include __DIR__ . '/includes/site_sidebar.php'; ?>
<?php include __DIR__ . '/includes/mobile_bottom_nav.php'; ?>
```
