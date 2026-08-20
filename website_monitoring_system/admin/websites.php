<?php
require_once dirname(__DIR__) . '/includes/init.php';
requireLogin();

$pageTitle = 'Monitors';
$pageSubtitle = 'Add, search, and manage websites';
$currentPage = 'websites';

$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'all';
$period = $_GET['period'] ?? 'all';

$sql = 'SELECT * FROM websites WHERE 1=1';
$params = [];

if ($q !== '') {
    $sql .= ' AND (name LIKE ? OR url LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

if ($status === 'up') {
    $sql .= " AND is_paused = 0 AND status = 'up'";
} elseif ($status === 'down') {
    $sql .= " AND is_paused = 0 AND status = 'down'";
} elseif ($status === 'paused') {
    $sql .= ' AND is_paused = 1';
}

if ($period === 'today') {
    $sql .= ' AND DATE(last_checked) = CURDATE()';
} elseif ($period === 'week') {
    $sql .= ' AND last_checked >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
}

$sql .= ' ORDER BY is_paused ASC, name ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$websites = $stmt->fetchAll();

require dirname(__DIR__) . '/includes/header.php';
?>

<form class="filters" method="get">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search name or URL" style="max-width:260px">
    <select name="status" style="max-width:160px">
        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All websites</option>
        <option value="up" <?= $status === 'up' ? 'selected' : '' ?>>UP only</option>
        <option value="down" <?= $status === 'down' ? 'selected' : '' ?>>DOWN only</option>
        <option value="paused" <?= $status === 'paused' ? 'selected' : '' ?>>Paused</option>
    </select>
    <select name="period" style="max-width:200px">
        <option value="all" <?= $period === 'all' ? 'selected' : '' ?>>Any check time</option>
        <option value="today" <?= $period === 'today' ? 'selected' : '' ?>>Checked today</option>
        <option value="week" <?= $period === 'week' ? 'selected' : '' ?>>Last 7 days</option>
    </select>
    <button class="btn btn-ghost" type="submit">Filter</button>
    <a class="btn" href="<?= e(appUrl('admin/website-form.php')) ?>">Add website</a>
</form>

<div class="card">
    <div class="table-wrap">
        <?php if (!$websites): ?>
            <div class="empty">No websites match this filter.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Name</th>
                    <th>URL</th>
                    <th>Response</th>
                    <th>Interval</th>
                    <th>Last checked</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($websites as $site): ?>
                <?php $cls = statusClass($site['status'], (bool) $site['is_paused'], (bool) $site['is_slow']); ?>
                <tr>
                    <td><span class="badge <?= e($cls) ?>"><?= e(statusLabel($site['status'], (bool) $site['is_paused'], (bool) $site['is_slow'])) ?></span></td>
                    <td class="site-name"><a href="<?= e(appUrl('admin/website-view.php?id=' . $site['id'])) ?>"><?= e($site['name']) ?></a></td>
                    <td class="site-url"><?= e($site['url']) ?></td>
                    <td><?= $site['response_time'] !== null ? (int) $site['response_time'] . ' ms' : '—' ?></td>
                    <td><?= (int) $site['interval_minutes'] ?> min</td>
                    <td><?= e(timeAgo($site['last_checked'])) ?></td>
                    <td class="actions">
                        <a class="btn btn-ghost btn-sm" href="<?= e(appUrl('admin/website-check.php?id=' . $site['id'])) ?>">Check</a>
                        <a class="btn btn-ghost btn-sm" href="<?= e(appUrl('admin/website-toggle.php?id=' . $site['id'])) ?>"><?= $site['is_paused'] ? 'Resume' : 'Pause' ?></a>
                        <a class="btn btn-ghost btn-sm" href="<?= e(appUrl('admin/website-form.php?id=' . $site['id'])) ?>">Edit</a>
                        <a class="btn btn-danger btn-sm" href="<?= e(appUrl('admin/website-delete.php?id=' . $site['id'])) ?>" data-confirm="Delete this website and all of its logs?">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
