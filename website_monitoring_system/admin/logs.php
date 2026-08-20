<?php
require_once dirname(__DIR__) . '/includes/init.php';
requireLogin();

$pageTitle = 'Monitoring logs';
$pageSubtitle = 'Full check history for all websites';
$currentPage = 'logs';

$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'all';
$websiteId = (int) ($_GET['website_id'] ?? 0);
$period = $_GET['period'] ?? 'all';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$where = ' WHERE 1=1';
$params = [];

if ($websiteId) {
    $where .= ' AND l.website_id = ?';
    $params[] = $websiteId;
}
if ($q !== '') {
    $where .= ' AND (w.name LIKE ? OR w.url LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($status === 'up' || $status === 'down') {
    $where .= ' AND l.status = ?';
    $params[] = $status;
}
if ($period === 'today') {
    $where .= ' AND DATE(l.checked_at) = CURDATE()';
} elseif ($period === 'week') {
    $where .= ' AND l.checked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
} elseif ($period === 'month') {
    $where .= ' AND l.checked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
} elseif ($period === '90days') {
    $where .= ' AND l.checked_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)';
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM logs l JOIN websites w ON w.id = l.website_id $where");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$sql = "SELECT l.*, w.name, w.url
        FROM logs l
        JOIN websites w ON w.id = l.website_id
        $where
        ORDER BY l.checked_at DESC
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$sites = $pdo->query('SELECT id, name FROM websites ORDER BY name')->fetchAll();
$queryBase = appUrl('admin/logs.php?q=' . urlencode($q) . '&status=' . urlencode($status) . '&period=' . urlencode($period) . '&website_id=' . $websiteId);

require dirname(__DIR__) . '/includes/header.php';
?>

<form class="filters" method="get">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search name or URL" style="max-width:240px">
    <select name="website_id" style="max-width:200px">
        <option value="0">All websites</option>
        <?php foreach ($sites as $s): ?>
            <option value="<?= (int) $s['id'] ?>" <?= $websiteId === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="status" style="max-width:140px">
        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All status</option>
        <option value="up" <?= $status === 'up' ? 'selected' : '' ?>>UP</option>
        <option value="down" <?= $status === 'down' ? 'selected' : '' ?>>DOWN</option>
    </select>
    <select name="period" style="max-width:180px">
        <option value="all" <?= $period === 'all' ? 'selected' : '' ?>>Any time</option>
        <option value="today" <?= $period === 'today' ? 'selected' : '' ?>>Today</option>
        <option value="week" <?= $period === 'week' ? 'selected' : '' ?>>Last 7 days</option>
        <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>Last 30 days</option>
        <option value="90days" <?= $period === '90days' ? 'selected' : '' ?>>Last 90 days</option>
    </select>
    <button class="btn btn-ghost" type="submit">Filter</button>
</form>

<div class="card">
    <div class="table-wrap">
        <?php if (!$logs): ?>
            <div class="empty">No logs found.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Checked at</th>
                    <th>Website</th>
                    <th>URL</th>
                    <th>Status</th>
                    <th>HTTP</th>
                    <th>Response</th>
                    <th>Error</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= e($log['checked_at']) ?></td>
                    <td><a href="<?= e(appUrl('admin/website-view.php?id=' . $log['website_id'])) ?>"><?= e($log['name']) ?></a></td>
                    <td class="site-url"><?= e($log['url']) ?></td>
                    <td><span class="badge <?= $log['status'] === 'up' ? ($log['is_slow'] ? 'slow' : 'up') : 'down' ?>"><?= e(strtoupper($log['status'])) ?></span></td>
                    <td><?= $log['http_code'] ? (int) $log['http_code'] : '—' ?></td>
                    <td><?= $log['response_time'] !== null ? (int) $log['response_time'] . ' ms' : '—' ?></td>
                    <td class="small"><?= e($log['error_message'] ?: '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?= pagination($total, $page, $perPage, $queryBase) ?>
        <?php endif; ?>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
