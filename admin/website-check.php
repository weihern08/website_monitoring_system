<?php
require_once dirname(__DIR__) . '/includes/init.php';
requireLogin();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM websites WHERE id = ?');
$stmt->execute([$id]);
$site = $stmt->fetch();

if (!$site) {
    setFlash('error', 'Website not found.');
    redirect(appUrl('admin/websites.php'));
}

$timeout = (int) ($site['timeout_seconds'] ?: getSetting($pdo, 'request_timeout', '10'));
$check = checkWebsiteUrl($site['url'], max(3, $timeout));
$alert = recordCheck($pdo, $site, $check, true);

$msg = strtoupper($check['status']) . ' · ' . $check['response_time'] . ' ms';
if ($alert) {
    $msg .= ' · alert: ' . strtoupper($alert);
}
setFlash($check['up'] ? 'success' : 'error', 'Checked ' . $site['name'] . ': ' . $msg);

$back = $_SERVER['HTTP_REFERER'] ?? appUrl('admin/website-view.php?id=' . $id);
redirect($back);
