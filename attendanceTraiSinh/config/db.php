<?php
$env = require __DIR__ . '/../../config/env.php';

// Kết nối PDO
$host = $env['db']['host'];
$dbname = $env['db']['dbname'];
$user = $env['db']['user'];
$pass = $env['db']['pass'];
$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
$pdo->exec("SET time_zone = '+07:00'");

/**
 * Kiểm tra đăng nhập: trả về mảng user nếu đúng, trả về false nếu sai
 */
function check_login($username, $password) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        return $user;
    }
    return false;
}
?>