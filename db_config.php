<?php
// Production-safe database config. Prefer environment variables in hosting panel.
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
define('DB_NAME', getenv('DB_NAME') ?: 'api_aifmusic_top');
define('DB_USER', getenv('DB_USER') ?: 'api_aifmusic_top');
define('DB_PASS', getenv('DB_PASS') ?: 'dz8SWX2HB3nTfmQe');
