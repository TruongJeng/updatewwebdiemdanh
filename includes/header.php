<?php
if (!isset($pageTitle)) $pageTitle = 'CLB Kỹ Năng Đoàn Hội Trường THPT Lý Thường Kiệt';
if (!isset($full_name)) $full_name = $_SESSION['full_name'] ?? 'Người dùng';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="/hethongdiemdanh/assets/logo_CLB.png">
    <!-- Bootstrap & Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #e8f1fb;padding-top: 60px; }
        .topbar {
        background: #6fa6e3;
        color: #fff;
        height: 60px;
        position: fixed;      /* QUAN TRỌNG */
        top: 0;
        left: 0;
        right: 0;
        z-index: 1200;
        padding: 0 18px;
        }

        .logo-area img { height:36px; margin-right:10px; }
        .logo-area span { font-size:20px; font-weight:600; }
        .logo-area a { color: #fff; text-decoration: none; display: flex; align-items: center; }
        .dropdown-toggle { font-weight: 500; }
        @media (max-width: 900px) {
    	.logo-area img { height:30px; }
    	.logo-area span { font-size:16px; }
		}
		@media (max-width: 600px) {
    	.logo-area span { font-size:13px; }
		}
        .dropdown-menu {
   		z-index: 2000 !important;
		}
    </style>
</head>
<body>
<div class="topbar d-flex align-items-center justify-content-between">
  <div class="logo-area">
    <a href="../dashboard.php">
      <img src="/hethongdiemdanh/assets/logo_CLB.png" alt="Logo">
      <span><?= htmlspecialchars($pageTitle) ?></span>
    </a>
  </div>
  <div>
    <div class="dropdown">
      <a class="d-flex align-items-center text-white text-decoration-none dropdown-toggle"
         href="#" id="dropdownUser" data-bs-toggle="dropdown" aria-expanded="false"
         style="font-weight:500;">
        <i class="bi bi-person-circle" style="font-size:28px;margin-right:7px;"></i>
        <?= htmlspecialchars($full_name) ?>
      </a>
      <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownUser">
        <li>
          <a class="dropdown-item" href="/password/change_password.php">
            <i class="bi bi-arrow-repeat me-2"></i>Đổi mật khẩu
          </a>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
          <a class="dropdown-item text-danger" href="/hethongdiemdanh/logout.php">
            <i class="bi bi-box-arrow-right me-2"></i>Đăng xuất
          </a>
        </li>
      </ul>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</div>
<!-- Nội dung trang bắt đầu -->