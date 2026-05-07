<?php
/** SMTP configuration. Set values via environment variables in production. */
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.126.com');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 465));
define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'ssl');
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: getenv('SMTP_USER') ?: 'funicloud@126.com');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: getenv('SMTP_PASS') ?: 'BQa8MxnmdYTFmc6k');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: getenv('SMTP_FROM') ?: 'funicloud@126.com');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'Music Site');
define('SMTP_TIMEOUT', (int)(getenv('SMTP_TIMEOUT') ?: 20));
