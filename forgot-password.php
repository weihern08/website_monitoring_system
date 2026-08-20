<?php
require_once __DIR__ . '/includes/init.php';

if (isLoggedIn()) {
    redirect(appUrl('admin/index.php'));
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = trim($_POST['username'] ?? '');
    $key      = trim($_POST['recovery_key'] ?? '');
    $password = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($username === '' || $key === '' || $password === '') {
        $error = 'All fields are required.';
    } elseif (!hash_equals(RECOVERY_KEY, $key)) {
        $error = 'Recovery key is incorrect. Check config/config.php.';
    } elseif (strlen($password) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM admins WHERE username = ?');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        if (!$admin) {
            $error = 'Admin username not found.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $upd = $pdo->prepare('UPDATE admins SET password = ? WHERE id = ?');
            $upd->execute([$hash, $admin['id']]);
            $success = 'Password updated. You can now log in.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot password · <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(appUrl('assets/css/style.css')) ?>">
</head>
<body class="login-page">
    <div class="auth-card">
        <h1>Reset password</h1>
        <p class="muted">Enter your admin username, the recovery key from <code>config/config.php</code>, and a new password.</p>

        <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="flash flash-success"><?= e($success) ?></div><?php endif; ?>

        <form method="post">
            <?= csrfField() ?>
            <div class="form-row">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-row">
                <label for="recovery_key">Recovery key</label>
                <input type="text" id="recovery_key" name="recovery_key" required>
            </div>
            <div class="form-row">
                <label for="new_password">New password</label>
                <div class="password-wrap">
                    <input type="password" id="new_password" name="new_password" required>
                    <button type="button" class="toggle-pass" data-toggle-password="new_password">Show</button>
                </div>
            </div>
            <div class="form-row">
                <label for="confirm_password">Confirm password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button class="btn" type="submit" style="width:100%">Reset password</button>
        </form>
        <p class="small" style="margin-top:14px"><a href="<?= e(appUrl('login.php')) ?>">Back to login</a></p>
    </div>
    <script src="<?= e(appUrl('assets/js/app.js')) ?>"></script>
</body>
</html>
