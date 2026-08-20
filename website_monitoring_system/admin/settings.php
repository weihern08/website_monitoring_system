<?php
require_once dirname(__DIR__) . '/includes/init.php';
requireLogin();

$pageTitle = 'Settings';
$pageSubtitle = 'Telegram bot, thresholds, and admin password';
$currentPage = 'settings';
$chats = [];
$testResult = null;

$admin = currentAdmin($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'save') {
        setSetting($pdo, 'telegram_bot_token', trim($_POST['telegram_bot_token'] ?? ''));
        setSetting($pdo, 'telegram_chat_id', trim($_POST['telegram_chat_id'] ?? ''));
        setSetting($pdo, 'telegram_enabled', isset($_POST['telegram_enabled']) ? '1' : '0');
        setSetting($pdo, 'slow_threshold_ms', (string) max(200, (int) ($_POST['slow_threshold_ms'] ?? 3000)));
        setSetting($pdo, 'request_timeout', (string) max(3, (int) ($_POST['request_timeout'] ?? 10)));
        setSetting($pdo, 'cron_secret', trim($_POST['cron_secret'] ?? ''));
        setSetting($pdo, 'log_retention_days', (string) max(30, min(365, (int) ($_POST['log_retention_days'] ?? 90))));
        setSetting($pdo, 'status_page_title', trim($_POST['status_page_title'] ?? APP_NAME . ' Status'));
        setFlash('success', 'Settings saved.');
        redirect(appUrl('admin/settings.php'));
    }

    if ($action === 'test') {
        $ok = sendTelegram($pdo, "🟢 <b>UptimeGuard test</b>\nTelegram alerts are working.\nTime: " . date('Y-m-d H:i:s'));
        $testResult = $ok;
        setFlash($ok ? 'success' : 'error', $ok ? 'Test message sent to Telegram.' : 'Failed to send. Check bot token, chat ID, and that you started the bot.');
        redirect(appUrl('admin/settings.php'));
    }

    if ($action === 'chats') {
        $token = trim($_POST['telegram_bot_token'] ?? getSetting($pdo, 'telegram_bot_token', ''));
        if ($token === '') {
            setFlash('error', 'Paste your bot token first, then click Auto find Chat ID.');
            redirect(appUrl('admin/settings.php'));
        }

        setSetting($pdo, 'telegram_bot_token', $token);
        $result = fetchTelegramUpdates($token);

        if (!$result['ok']) {
            setFlash('error', 'Telegram error: ' . ($result['error'] ?: 'Invalid bot token'));
            redirect(appUrl('admin/settings.php'));
        }

        $chats = $result['chats'];
        $best = pickBestTelegramChat($chats);

        if (!$best) {
            setFlash('error', 'No chat found. Open Telegram, send /start to your bot, then click Auto find Chat ID again.');
            redirect(appUrl('admin/settings.php'));
        }

        setSetting($pdo, 'telegram_chat_id', $best['id']);
        $_SESSION['telegram_chats'] = $chats;
        $botName = $result['bot']['username'] ?? 'bot';
        $who = trim($best['name'] !== '' ? $best['name'] : $best['id']);
        $extra = count($chats) > 1 ? ' If this is the wrong chat, pick another below.' : '';
        setFlash('success', 'Chat ID found and saved: ' . $best['id'] . ' (' . $who . ') via @' . $botName . '.' . $extra);
        redirect(appUrl('admin/settings.php'));
    }

    if ($action === 'use_chat') {
        $chosen = trim($_POST['chat_id'] ?? '');
        if ($chosen === '') {
            setFlash('error', 'Missing chat ID.');
        } else {
            setSetting($pdo, 'telegram_chat_id', $chosen);
            setFlash('success', 'Chat ID saved: ' . $chosen);
        }
        redirect(appUrl('admin/settings.php'));
    }

    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $stmt = $pdo->prepare('SELECT password FROM admins WHERE id = ?');
        $stmt->execute([(int) $_SESSION['admin_id']]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($current, $row['password'])) {
            setFlash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 6) {
            setFlash('error', 'New password must be at least 6 characters.');
        } elseif ($new !== $confirm) {
            setFlash('error', 'New passwords do not match.');
        } else {
            $upd = $pdo->prepare('UPDATE admins SET password = ? WHERE id = ?');
            $upd->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['admin_id']]);
            setFlash('success', 'Password changed.');
        }
        redirect(appUrl('admin/settings.php'));
    }
}

$token = getSetting($pdo, 'telegram_bot_token', '');
$chatId = getSetting($pdo, 'telegram_chat_id', '');
$enabled = getSetting($pdo, 'telegram_enabled', '0') === '1';
$chats = $_SESSION['telegram_chats'] ?? [];
unset($_SESSION['telegram_chats']);
$cronSecret = getSetting($pdo, 'cron_secret', CRON_SECRET);
$cron = cronStatus($pdo);
$cronUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    . appUrl('cron/monitor.php?key=' . urlencode($cronSecret));
$cronPhpCmd = 'php -q "' . dirname(__DIR__) . '/cron/monitor.php"';
$statusUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    . appUrl('status.php');

require dirname(__DIR__) . '/includes/header.php';
?>

