<?php
require_once dirname(__DIR__) . '/includes/init.php';
logoutAdmin();
header('Location: ' . appUrl('login.php'));
exit;
