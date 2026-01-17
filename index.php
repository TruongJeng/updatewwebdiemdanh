<?php
require_once __DIR__ . '/config/session.php';
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

require 'includes/db.php';

$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"] ?? '';
    $password = $_POST["password"] ?? '';

    // Gọi hàm check_login từ db.php
    $user = check_login($username, $password);

    if ($user) {
        // Đăng nhập thành công
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['first_login'] = $user['first_login']; // Thêm dòng này

        if ($user['first_login']) {
            header("Location: password/new_password.php"); // Chuyển đến trang đổi mật khẩu lần đầu
            exit();
        } else {
            header("Location: dashboard.php");
            exit();
        }
    } else {
        $error = "Sai tên đăng nhập hoặc mật khẩu!";
    }
}
if(isset($_GET['timeout'])): ?>
    <div class="alert alert-warning">
        Bạn đã bị đăng xuất do không hoạt động quá lâu. Vui lòng đăng nhập lại.
    </div>
<?php endif; 
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>CLB KỸ NĂNG ĐOÀN - HỘI TRƯỜNG THPT LÝ THƯỜNG KIỆT</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="/hethongdiemdanh/assets/logo_CLB.png">
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #e8f1fb 0%, #a8c8f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-box {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 24px rgba(49,120,198,0.13), 0 1.5px 8px #a8c8f088;
            padding: 38px 32px 28px 32px;
            min-width: 320px;
            max-width: 380px;
            width: 100%;
            margin: 20px;
        }
        .login-title {
            color: #3178c6;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
            text-align: center;
        }
        .login-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
        }
        .login-logo img {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            background: #e8f1fb;
            object-fit: cover;
            border: 2.5px solid #a8c8f0;
            box-shadow: 0 2px 10px #a8c8f033;
        }
        .form-label {
            color: #3178c6;
        }
        .form-control:focus {
            border-color: #6fa6e3;
            box-shadow: 0 0 0 0.2rem #a8c8f077;
        }
        .btn-login {
            background: #6fa6e3;
            color: #fff;
            font-weight: 600;
            border-radius: 8px;
            transition: background 0.2s;
            margin-top: 6px;
        }
        .btn-login:hover, .btn-login:focus {
            background: #3178c6;
        }
        .login-box .alert {
            font-size: 15px;
            margin-bottom: 12px;
        }
        .form-password-toggle {
            position: relative;
        }
        .toggle-password {
            position: absolute;
            top: 8px;
            right: 12px;
            z-index: 10;
            background: none;
            border: none;
            color: #6fa6e3;
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0;
        }
        .forgot-link {
            display: block;
            text-align: right;
            font-size: 14px;
            margin-top: 6px;
            color: #3178c6;
            text-decoration: none;
            transition: color 0.15s;
        }
        .forgot-link:hover {
            color: #1757a6;
            text-decoration: underline;
        }
        @media (max-width: 600px) {
            .login-box {
                padding: 26px 12px 20px 12px;
            }
            .login-logo img { width: 54px; height: 54px; }
        }
    </style>
</head>
<body>
    <form class="login-box" method="post" autocomplete="off">
        <div class="login-logo">
            <!-- Thay đổi src logo bên dưới nếu muốn logo khác -->
            <img src="/hethongdiemdanh/assets/logo_CLB.png" alt="Logo CLB">
        </div>
        <div class="login-title mb-2">Đăng nhập hệ thống</div>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <div class="mb-3">
            <label class="form-label" for="username">Tên đăng nhập</label>
            <input class="form-control" type="text" id="username" name="username" autofocus required autocomplete="username">
        </div>
        <div class="mb-2 form-password-toggle">
            <label class="form-label" for="password">Mật khẩu</label>
            <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password">
            <button class="toggle-password" type="button" tabindex="-1" onclick="togglePassword()">
                <i class="bi bi-eye-slash" id="togglePassIcon"></i>
            </button>
        </div>
        <button class="btn btn-login w-100 mt-2" type="submit">Đăng nhập</button>
        <a href="modules/forgot_password.php" class="forgot-link">Quên mật khẩu?</a>
    </form>
    <script>
    // Hiện/ẩn mật khẩu
    function togglePassword() {
        const passInput = document.getElementById('password');
        const icon = document.getElementById('togglePassIcon');
        if (passInput.type === "password") {
            passInput.type = "text";
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        } else {
            passInput.type = "password";
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        }
    }
    </script>
</body>
</html>