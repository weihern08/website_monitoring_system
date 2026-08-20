<?php
/**
 * Shared helpers
 */

function isInstalled(): bool
{
    return file_exists(dirname(__DIR__) . '/config/installed.lock');
}

function appUrl(string $path = ''): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $root = dirname($script);

    // If we are inside /admin or /cron, go up one level
    $base = basename($root);
    if (in_array($base, ['admin', 'cron', 'install'], true)) {
        $root = dirname($root);
    }

    $root = rtrim($root, '/');
    if ($root === '' || $root === '\\' || $root === '.') {
        $root = '';
    }

    return $root . '/' . ltrim($path, '/');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}

function getSetting(PDO $pdo, string $key, string $default = ''): string
{
    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? (string) $row['setting_value'] : $default;
}

function setSetting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}

function timeAgo(?string $datetime): string
{
    if (!$datetime) {
        return 'Never';
    }

    $ts = strtotime($datetime);
    if ($ts === false) {
        return 'Never';
    }

    $diff = time() - $ts;
    if ($diff < 0) {
        return date('Y-m-d H:i', $ts);
    }
    if ($diff < 10) {
        return 'just now';
    }
    if ($diff < 60) {
        return $diff . 's ago';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . 'm ago';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . 'h ago';
    }
    if ($diff < 604800) {
        return floor($diff / 86400) . 'd ago';
    }

    return date('Y-m-d H:i', $ts);
}

function statusLabel(string $status, bool $paused = false, bool $slow = false): string
{
    if ($paused) {
        return 'Paused';
    }
    if ($status === 'up' && $slow) {
        return 'Slow';
    }
    if ($status === 'up') {
        return 'Up';
    }
    if ($status === 'down') {
        return 'Down';
    }
    return 'Not checked';
}

function statusClass(string $status, bool $paused = false, bool $slow = false): string
{
    if ($paused) {
        return 'paused';
    }
    if ($status === 'up' && $slow) {
        return 'slow';
    }
    if ($status === 'up') {
        return 'up';
    }
    if ($status === 'down') {
        return 'down';
    }
    return 'unknown';
}

function logRetentionDays(PDO $pdo): int
{
    return max(30, min(365, (int) getSetting($pdo, 'log_retention_days', '90')));
}

function websiteUptime(PDO $pdo, int $websiteId, int $days = 7): ?float
{
    $stmt = $pdo->prepare(
        'SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = "up" THEN 1 ELSE 0 END) AS up_count
         FROM logs
         WHERE website_id = ?
           AND checked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)'
    );
    $stmt->execute([$websiteId, $days]);
    $row = $stmt->fetch();

    if (!$row || (int) $row['total'] === 0) {
        return null;
    }

    return round(((int) $row['up_count'] / (int) $row['total']) * 100, 2);
}

function uptimeBar(PDO $pdo, int $websiteId, int $days = 1, int $segments = 0): array
{
    if ($segments <= 0) {
        $segments = $days === 1 ? 24 : min($days, 90);
    }

    $stmt = $pdo->prepare(
        'SELECT status, checked_at
         FROM logs
         WHERE website_id = ?
           AND checked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
         ORDER BY checked_at ASC'
    );
    $stmt->execute([$websiteId, $days]);
    $logs = $stmt->fetchAll();

    $bar = array_fill(0, $segments, 'empty');
    if (!$logs) {
        return $bar;
    }

    $periodSeconds = $days * 86400;
    $bucketSize = max(1, $periodSeconds / $segments);
    $now = time();
    $start = $now - $periodSeconds;

    foreach ($logs as $log) {
        $ts = strtotime($log['checked_at']);
        if ($ts === false) {
            continue;
        }
        $index = (int) floor(($ts - $start) / $bucketSize);
        if ($index < 0) {
            continue;
        }
        if ($index >= $segments) {
            $index = $segments - 1;
        }

        // DOWN always wins in a bucket so red shows on the bar
        if ($log['status'] === 'down') {
            $bar[$index] = 'down';
        } elseif ($bar[$index] !== 'down') {
            $bar[$index] = 'up';
        }
    }

    return $bar;
}

/**
 * HTTP availability check.
 * UP = reachable with HTTP 200-399
 * DOWN = timeout, connection error, or 400+
 */
function checkWebsiteUrl(string $url, int $timeout = 10): array
{
    $started = microtime(true);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_USERAGENT      => 'UptimeGuard Monitor/' . APP_VERSION,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HEADER         => true,
        CURLOPT_NOBODY         => false,
    ]);

    $response = curl_exec($ch);
    $errno    = curl_errno($ch);
    $error    = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $elapsedMs = (int) round((microtime(true) - $started) * 1000);

    $up = ($response !== false && $errno === 0 && $httpCode >= 200 && $httpCode < 400);

    return [
        'up'            => $up,
        'status'        => $up ? 'up' : 'down',
        'response_time' => $elapsedMs,
        'http_code'     => $httpCode ?: null,
        'error'         => $up ? null : ($error ?: ('HTTP ' . ($httpCode ?: 'timeout'))),
    ];
}

