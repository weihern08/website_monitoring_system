<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'synergy1_weihern_website_monitoring_system');
define('DB_USER', 'synergy1_shaoxi');
define('DB_PASS', 'p07e&61#5e9^c]Y}');
define('DB_CHARSET', 'utf8mb4');

function getPDO(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}
