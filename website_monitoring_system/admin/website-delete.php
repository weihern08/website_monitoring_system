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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $del = $pdo->prepare('DELETE FROM websites WHERE id = ?');
    $del->execute([$id]);
    setFlash('success', 'Monitor deleted.');
    redirect(appUrl('admin/websites.php'));
}

$pageTitle = 'Delete monitor';
$currentPage = 'websites';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="card" style="max-width:560px">
    <div class="card-body">
        <p>Delete <strong><?= e($site['name']) ?></strong>?</p>
        <p class="muted">This also removes its logs and alerts. This cannot be undone.</p>
        <form method="post" class="actions">
            <?= csrfField() ?>
            <button class="btn btn-danger" type="submit">Delete</button>
            <a class="btn btn-ghost" href="<?= e(appUrl('admin/websites.php')) ?>">Cancel</a>
        </form>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
