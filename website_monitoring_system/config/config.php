<?php
/**
 * Application configuration
 * Telegram token/chat ID can also be changed in Admin > Settings.
 */

define('APP_NAME', 'UptimeGuard');
define('APP_TAGLINE', 'Website Monitoring System');
define('APP_EDITION', 'Pro');
define('APP_VERSION', '2.0.0');

define('DEMO_USERNAME', 'admin');
define('DEMO_PASSWORD', 'admin123');

// Used on the forgot-password page. Change this after install.
define('RECOVERY_KEY', 'MONITOR-RESET-2026');

// Fallback cron key (overridden by Settings if set)
define('CRON_SECRET', 'change-this-cron-secret');

// Session lifetime in seconds (8 hours)
define('SESSION_LIFETIME', 28800);

date_default_timezone_set('Asia/Kuala_Lumpur');
