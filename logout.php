<?php
require_once __DIR__ . '/config/session.php';

// Xóa toàn bộ biến session
$_SESSION = [];

// Xóa session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hủy session
session_destroy();

// Chuyển về trang đăng nhập
header("Location: index.php");
exit();