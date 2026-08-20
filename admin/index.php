<?php
require_once dirname(__DIR__) . '/includes/init.php';
requireLogin();

$pageTitle = 'Dashboard';
$pageSubtitle = 'Live overview of all website monitors';
$currentPage = 'dashboard';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_monitor'])) {
    verifyCsrf();
    $result = runMonitorCycle($pdo);
    setFlash('success', 'Monitor cycle finished. Checked ' . $result['checked'] . ' website(s), sent ' . $result['alerts'] . ' alert(s).');
    redirect(appUrl('admin/index.php'));
}

$counts = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN is_paused = 1 THEN 1 ELSE 0 END) AS paused,
        SUM(CASE WHEN is_paused = 0 AND status = 'up' THEN 1 ELSE 0 END) AS up_count,
        SUM(CASE WHEN is_paused = 0 AND status = 'down' THEN 1 ELSE 0 END) AS down_count,
        SUM(CASE WHEN is_paused = 0 AND status = 'unknown' THEN 1 ELSE 0 END) AS unknown_count,
        ROUND(AVG(CASE WHEN is_paused = 0 AND response_time IS NOT NULL THEN response_time END), 0) AS avg_rt
     FROM websites"
)->fetch();

$websites = $pdo->query('SELECT * FROM websites ORDER BY is_paused ASC, FIELD(status, "down", "unknown", "up"), name ASC')->fetchAll();
$recentAlerts = $pdo->query(
    'SELECT a.*, w.name, w.url
     FROM alerts a
     JOIN websites w ON w.id = a.website_id
     ORDER BY a.sent_at DESC
     LIMIT 8'
)->fetchAll();
$recentLogs = $pdo->query(
    'SELECT l.*, w.name, w.url
     FROM logs l
     JOIN websites w ON w.id = l.website_id
     ORDER BY l.checked_at DESC
     LIMIT 8'
)->fetchAll();

$cron = cronStatus($pdo);
$retentionDays = logRetentionDays($pdo);

require dirname(__DIR__) . '/includes/header.php';
?>

<?php if ($cron['active']): ?>
    <div class="monitor-banner monitor-banner-active">
        <strong>Auto monitoring is running</strong>
        <span>Last cron run: <?= e(timeAgo($cron['last'])) ?> · Keeping <?= (int) $retentionDays ?> days of history</span>
    </div>
<?php else: ?>
    <div class="monitor-banner monitor-banner-warn">
        <strong>Auto monitoring is not active</strong>
        <span>Set cPanel Cron Jobs to run every 1 minute. Last run: <?= $cron['last'] ? e(timeAgo($cron['last'])) : 'never' ?></span>
        <a class="btn btn-sm btn-ghost" href="<?= e(appUrl('admin/settings.php')) ?>">Setup cron</a>
    </div>
<?php endif; ?>

