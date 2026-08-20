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

$pageTitle = $site['name'];
$pageSubtitle = $site['url'];
$currentPage = 'websites';

$uptime24 = websiteUptime($pdo, $id, 1);
$uptime7  = websiteUptime($pdo, $id, 7);
$uptime30 = websiteUptime($pdo, $id, 30);
$uptime90 = websiteUptime($pdo, $id, 90);
$bar = uptimeBar($pdo, $id, 1, 36);
$bar90 = uptimeBar($pdo, $id, 90, 90);
$cls = statusClass($site['status'], (bool) $site['is_paused'], (bool) $site['is_slow']);

$logs = $pdo->prepare(
    'SELECT * FROM logs WHERE website_id = ? ORDER BY checked_at DESC LIMIT 30'
);
$logs->execute([$id]);
$logs = $logs->fetchAll();

$alerts = $pdo->prepare(
    'SELECT * FROM alerts WHERE website_id = ? ORDER BY sent_at DESC LIMIT 15'
);
$alerts->execute([$id]);
$alerts = $alerts->fetchAll();

require dirname(__DIR__) . '/includes/header.php';
?>

<div class="stats">
    <div class="stat-card <?= e($cls) ?>">
        <span>Current status</span>
        <strong><?= e(statusLabel($site['status'], (bool) $site['is_paused'], (bool) $site['is_slow'])) ?></strong>
    </div>
    <div class="stat-card">
        <span>Response time</span>
        <strong><?= $site['response_time'] !== null ? (int) $site['response_time'] . ' ms' : '—' ?></strong>
    </div>
    <div class="stat-card">
        <span>Uptime 24h / 7d / 30d / 90d</span>
        <strong>
            <?= $uptime24 === null ? '—' : number_format($uptime24, 2) . '%' ?>
            · <?= $uptime7 === null ? '—' : number_format($uptime7, 2) . '%' ?>
            · <?= $uptime30 === null ? '—' : number_format($uptime30, 2) . '%' ?>
            · <?= $uptime90 === null ? '—' : number_format($uptime90, 2) . '%' ?>
        </strong>
    </div>
    <div class="stat-card">
        <span>Last checked</span>
        <strong><?= e(timeAgo($site['last_checked'])) ?></strong>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h2>Last 24 hours</h2>
        <div class="actions">
            <a class="btn btn-success btn-sm" href="<?= e(appUrl('admin/website-check.php?id=' . $id)) ?>">Check now</a>
            <a class="btn btn-ghost btn-sm" href="<?= e(appUrl('admin/website-toggle.php?id=' . $id)) ?>"><?= $site['is_paused'] ? 'Resume' : 'Pause' ?></a>
            <a class="btn btn-ghost btn-sm" href="<?= e(appUrl('admin/website-form.php?id=' . $id)) ?>">Edit</a>
            <a class="btn btn-danger btn-sm" href="<?= e(appUrl('admin/website-delete.php?id=' . $id)) ?>">Delete</a>
        </div>
    </div>
    <div class="card-body">
        <div class="uptime-bar" style="height:22px"><?php foreach ($bar as $seg): ?><span class="<?= e($seg) ?>"></span><?php endforeach; ?></div>
        <p class="small" style="margin-top:10px">
            Interval: every <?= (int) $site['interval_minutes'] ?> min ·
            Timeout: <?= (int) $site['timeout_seconds'] ?>s ·
            Slow if over <?= (int) $site['slow_threshold_ms'] ?> ms ·
            HTTP <?= $site['http_code'] ? (int) $site['http_code'] : '—' ?>
            <?php if ($site['status_since']): ?> · <?= e($site['status']) ?> since <?= e($site['status_since']) ?><?php endif; ?>
        </p>
        <?php if ($site['last_error']): ?>
            <p class="flash flash-error" style="margin-top:12px"><?= e($site['last_error']) ?></p>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h2>Last 90 days</h2>
        <span class="uptime-pct"><?= $uptime90 === null ? '—' : number_format($uptime90, 2) . '% uptime' ?></span>
    </div>
    <div class="card-body">
        <div class="uptime-bar uptime-bar-90" title="Daily status for the last 90 days">
            <?php foreach ($bar90 as $seg): ?><span class="<?= e($seg) ?>"></span><?php endforeach; ?>
        </div>
        <p class="small" style="margin-top:10px">Green = up, red = down. Data is kept for <?= (int) logRetentionDays($pdo) ?> days.</p>
    </div>
</div>

<div class="two-col">
    <div class="card">
        <div class="card-head"><h2>Check history</h2><a class="small" href="<?= e(appUrl('admin/logs.php?website_id=' . $id)) ?>">All logs</a></div>
        <div class="table-wrap">
            <?php if (!$logs): ?>
                <div class="empty">No logs yet.</div>
            <?php else: ?>
            <table>
                <thead><tr><th>Time</th><th>Status</th><th>HTTP</th><th>Response</th><th>Note</th></tr></thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= e($log['checked_at']) ?></td>
                        <td><span class="badge <?= $log['status'] === 'up' ? ($log['is_slow'] ? 'slow' : 'up') : 'down' ?>"><?= e(strtoupper($log['status'])) ?></span></td>
                        <td><?= $log['http_code'] ? (int) $log['http_code'] : '—' ?></td>
                        <td><?= $log['response_time'] !== null ? (int) $log['response_time'] . ' ms' : '—' ?></td>
                        <td class="small"><?= e($log['error_message'] ?: '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h2>Status change alerts</h2></div>
        <div class="table-wrap">
            <?php if (!$alerts): ?>
                <div class="empty">No status-change alerts yet.</div>
            <?php else: ?>
            <table>
                <thead><tr><th>Time</th><th>Type</th><th>Telegram</th></tr></thead>
                <tbody>
                <?php foreach ($alerts as $alert): ?>
                    <tr>
                        <td><?= e($alert['sent_at']) ?></td>
                        <td><span class="badge <?= $alert['alert_type'] === 'recovery' ? 'up' : ($alert['alert_type'] === 'slow' ? 'slow' : 'down') ?>"><?= e(strtoupper($alert['alert_type'])) ?></span></td>
                        <td><?= $alert['sent'] ? 'Sent' : 'Not sent' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
