<?php
require_once dirname(__DIR__) . '/includes/init.php';
requireLogin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$site = [
    'name' => '',
    'url' => 'https://',
    'interval_minutes' => 5,
    'timeout_seconds' => (int) getSetting($pdo, 'request_timeout', '10'),
    'slow_threshold_ms' => (int) getSetting($pdo, 'slow_threshold_ms', '3000'),
];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM websites WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        setFlash('error', 'Website not found.');
        redirect(appUrl('admin/websites.php'));
    }
    $site = $found;
}

$pageTitle = $id ? 'Edit monitor' : 'Add monitor';
$currentPage = 'websites';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $site['name'] = trim($_POST['name'] ?? '');
    $site['url'] = trim($_POST['url'] ?? '');
    $site['interval_minutes'] = (int) ($_POST['interval_minutes'] ?? 5);
    $site['timeout_seconds'] = (int) ($_POST['timeout_seconds'] ?? 10);
    $site['slow_threshold_ms'] = (int) ($_POST['slow_threshold_ms'] ?? 3000);

    if ($site['name'] === '') {
        $errors[] = 'Website name is required.';
    }
    if (!validHttpUrl($site['url'])) {
        $errors[] = 'Enter a valid URL starting with http:// or https://';
    }
    if ($site['interval_minutes'] < 1 || $site['interval_minutes'] > 1440) {
        $errors[] = 'Interval must be between 1 and 1440 minutes.';
    }
    if ($site['timeout_seconds'] < 3 || $site['timeout_seconds'] > 60) {
        $errors[] = 'Timeout must be between 3 and 60 seconds.';
    }
    if ($site['slow_threshold_ms'] < 200) {
        $errors[] = 'Slow threshold must be at least 200 ms.';
    }

    if (!$errors) {
        if ($id) {
            $stmt = $pdo->prepare(
                'UPDATE websites SET name = ?, url = ?, interval_minutes = ?, timeout_seconds = ?, slow_threshold_ms = ? WHERE id = ?'
            );
            $stmt->execute([
                $site['name'], $site['url'], $site['interval_minutes'],
                $site['timeout_seconds'], $site['slow_threshold_ms'], $id
            ]);
            setFlash('success', 'Monitor updated.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO websites (name, url, interval_minutes, timeout_seconds, slow_threshold_ms, status)
                 VALUES (?, ?, ?, ?, ?, "unknown")'
            );
            $stmt->execute([
                $site['name'], $site['url'], $site['interval_minutes'],
                $site['timeout_seconds'], $site['slow_threshold_ms']
            ]);
            $id = (int) $pdo->lastInsertId();
            setFlash('success', 'Monitor added. Run a check to get the first status.');
        }
        redirect(appUrl('admin/website-view.php?id=' . $id));
    }
}

require dirname(__DIR__) . '/includes/header.php';
?>

<div class="card" style="max-width:720px">
    <div class="card-body">
        <?php foreach ($errors as $err): ?>
            <div class="flash flash-error"><?= e($err) ?></div>
        <?php endforeach; ?>
        <form method="post">
            <?= csrfField() ?>
            <div class="form-row">
                <label for="name">Website name</label>
                <input type="text" id="name" name="name" value="<?= e($site['name']) ?>" required placeholder="My company site">
            </div>
            <div class="form-row">
                <label for="url">Website URL</label>
                <input type="url" id="url" name="url" value="<?= e($site['url']) ?>" required placeholder="https://example.com">
            </div>
            <div class="form-grid">
                <div class="form-row">
                    <label for="interval_minutes">Check interval (minutes)</label>
                    <input type="number" id="interval_minutes" name="interval_minutes" min="1" max="1440" value="<?= (int) $site['interval_minutes'] ?>" required>
                    <div class="help">Cron should run every 1 minute. Each site is only checked when its interval has passed.</div>
                </div>
                <div class="form-row">
                    <label for="timeout_seconds">Timeout (seconds)</label>
                    <input type="number" id="timeout_seconds" name="timeout_seconds" min="3" max="60" value="<?= (int) $site['timeout_seconds'] ?>" required>
                </div>
            </div>
            <div class="form-row">
                <label for="slow_threshold_ms">Slow response threshold (ms)</label>
                <input type="number" id="slow_threshold_ms" name="slow_threshold_ms" min="200" max="60000" value="<?= (int) $site['slow_threshold_ms'] ?>" required>
                <div class="help">If the site is UP but slower than this, a Telegram WARNING is sent (once, until it becomes fast again).</div>
            </div>
            <div class="actions">
                <button class="btn" type="submit"><?= $id ? 'Save changes' : 'Add monitor' ?></button>
                <a class="btn btn-ghost" href="<?= e(appUrl('admin/websites.php')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
