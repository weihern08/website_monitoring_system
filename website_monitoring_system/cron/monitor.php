<?php
/**
 * Automatic monitoring engine.
 * Run every minute via cron / Windows Task Scheduler.
 *
 * CLI:  php cron/monitor.php
 * Web:  /cron/monitor.php?key=YOUR_SECRET
 */

require_once dirname(__DIR__) . '/includes/init.php';

$cli = (PHP_SAPI === 'cli');

if (!$cli) {
    $provided = $_GET['key'] ?? '';
    try {
        $secret = getSetting(getPDO(), 'cron_secret', CRON_SECRET);
    } catch (Throwable $e) {
        $secret = CRON_SECRET;
    }
    if ($secret === '' || !hash_equals((string) $secret, (string) $provided)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Forbidden. Append ?key=YOUR_CRON_SECRET\n";
        exit;
    }
}

try {
    $pdo = getPDO();
    $result = runMonitorCycle($pdo);
    $line = date('c') . ' checked=' . $result['checked'] . ' alerts=' . $result['alerts'] . PHP_EOL;

    if ($cli) {
        echo $line;
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'      => true,
            'checked' => $result['checked'],
            'alerts'  => $result['alerts'],
            'time'    => date('c'),
        ]);
    }
} catch (Throwable $e) {
    if ($cli) {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
