<?php
/**
 * Telegram Bot API helper
 */

function telegramEnabled(PDO $pdo): bool
{
    return getSetting($pdo, 'telegram_enabled', '0') === '1'
        && getSetting($pdo, 'telegram_bot_token', '') !== ''
        && getSetting($pdo, 'telegram_chat_id', '') !== '';
}

function sendTelegram(PDO $pdo, string $text): bool
{
    $token  = trim(getSetting($pdo, 'telegram_bot_token', ''));
    $chatId = trim(getSetting($pdo, 'telegram_chat_id', ''));

    if ($token === '' || $chatId === '') {
        return false;
    }

    $url  = 'https://api.telegram.org/bot' . $token . '/sendMessage';
    $payload = http_build_query([
        'chat_id'    => $chatId,
        'text'       => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $err) {
        return false;
    }

    $json = json_decode($raw, true);
    return is_array($json) && !empty($json['ok']);
}

function formatTelegramAlert(array $website, string $type, array $check): string
{
    $name = htmlspecialchars($website['name'], ENT_QUOTES, 'UTF-8');
    $url  = htmlspecialchars($website['url'], ENT_QUOTES, 'UTF-8');
    $rt   = $check['response_time'] !== null ? $check['response_time'] . ' ms' : 'N/A';
    $code = $check['http_code'] ? (string) $check['http_code'] : 'N/A';
    $time = date('Y-m-d H:i:s');

    if ($type === 'down') {
        $title = "ALERT: Website DOWN";
        $icon  = "🔴";
        $status = "DOWN";
    } elseif ($type === 'recovery') {
        $title = "RECOVERY: Website back UP";
        $icon  = "🟢";
        $status = "UP";
    } else {
        $title = "WARNING: Slow response detected";
        $icon  = "🟡";
        $status = "UP (SLOW)";
    }

    $error = '';
    if (!empty($check['error'])) {
        $error = "\nError: " . $check['error'];
    }

    return "{$icon} <b>{$title}</b>\n"
        . "━━━━━━━━━━━━━━\n"
        . "Website: <b>{$name}</b>\n"
        . "URL: {$url}\n"
        . "Status: <b>{$status}</b>\n"
        . "HTTP code: {$code}\n"
        . "Response time: {$rt}\n"
        . "Time: {$time}"
        . $error;
}

function telegramApi(string $token, string $method, array $params = []): array
{
    $url = 'https://api.telegram.org/bot' . $token . '/' . $method;
    if ($params) {
        $url .= '?' . http_build_query($params);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $err) {
        return ['ok' => false, 'description' => $err ?: 'Could not connect to Telegram'];
    }

    $json = json_decode((string) $raw, true);
    return is_array($json) ? $json : ['ok' => false, 'description' => 'Invalid Telegram response'];
}

function fetchTelegramUpdates(string $token): array
{
    $me = telegramApi($token, 'getMe');
    if (empty($me['ok'])) {
        return [
            'ok'    => false,
            'error' => $me['description'] ?? 'Invalid bot token',
            'bot'   => null,
            'chats' => [],
        ];
    }

    $updates = telegramApi($token, 'getUpdates', ['limit' => 100, 'timeout' => 0]);
    if (empty($updates['ok'])) {
        return [
            'ok'    => false,
            'error' => $updates['description'] ?? 'Could not read bot messages',
            'bot'   => $me['result'] ?? null,
            'chats' => [],
        ];
    }

    $chats = [];
    foreach ($updates['result'] as $update) {
        $msg = $update['message']
            ?? $update['edited_message']
            ?? $update['channel_post']
            ?? $update['my_chat_member']
            ?? $update['chat_member']
            ?? null;

        if (!$msg && !empty($update['callback_query']['message'])) {
            $msg = $update['callback_query']['message'];
        }
        if (!$msg || empty($msg['chat']['id'])) {
            continue;
        }

        $chat = $msg['chat'];
        $id = (string) $chat['id'];
        $chats[$id] = [
            'id'   => $id,
            'type' => $chat['type'] ?? 'unknown',
            'name' => $chat['title']
                ?? trim(($chat['first_name'] ?? '') . ' ' . ($chat['last_name'] ?? '')),
            'username' => $chat['username'] ?? '',
        ];
    }

    return [
        'ok'    => true,
        'error' => null,
        'bot'   => $me['result'] ?? null,
        'chats' => array_values($chats),
    ];
}

function pickBestTelegramChat(array $chats): ?array
{
    if (!$chats) {
        return null;
    }

    foreach (array_reverse($chats) as $chat) {
        if (($chat['type'] ?? '') === 'private') {
            return $chat;
        }
    }

    return $chats[count($chats) - 1];
}
