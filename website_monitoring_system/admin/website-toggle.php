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

$paused = (int) $site['is_paused'] === 1 ? 0 : 1;
$upd = $pdo->prepare('UPDATE websites SET is_paused = ? WHERE id = ?');
$upd->execute([$paused, $id]);

setFlash('success', $paused ? 'Monitor paused.' : 'Monitor resumed.');
redirect($_SERVER['HTTP_REFERER'] ?? appUrl('admin/websites.php'));
