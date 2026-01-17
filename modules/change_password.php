<?php
session_start();
require '../includes/db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); exit();
}

$error = '';
$msg = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $newpass = $_POST['newpass'] ?? '';
    $renewpass = $_POST['renewpass'] ?? '';
    if (!$newpass || !$renewpass) {
        $error = "Vui lòng nhập đầy đủ thông tin!";
    } elseif ($newpass !== $renewpass) {
        $error = "Mật khẩu nhập lại không khớp!";
    } elseif (strlen($newpass) < 5) {
        $error = "Mật khẩu phải từ 5 ký tự!";
    } else {
        $hash = password_hash($newpass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?");
        $stmt->execute([$hash, $_SESSION['user_id']]);
        $msg = "Đổi mật khẩu thành công! Bạn sẽ được chuyển về trang chính.";
        header("refresh:2;url=dashboard.php");
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Đổi mật khẩu</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background:#e8f1fb;">
    <div class="container" style="max-width:400px;margin-top:60px;">
        <div class="card shadow-sm">
            <div class="card-body">
                <h4 class="mb-3" style="color:#3178c6;">Đổi mật khẩu</h4>
                <?php if($error): ?>
                    <div class="alert alert-danger"><?=htmlspecialchars($error)?></div>
                <?php endif; ?>
                <?php if($msg): ?>
                    <div class="alert alert-success"><?=htmlspecialchars($msg)?></div>
                <?php endif; ?>
                <?php if(!$msg): ?>
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Mật khẩu mới</label>
                        <input type="password" name="newpass" class="form-control" required minlength="5">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nhập lại mật khẩu mới</label>
                        <input type="password" name="renewpass" class="form-control" required minlength="5">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Đổi mật khẩu</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>