<div class="two-col">
    <div class="card">
        <div class="card-head"><h2>Telegram alerts</h2></div>
        <div class="card-body">
            <form method="post">
                <?= csrfField() ?>
                <div class="form-row">
                    <label>
                        <input type="checkbox" name="telegram_enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
                        Enable Telegram alerts
                    </label>
                </div>
                <div class="form-row">
                    <label for="telegram_bot_token">Bot token</label>
                    <input type="text" id="telegram_bot_token" name="telegram_bot_token" value="<?= e($token) ?>" placeholder="123456:ABC-DEF...">
                    <div class="help">Create a bot with @BotFather, then paste the token here.</div>
                </div>
                <div class="form-row">
                    <label for="telegram_chat_id">Chat ID</label>
                    <input type="text" id="telegram_chat_id" name="telegram_chat_id" value="<?= e($chatId) ?>" placeholder="Will fill automatically">
                    <div class="help">Paste the bot token, send <code>/start</code> to your bot in Telegram, then click Auto find Chat ID. The ID is filled in automatically.</div>
                </div>
                <div class="form-grid">
                    <div class="form-row">
                        <label>Default slow threshold (ms)</label>
                        <input type="number" name="slow_threshold_ms" min="200" value="<?= e(getSetting($pdo, 'slow_threshold_ms', '3000')) ?>">
                    </div>
                    <div class="form-row">
                        <label>Default timeout (seconds)</label>
                        <input type="number" name="request_timeout" min="3" max="60" value="<?= e(getSetting($pdo, 'request_timeout', '10')) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <label>Cron secret key</label>
                    <input type="text" name="cron_secret" value="<?= e($cronSecret) ?>">
                </div>
                <div class="form-row">
                    <label>Keep logs for (days)</label>
                    <input type="number" name="log_retention_days" min="30" max="365" value="<?= e(getSetting($pdo, 'log_retention_days', '90')) ?>">
                    <div class="help">Default 90 days (like UptimeRobot). Older logs are deleted automatically after this period.</div>
                </div>
                <div class="form-row">
                    <label>Public status page title</label>
                    <input type="text" name="status_page_title" value="<?= e(getSetting($pdo, 'status_page_title', APP_NAME . ' Status')) ?>">
                    <div class="help">Anyone can view the status page without login.</div>
                </div>
                <div class="actions">
                    <button class="btn" type="submit" name="action" value="save">Save settings</button>
                    <button class="btn btn-ghost" type="submit" name="action" value="chats">Auto find Chat ID</button>
                </div>
            </form>

            <form method="post" class="actions" style="margin-top:12px">
                <?= csrfField() ?>
                <button class="btn btn-success" name="action" value="test">Send test message</button>
            </form>

            <?php if ($chats): ?>
                <p class="small" style="margin-top:14px">More than one chat was found. Current Chat ID is saved. Click Use to switch:</p>
                <table>
                    <thead><tr><th>Chat ID</th><th>Name</th><th>Type</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($chats as $chat): ?>
                        <tr>
                            <td><?= e($chat['id']) ?></td>
                            <td><?= e($chat['name']) ?><?= !empty($chat['username']) ? ' (@' . e($chat['username']) . ')' : '' ?></td>
                            <td><?= e($chat['type']) ?></td>
                            <td>
                                <form method="post">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="use_chat">
                                    <input type="hidden" name="chat_id" value="<?= e($chat['id']) ?>">
                                    <button class="btn btn-sm <?= $chat['id'] === $chatId ? 'btn-success' : 'btn-ghost' ?>" type="submit">
                                        <?= $chat['id'] === $chatId ? 'Selected' : 'Use this' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-head"><h2>Change password</h2></div>
            <div class="card-body">
                <p class="small">Logged in as <strong><?= e($admin['username'] ?? '') ?></strong></p>
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="password">
                    <div class="form-row">
                        <label>Current password</label>
                        <div class="password-wrap">
                            <input type="password" id="current_password" name="current_password" required>
                            <button type="button" class="toggle-pass" data-toggle-password="current_password">Show</button>
                        </div>
                    </div>
                    <div class="form-row">
                        <label>New password</label>
                        <input type="password" name="new_password" required minlength="6">
                    </div>
                    <div class="form-row">
                        <label>Confirm new password</label>
                        <input type="password" name="confirm_password" required minlength="6">
                    </div>
                    <button class="btn" type="submit">Update password</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Public status page</h2></div>
            <div class="card-body">
                <p class="small">Share this link publicly. No login required (like UptimeRobot status page).</p>
                <p class="mono"><a href="<?= e($statusUrl) ?>" target="_blank" rel="noopener"><?= e($statusUrl) ?></a></p>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Auto monitoring (Cron)</h2></div>
            <div class="card-body">
                <p class="small">
                    Status:
                    <span class="badge <?= $cron['active'] ? 'up' : 'down' ?>"><?= e($cron['label']) ?></span>
                    · Last run: <?= $cron['last'] ? e($cron['last']) : 'never' ?>
                </p>
                <p class="small">Add this in cPanel → Cron Jobs, run every <strong>1 minute</strong> (<code>* * * * *</code>):</p>
                <p class="mono"><?= e($cronPhpCmd) ?></p>
                <p class="small" style="margin-top:12px">Or call this URL every minute:</p>
                <p class="mono"><?= e($cronUrl) ?></p>
                <p class="help">Each website is checked when its own interval has passed. Example: interval 5 min = checked every 5 minutes.</p>
            </div>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
