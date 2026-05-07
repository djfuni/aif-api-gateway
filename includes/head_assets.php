<?php
/** Shared head assets for PHP-rendered pages. */
$assetVersion = $assetVersion ?? '20260506-modular';
?>
<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
<link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" as="style" crossorigin onload="this.onload=null;this.rel='stylesheet'">
<link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/v4-shims.min.css" as="style" crossorigin onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/v4-shims.min.css"></noscript>
<link href="assets/icons-fallback.css?v=<?= htmlspecialchars($assetVersion, ENT_QUOTES) ?>" rel="stylesheet">
<link rel="preload" href="assets/css/layout.css?v=<?= htmlspecialchars($assetVersion, ENT_QUOTES) ?>" as="style">
<link href="assets/ai_site.css?v=<?= htmlspecialchars($assetVersion, ENT_QUOTES) ?>" rel="stylesheet">
<link href="manifest.webmanifest" rel="manifest">
