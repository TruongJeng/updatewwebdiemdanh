<?php
// Kết nối PDO
$host = 'localhost';
$dbname = 'clbupdate1812026';
$user = 'root'; // đổi thành tài khoản thật của bạn
$pass = '';     // đổi thành mật khẩu thật của bạn
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