function recordCheck(PDO $pdo, array $website, array $check, bool $sendAlerts = true): string
{
    $newStatus = $check['status'];
    $oldStatus = $website['status'] ?? 'unknown';
    $threshold = (int) ($website['slow_threshold_ms'] ?: getSetting($pdo, 'slow_threshold_ms', '3000'));
    $isSlow    = $newStatus === 'up' && $check['response_time'] !== null && $check['response_time'] > $threshold;
    $wasSlow   = (int) ($website['is_slow'] ?? 0) === 1;
    $alertType = '';

    $stmt = $pdo->prepare(
        'INSERT INTO logs (website_id, status, is_slow, response_time, http_code, error_message, checked_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $website['id'],
        $newStatus,
        $isSlow ? 1 : 0,
        $check['response_time'],
        $check['http_code'],
        $check['error'],
    ]);

    $statusChanged = ($oldStatus !== 'unknown' && $oldStatus !== $newStatus)
        || ($oldStatus === 'unknown' && $newStatus === 'down');

    if ($statusChanged && $newStatus === 'down') {
        $alertType = 'down';
    } elseif ($statusChanged && $newStatus === 'up') {
        $alertType = 'recovery';
    } elseif ($newStatus === 'up' && $isSlow && !$wasSlow) {
        $alertType = 'slow';
    }

    $statusSince = $website['status_since'] ?? null;
    if ($oldStatus !== $newStatus || !$statusSince) {
        $statusSince = date('Y-m-d H:i:s');
    }

    $upd = $pdo->prepare(
        'UPDATE websites
         SET status = ?, is_slow = ?, response_time = ?, http_code = ?, last_error = ?,
             last_checked = NOW(), status_since = ?, updated_at = NOW()
         WHERE id = ?'
    );
    $upd->execute([
        $newStatus,
        $isSlow ? 1 : 0,
        $check['response_time'],
        $check['http_code'],
        $check['error'],
        $statusSince,
        $website['id'],
    ]);

    if ($alertType !== '' && $sendAlerts) {
        $message = formatTelegramAlert($website, $alertType, $check);
        $sent = false;
        if (telegramEnabled($pdo)) {
            $sent = sendTelegram($pdo, $message);
        }

        $ins = $pdo->prepare(
            'INSERT INTO alerts (website_id, alert_type, status, response_time, message, sent, sent_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $ins->execute([
            $website['id'],
            $alertType,
            $newStatus,
            $check['response_time'],
            $message,
            $sent ? 1 : 0,
        ]);
    }

    return $alertType;
}

function websitesDueForCheck(PDO $pdo): array
{
    $sql = 'SELECT * FROM websites
            WHERE is_paused = 0
              AND (
                    last_checked IS NULL
                    OR last_checked <= DATE_SUB(NOW(), INTERVAL interval_minutes MINUTE)
                  )
            ORDER BY last_checked ASC';
    return $pdo->query($sql)->fetchAll();
}

function runMonitorCycle(PDO $pdo): array
{
    $due = websitesDueForCheck($pdo);
    $checked = 0;
    $alerts = 0;

    foreach ($due as $site) {
        $timeout = (int) ($site['timeout_seconds'] ?: getSetting($pdo, 'request_timeout', '10'));
        $check = checkWebsiteUrl($site['url'], max(3, $timeout));
        $alert = recordCheck($pdo, $site, $check, true);
        $checked++;
        if ($alert !== '') {
            $alerts++;
        }
    }

    $days = logRetentionDays($pdo);
    if ($days > 0) {
        $pdo->prepare('DELETE FROM logs WHERE checked_at < DATE_SUB(NOW(), INTERVAL ? DAY)')->execute([$days]);
        $pdo->prepare('DELETE FROM alerts WHERE sent_at < DATE_SUB(NOW(), INTERVAL ? DAY)')->execute([$days]);
    }

    cronHeartbeat($pdo);

    return ['checked' => $checked, 'alerts' => $alerts, 'due' => count($due)];
}

function cronHeartbeat(PDO $pdo): void
{
    setSetting($pdo, 'last_cron_run', date('Y-m-d H:i:s'));
}

function cronStatus(PDO $pdo): array
{
    $last = getSetting($pdo, 'last_cron_run', '');
    if ($last === '') {
        return ['active' => false, 'last' => null, 'label' => 'Not running yet'];
    }

    $ts = strtotime($last);
    $active = $ts !== false && (time() - $ts) <= 180;

    return [
        'active' => $active,
        'last'   => $last,
        'label'  => $active ? 'Auto monitoring active' : 'Auto monitoring stopped',
    ];
}

function pagination(int $total, int $page, int $perPage, string $baseUrl): string
{
    $pages = max(1, (int) ceil($total / $perPage));
    if ($pages <= 1) {
        return '';
    }

    $html = '<nav class="pagination">';
    for ($i = 1; $i <= $pages; $i++) {
        $sep = strpos($baseUrl, '?') !== false ? '&' : '?';
        $cls = $i === $page ? 'active' : '';
        $html .= '<a class="' . $cls . '" href="' . e($baseUrl . $sep . 'page=' . $i) . '">' . $i . '</a>';
    }
    $html .= '</nav>';
    return $html;
}

function validHttpUrl(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $scheme = parse_url($url, PHP_URL_SCHEME);
    return in_array(strtolower((string) $scheme), ['http', 'https'], true);
}