<div class="stats">
    <div class="stat-card">
        <span>Total monitors</span>
        <strong><?= (int) $counts['total'] ?></strong>
    </div>
    <div class="stat-card up">
        <span>Up</span>
        <strong><?= (int) $counts['up_count'] ?></strong>
    </div>
    <div class="stat-card down">
        <span>Down</span>
        <strong><?= (int) $counts['down_count'] ?></strong>
    </div>
    <div class="stat-card paused">
        <span>Paused / avg response</span>
        <strong><?= (int) $counts['paused'] ?> · <?= $counts['avg_rt'] !== null ? (int) $counts['avg_rt'] . ' ms' : '—' ?></strong>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h2>Monitors</h2>
        <div class="actions">
            <form method="post">
                <?= csrfField() ?>
                <button class="btn btn-success btn-sm" name="run_monitor" value="1">Run checks now</button>
            </form>
            <a class="btn btn-sm" href="<?= e(appUrl('admin/website-form.php')) ?>">Add monitor</a>
        </div>
    </div>
    <div class="table-wrap">
        <?php if (!$websites): ?>
            <div class="empty">No monitors yet. Add a website to start checking uptime.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Monitor</th>
                    <th>Last 24h</th>
                    <th>Uptime (7d)</th>
                    <th>Uptime (90d)</th>
                    <th>Response</th>
                    <th>Interval</th>
                    <th>Last check</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($websites as $site): ?>
                <?php
                    $cls = statusClass($site['status'], (bool) $site['is_paused'], (bool) $site['is_slow']);
                    $uptime7 = websiteUptime($pdo, (int) $site['id'], 7);
                    $uptime90 = websiteUptime($pdo, (int) $site['id'], 90);
                    $bar = uptimeBar($pdo, (int) $site['id'], 1);
                ?>
                <tr>
                    <td>
                        <span class="badge <?= e($cls) ?>"><?= e(statusLabel($site['status'], (bool) $site['is_paused'], (bool) $site['is_slow'])) ?></span>
                    </td>
                    <td>
                        <div class="site-name"><a href="<?= e(appUrl('admin/website-view.php?id=' . $site['id'])) ?>"><?= e($site['name']) ?></a></div>
                        <div class="site-url"><?= e($site['url']) ?></div>
                    </td>
                    <td>
                        <div class="uptime-bar" title="Last 24 hours">
                            <?php foreach ($bar as $seg): ?>
                                <span class="<?= e($seg) ?>"></span>
                            <?php endforeach; ?>
                        </div>
                    </td>
                    <td class="uptime-pct"><?= $uptime7 === null ? '—' : e(number_format($uptime7, 2) . '%') ?></td>
                    <td class="uptime-pct"><?= $uptime90 === null ? '—' : e(number_format($uptime90, 2) . '%') ?></td>
                    <td><?= $site['response_time'] !== null ? (int) $site['response_time'] . ' ms' : '—' ?></td>
                    <td>every <?= (int) $site['interval_minutes'] ?> min</td>
                    <td><?= e(timeAgo($site['last_checked'])) ?></td>
                    <td class="actions">
                        <a class="btn btn-ghost btn-sm" href="<?= e(appUrl('admin/website-check.php?id=' . $site['id'])) ?>">Check</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<div class="two-col">
    <div class="card">
        <div class="card-head"><h2>Recent alerts</h2><a class="small" href="<?= e(appUrl('admin/alerts.php')) ?>">View all</a></div>
        <div class="table-wrap">
            <?php if (!$recentAlerts): ?>
                <div class="empty">No alerts yet. Alerts appear only when status changes.</div>
            <?php else: ?>
            <table>
                <thead><tr><th>When</th><th>Website</th><th>Type</th><th>Telegram</th></tr></thead>
                <tbody>
                <?php foreach ($recentAlerts as $alert): ?>
                    <tr>
                        <td><?= e(timeAgo($alert['sent_at'])) ?></td>
                        <td><?= e($alert['name']) ?></td>
                        <td><span class="badge <?= $alert['alert_type'] === 'recovery' ? 'up' : ($alert['alert_type'] === 'slow' ? 'slow' : 'down') ?>"><?= e(strtoupper($alert['alert_type'])) ?></span></td>
                        <td><?= $alert['sent'] ? 'Sent' : 'Not sent' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h2>Latest activity</h2><a class="small" href="<?= e(appUrl('admin/logs.php')) ?>">View logs</a></div>
        <div class="table-wrap">
            <?php if (!$recentLogs): ?>
                <div class="empty">No checks yet. Click “Run checks now” or set up cron.</div>
            <?php else: ?>
            <table>
                <thead><tr><th>When</th><th>Website</th><th>Status</th><th>Time</th></tr></thead>
                <tbody>
                <?php foreach ($recentLogs as $log): ?>
                    <tr>
                        <td><?= e(timeAgo($log['checked_at'])) ?></td>
                        <td><?= e($log['name']) ?></td>
                        <td><span class="badge <?= $log['status'] === 'up' ? ($log['is_slow'] ? 'slow' : 'up') : 'down' ?>"><?= e(strtoupper($log['status'])) ?></span></td>
                        <td><?= $log['response_time'] !== null ? (int) $log['response_time'] . ' ms' : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
