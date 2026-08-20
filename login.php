<?php
require_once __DIR__ . '/includes/init.php';

if (isLoggedIn()) {
    redirect(appUrl('admin/index.php'));
}

$error = '';
$username = '';
$installed = isset($_GET['installed']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } elseif (!loginAdmin($pdo, $username, $password)) {
        $error = 'Invalid username or password.';
    } else {
        redirect(appUrl('admin/index.php'));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login · <?= e(APP_NAME) ?> Pro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(appUrl('assets/css/style.css')) ?>">
</head>
<body class="login-page">
    <div class="login-shell">
        <section class="login-hero">
            <div class="auth-brand">
                <span class="brand-mark"><span class="pulse-dot"></span></span>
                <div>
                    <strong><?= e(APP_NAME) ?></strong>
                    <div class="small"><?= e(APP_TAGLINE) ?></div>
                </div>
                <span class="pro-badge">PRO</span>
            </div>
            <h1>Monitor every site.<br>Get alerts instantly.</h1>
            <p>Enterprise-style uptime monitoring with Telegram alerts, response time tracking, and full incident history.</p>
            <ul class="hero-points">
                <li>24/7 HTTP uptime checks</li>
                <li>Telegram DOWN / RECOVERY alerts</li>
                <li>Slow-response warnings</li>
                <li>Admin-only secure console</li>
            </ul>
        </section>

        <section class="auth-card login-card">
            <div class="card-kicker">Admin console</div>
            <h2>Sign in</h2>
            <p class="muted">This system is admin-only. No public registration.</p>

            <div class="demo-box">
                <div class="demo-box-head">
                    <span>Demo account</span>
                    <button type="button" class="btn btn-sm btn-ghost" id="fill-demo">Use demo</button>
                </div>
                <p><span>User</span> <code><?= e(DEMO_USERNAME) ?></code></p>
                <p><span>Password</span> <code><?= e(DEMO_PASSWORD) ?></code></p>
            </div>

            <?php if ($installed): ?>
                <div class="flash flash-success">Install complete. Sign in with the admin account you created.</div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="flash flash-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <?= csrfField() ?>
                <div class="form-row">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?= e($username) ?>" required autofocus>
                </div>
                <div class="form-row">
                    <label for="password">Password</label>
                    <div class="password-wrap">
                        <input type="password" id="password" name="password" required>
                        <button type="button" class="toggle-pass" data-toggle-password="password">Show</button>
                    </div>
                </div>
                <button class="btn btn-pro" type="submit">Sign in to Pro</button>
            </form>
        <p class="small login-foot">
            <span>
                <a href="<?= e(appUrl('forgot-password.php')) ?>">Forgot password?</a>
                · <a href="<?= e(appUrl('status.php')) ?>">View status page</a>
            </span>
            <span>v<?= e(APP_VERSION) ?> <?= e(APP_EDITION) ?></span>
        </p>
        </section>
    </div>
    <script src="<?= e(appUrl('assets/js/app.js')) ?>"></script>
    <script>
        (function () {
            var btn = document.getElementById('fill-demo');
            if (!btn) return;
            btn.addEventListener('click', function () {
                document.getElementById('username').value = <?= json_encode(DEMO_USERNAME) ?>;
                document.getElementById('password').value = <?= json_encode(DEMO_PASSWORD) ?>;
                document.getElementById('password').focus();
            });
        })();
    </script>
</body>
</html>
