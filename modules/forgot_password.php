<?php
// Cấu hình email admin nhận yêu cầu quên mật khẩu
$admin_email = 'admin@yourdomain.com';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $role = $_POST['role'] ?? '';

    if ($full_name && $username && $role) {
        // Chuẩn bị nội dung email
        $subject = "Yêu cầu quên mật khẩu từ hệ thống CLB";
        $roles = [
            'admin' => 'Quản trị viên',
            'teacher' => 'Giáo viên/Giảng viên',
            'club_leader' => 'Ban chủ nhiệm',
            'student' => 'Học sinh'
        ];
        $role_label = $roles[$role] ?? $role;
        $body = "Có yêu cầu lấy lại mật khẩu:\n"
              . "Họ và tên: $full_name\n"
              . "Tên đăng nhập: $username\n"
              . "Chức vụ: $role_label\n"
              . "Thời gian: " . date('d/m/Y H:i');
        $headers = "From: no-reply@yourdomain.com\r\n";
        // Gửi mail (hoặc lưu file nếu server chưa cấu hình mail)
        if (mail($admin_email, $subject, $body, $headers)) {
            $success = "Đã gửi yêu cầu thành công! Vui lòng chờ quản trị viên liên hệ lại.";
        } else {
            // Nếu thử nghiệm trên localhost chưa gửi được mail, bạn có thể hiển thị thông tin này:
            $success = "Yêu cầu đã được ghi nhận (demo)!<br>Nội dung gửi:<br>" . nl2br(htmlspecialchars($body));
        }
    } else {
        $error = "Vui lòng điền đầy đủ thông tin!";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quên mật khẩu - Hệ thống điểm danh</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #e8f1fb 0%, #a8c8f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .forgot-box {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 24px rgba(49,120,198,0.13), 0 1.5px 8px #a8c8f088;
            padding: 38px 32px 28px 32px;
            min-width: 320px;
            max-width: 400px;
            width: 100%;
            margin: 20px;
        }
        .forgot-title {
            color: #3178c6;
            font-weight: 700;
            margin-bottom: 20px;
            text-align: center;
        }
        .form-label { color: #3178c6; }
        .form-select, .form-control:focus {
            border-color: #6fa6e3;
            box-shadow: 0 0 0 0.13rem #a8c8f077;
        }
        .btn-forgot {
            background: #6fa6e3;
            color: #fff;
            font-weight: 600;
            border-radius: 8px;
            transition: background 0.2s;
        }
        .btn-forgot:hover {
            background: #3178c6;
        }
        .back-link {
            display: block;
            text-align: right;
            font-size: 14px;
            margin-top: 10px;
            color: #3178c6;
            text-decoration: none;
        }
        .back-link:hover {
            color: #1757a6;
            text-decoration: underline;
        }
        @media (max-width: 600px) {
            .forgot-box { padding: 24px 10px 20px 10px; }
        }
    </style>
</head>
<body>
    <form class="forgot-box" method="post" autocomplete="off">
        <div class="forgot-title">Yêu cầu quên mật khẩu</div>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <div class="mb-3">
            <label class="form-label" for="full_name">Họ và tên</label>
            <input class="form-control" type="text" id="full_name" name="full_name" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="username">Tên đăng nhập</label>
            <input class="form-control" type="text" id="username" name="username" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="role">Chức vụ</label>
            <select class="form-select" id="role" name="role" required>
                <option value="">-- Chọn chức vụ --</option>
                <option value="admin">Quản trị viên</option>
                <option value="teacher">Giáo viên/Giảng viên</option>
                <option value="club_leader">Ban chủ nhiệm</option>
                <option value="student">Học sinh</option>
            </select>
        </div>
        <button class="btn btn-forgot w-100 mt-2" type="submit">Gửi yêu cầu</button>
        <a href="../index.php" class="back-link">Về trang đăng nhập</a>
    </form>
</body>
</html>