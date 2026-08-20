<?php
require_once __DIR__ . '/includes/init.php';

$counts = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN is_paused = 0 AND status = 'up' THEN 1 ELSE 0 END) AS up_count,
        SUM(CASE WHEN is_paused = 0 AND status = 'down' THEN 1 ELSE 0 END) AS down_count
     FROM websites
     WHERE is_paused = 0"
)->fetch();

$websites = $pdo->query(
    "SELECT * FROM websites
     WHERE is_paused = 0
     ORDER BY FIELD(status, 'down', 'unknown', 'up'), name ASC"
)->fetchAll();

$downCount = (int) $counts['down_count'];
$totalActive = (int) $counts['total'];

if ($downCount === 0) {
    $overallStatus = 'operational';
    $overallLabel = 'All systems operational';
} elseif ($downCount < $totalActive) {
    $overallStatus = 'partial';
    $overallLabel = 'Partial outage';
} else {
    $overallStatus = 'major';
    $overallLabel = 'Major outage';
}

function statusPublicLabel(string $status): string
{
    if ($status === 'down') {
        return 'Down';
    }
    if ($status === 'up') {
        return 'Operational';
    }
    return 'Unknown';
}

function displayHost(string $url): string
{
    $host = parse_url($url, PHP_URL_HOST);
    if ($host) {
        return $host;
    }
    return $url;
}

$pageTitle = getSetting($pdo, 'status_page_title', APP_NAME . ' Status');
$lastUpdated = date('Y-m-d H:i:s T');
$cssPath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($cssPath === '' || $cssPath === '.') {
    $cssPath = '';
}
$cssUrl = $cssPath . '/assets/css/status.css?v=4.2';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="60">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= e($cssUrl) ?>">
    <style>
        .status-item-bar i.up { background: #3ecf8e !important; }
        .status-item-bar i.down { background: #ef4444 !important; }
        .status-item-bar i.empty { background: #8b9cb3 !important; }
    </style>
</head>
<body class="status-page">
<div class="status-wrap">
    <header class="status-header">
        <strong><?= e($pageTitle) ?></strong>
        <a class="status-admin-link" href="<?= e(appUrl('login.php')) ?>">Admin login</a>
    </header>
    <p class="status-meta">Last updated <?= e($lastUpdated) ?> · Auto refresh every 60 sec</p>

    <section class="status-banner status-banner-<?= e($overallStatus) ?>">
        <span class="status-banner-dot"></span>
        <h1><?= e($overallLabel) ?></h1>
    </section>

    <section class="status-panel">
        <h2>Services</h2>
        <?php if (!$websites): ?>
            <div class="empty">No public monitors to display.</div>
        <?php else: ?>
            <div class="status-list">
                <?php foreach ($websites as $site): ?>
                    <?php
                        $isDown = $site['status'] === 'down';
                        $uptime90 = websiteUptime($pdo, (int) $site['id'], 90);
                        $bar = uptimeBar($pdo, (int) $site['id'], 90, 90);
                    ?>
                    <article class="status-item">
                        <div class="status-item-top">
                            <div class="status-item-url">
                                <strong><?= e(displayHost($site['url'])) ?></strong>
                                <span><?= e($site['name']) ?></span>
                            </div>
                            <span class="status-item-pct <?= $isDown ? 'is-down' : '' ?>"><?= $uptime90 === null ? '—' : number_format($uptime90, 3) . '%' ?></span>
                            <span class="status-item-label <?= $isDown ? 'is-down' : 'is-up' ?>">
                                <i></i><?= e(statusPublicLabel($site['status'])) ?>
                            </span>
                        </div>
                        <div class="status-item-bar" title="Last 90 days">
                            <?php foreach ($bar as $seg): ?>
                                <i class="<?= e($seg === 'empty' ? 'empty' : $seg) ?>"></i>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <footer class="status-footer">
        Powered by <?= e(APP_NAME) ?>
    </footer>
</div>
</body>
</html>
