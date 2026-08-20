<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Dashboard') ?> · <?= e(APP_NAME) ?> Pro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(appUrl('assets/css/style.css')) ?>">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <div class="app-main">
        <header class="topbar">
            <div>
                <h1><?= e($pageTitle ?? 'Dashboard') ?></h1>
                <?php if (!empty($pageSubtitle)): ?>
                    <p class="muted"><?= e($pageSubtitle) ?></p>
                <?php endif; ?>
            </div>
            <div class="topbar-actions">
                <span class="user-chip">
                    <span class="avatar"><?= strtoupper(substr($_SESSION['admin_username'] ?? 'A', 0, 1)) ?></span>
                    <?= e($_SESSION['admin_username'] ?? 'Admin') ?>
                    <span class="pro-badge">PRO</span>
                </span>
                <a class="btn btn-ghost" href="<?= e(appUrl('admin/logout.php')) ?>">Logout</a>
            </div>
        </header>
        <div class="content">
            <?php $flash = getFlash(); if ($flash): ?>
                <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>
