<?php
require_once dirname(__DIR__) . '/includes/init.php';
requireLogin();

$pageTitle = 'Alerts';
$pageSubtitle = 'Telegram notifications sent on status changes';
$currentPage = 'alerts';

$type = $_GET['type'] ?? 'all';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = ' WHERE 1=1';
$params = [];
if (in_array($type, ['down', 'recovery', 'slow'], true)) {
    $where .= ' AND a.alert_type = ?';
    $params[] = $type;
}

$count = $pdo->prepare("SELECT COUNT(*) FROM alerts a $where");
$count->execute($params);
$total = (int) $count->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT a.*, w.name, w.url
     FROM alerts a
     JOIN websites w ON w.id = a.website_id
     $where
     ORDER BY a.sent_at DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$alerts = $stmt->fetchAll();

require dirname(__DIR__) . '/includes/header.php';
?>

<form class="filters" method="get">
    <select name="type" style="max-width:200px">
        <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>All alerts</option>
        <option value="down" <?= $type === 'down' ? 'selected' : '' ?>>DOWN</option>
        <option value="recovery" <?= $type === 'recovery' ? 'selected' : '' ?>>RECOVERY</option>
        <option value="slow" <?= $type === 'slow' ? 'selected' : '' ?>>SLOW</option>
    </select>
    <button class="btn btn-ghost" type="submit">Filter</button>
</form>

<div class="card">
    <div class="table-wrap">
        <?php if (!$alerts): ?>
            <div class="empty">No alerts yet. Duplicate checks with the same status do not create new alerts.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Website</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Response</th>
                    <th>Telegram</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($alerts as $alert): ?>
                <tr>
                    <td><?= e($alert['sent_at']) ?></td>
                    <td>
                        <div class="site-name"><?= e($alert['name']) ?></div>
                        <div class="site-url"><?= e($alert['url']) ?></div>
                    </td>
                    <td><span class="badge <?= $alert['alert_type'] === 'recovery' ? 'up' : ($alert['alert_type'] === 'slow' ? 'slow' : 'down') ?>"><?= e(strtoupper($alert['alert_type'])) ?></span></td>
                    <td><?= e(strtoupper($alert['status'])) ?></td>
                    <td><?= $alert['response_time'] !== null ? (int) $alert['response_time'] . ' ms' : '—' ?></td>
                    <td><?= $alert['sent'] ? 'Sent' : 'Not sent' ?></td>
                    <td class="mono"><?= e(strip_tags($alert['message'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?= pagination($total, $page, $perPage, appUrl('admin/alerts.php?type=' . urlencode($type))) ?>
        <?php endif; ?>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
