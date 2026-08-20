<?php
/**
 * One-time installer for XAMPP.
 */
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$lockFile = __DIR__ . '/config/installed.lock';
if (file_exists($lockFile)) {
    header('Location: login.php');
    exit;
}

$step = (int) ($_POST['step'] ?? $_GET['step'] ?? 1);
$errors = [];
$info = [
    'db_host' => $_POST['db_host'] ?? 'localhost',
    'db_name' => $_POST['db_name'] ?? 'website_monitoring',
    'db_user' => $_POST['db_user'] ?? 'root',
    'db_pass' => $_POST['db_pass'] ?? '',
    'username' => $_POST['username'] ?? 'admin',
];

function writeDatabaseConfig(array $info): void
{
    $content = "<?php\n"
        . "define('DB_HOST', " . var_export($info['db_host'], true) . ");\n"
        . "define('DB_NAME', " . var_export($info['db_name'], true) . ");\n"
        . "define('DB_USER', " . var_export($info['db_user'], true) . ");\n"
        . "define('DB_PASS', " . var_export($info['db_pass'], true) . ");\n"
        . "define('DB_CHARSET', 'utf8mb4');\n\n"
        . "function getPDO(): PDO\n"
        . "{\n"
        . "    static \$pdo = null;\n"
        . "    if (\$pdo instanceof PDO) {\n"
        . "        return \$pdo;\n"
        . "    }\n"
        . "    \$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;\n"
        . "    \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, [\n"
        . "        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,\n"
        . "        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n"
        . "        PDO::ATTR_EMULATE_PREPARES   => false,\n"
        . "    ]);\n"
        . "    return \$pdo;\n"
        . "}\n";
    file_put_contents(__DIR__ . '/config/database.php', $content);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step >= 2) {
    $username = trim($info['username']);
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if ($username === '' || $password === '') {
        $errors[] = 'Admin username and password are required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        try {
            if (!function_exists('curl_init')) {
                throw new RuntimeException('PHP cURL extension is required. Enable extension=curl in php.ini.');
            }

            $dsn = 'mysql:host=' . $info['db_host'] . ';charset=utf8mb4';
            $pdoRoot = new PDO($dsn, $info['db_user'], $info['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $dbName = preg_replace('/[^a-zA-Z0-9_]/', '', $info['db_name']);
            $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdoRoot->exec("USE `$dbName`");

            $sql = file_get_contents(__DIR__ . '/database/schema.sql');
            $sql = preg_replace('/CREATE DATABASE[\s\S]*?;/i', '', $sql);
            $sql = preg_replace('/USE\s+`?[^;`]+`?\s*;/i', '', $sql);
            foreach (preg_split('/;\s*[\r\n]+/', $sql) as $statement) {
                $statement = trim(preg_replace('/^\s*--.*$/m', '', $statement));
                if ($statement !== '') {
                    $pdoRoot->exec($statement);
                }
            }

            writeDatabaseConfig($info);

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdoRoot->prepare(
                'INSERT INTO admins (username, password) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE password = VALUES(password)'
            );
            $stmt->execute([$username, $hash]);

            $secret = bin2hex(random_bytes(8));
            $upd = $pdoRoot->prepare(
                'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );
            $upd->execute(['cron_secret', $secret]);

            file_put_contents($lockFile, date('c'));
            header('Location: login.php?installed=1');
            exit;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install · <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="install-page">
<div class="auth-card wide">
    <div class="auth-brand">
        <span class="brand-mark"><span class="pulse-dot"></span></span>
        <div>
            <strong><?= e(APP_NAME) ?></strong>
            <div class="small">XAMPP installer</div>
        </div>
    </div>
    <h1>Setup</h1>
    <p class="muted">Creates the MySQL database, tables, and the single admin account.</p>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post">
        <input type="hidden" name="step" value="2">
        <h2 style="font-size:16px">1. Database</h2>
        <div class="form-grid">
            <div class="form-row">
                <label>MySQL host</label>
                <input type="text" name="db_host" value="<?= e($info['db_host']) ?>" required>
            </div>
            <div class="form-row">
                <label>Database name</label>
                <input type="text" name="db_name" value="<?= e($info['db_name']) ?>" required>
            </div>
            <div class="form-row">
                <label>Username</label>
                <input type="text" name="db_user" value="<?= e($info['db_user']) ?>" required>
            </div>
            <div class="form-row">
                <label>Password</label>
                <input type="password" name="db_pass" value="<?= e($info['db_pass']) ?>" placeholder="(empty on XAMPP)">
            </div>
        </div>

        <h2 style="font-size:16px">2. Admin account</h2>
        <div class="form-grid">
            <div class="form-row">
                <label>Admin username</label>
                <input type="text" name="username" value="<?= e($info['username']) ?>" required>
            </div>
            <div class="form-row">
                <label>Password</label>
                <input type="password" name="password" required minlength="6" placeholder="admin123">
            </div>
            <div class="form-row">
                <label>Confirm password</label>
                <input type="password" name="confirm" required minlength="6">
            </div>
        </div>
        <button class="btn" type="submit">Install now</button>
    </form>
</div>
</body>
</html>
