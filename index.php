<?php
require_once __DIR__ . '/includes/init.php';

if (isInstalled() && isLoggedIn()) {
    redirect(appUrl('admin/index.php'));
}

if (isInstalled()) {
    redirect(appUrl('login.php'));
}

redirect(appUrl('install.php'));
