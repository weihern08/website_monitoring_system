<?php
/**
 * Bootstrap
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/telegram.php';

if (defined('SESSION_LIFETIME') && session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);
    session_set_cookie_params(SESSION_LIFETIME);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
$isInstall  = $scriptName === 'install.php';
$isCron     = $scriptName === 'monitor.php';

if (!$isInstall && !isInstalled()) {
    redirect(appUrl('install.php'));
}

if (!$isInstall && !$isCron && isInstalled()) {
    try {
        $pdo = getPDO();
    } catch (PDOException $e) {
        http_response_code(500);
        echo '<h2>Database connection failed</h2>';
        echo '<p>Check <code>config/database.php</code> or run the installer again.</p>';
        echo '<p>' . e($e->getMessage()) . '</p>';
        exit;
    }
}
