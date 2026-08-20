<?php
/**
 * Authentication helpers
 */

function isLoggedIn(): bool
{
    return !empty($_SESSION['admin_id']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        redirect(appUrl('login.php'));
    }
}

function currentAdmin(PDO $pdo): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, username, created_at FROM admins WHERE id = ?');
    $stmt->execute([(int) $_SESSION['admin_id']]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function loginAdmin(PDO $pdo, string $username, string $password): bool
{
    $stmt = $pdo->prepare('SELECT id, username, password FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    return true;
}

function logoutAdmin(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
