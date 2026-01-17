<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
if (!isset($full_name)) $full_name = $_SESSION['full_name'] ?? 'Người dùng';

// Lấy danh sách sự kiện
$stmt = $pdo->query("SELECT id, title, event_date FROM events ORDER BY event_date DESC");
$events = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" type="image/png" href="/assets/logo_CLB.png">
    <!-- Bootstrap & Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #e8f1fb; }
        .container-attendance {
            background: #fff;
            max-width: 600px;
            margin: 48px auto 24px auto;
            padding: 36px 28px;
            border-radius: 14px;
            box-shadow: 0 4px 24px #3178c615, 0 1.5px 8px #a8c8f088;
        }
        .attendance-title {
            color: #3178c6;
            text-align: center;
            margin-bottom: 24px;
            font-weight: 700;
        }
        .attendance-list li {
            list-style: none;
            margin: 14px 0;
        }
        .attendance-link {
            color: #3178c6;
            font-size: 18px;
            text-decoration: none;
            padding: 12px 16px;
            border-radius: 8px;
            display: inline-block;
            background: #e8f1fb;
            transition: background 0.17s, color 0.17s;
            border: 1.5px solid #a8c8f0;
        }
        .attendance-link:hover {
            background: #a8c8f0;
            color: #1757a6;
        }
        .back-link {
            color: #3178c6;
            text-decoration: none;
        }
        .back-link:hover {
            color: #1757a6;
            text-decoration: underline;
        }
        .logout-link {
            color: #e72c2c;
            float: right;
            text-decoration: none;
        }
        .logout-link:hover { text-decoration: underline;}
        @media (max-width: 600px) {
            .container-attendance { padding: 18px 3vw; }
            .attendance-title { font-size: 1.2em; }
        }
        .topbar { background: #6fa6e3; color: #fff; height: 60px; padding: 0 28px;}
        .logo-area img { height:36px; margin-right:10px; }
        .logo-area span { font-size:20px; font-weight:600;}
        .dropdown-toggle { font-weight: 500; }
    </style>
</head>
<body>
<?php
$pageTitle = "CLB Kỹ năng Đoàn - Hội Trường THPT Lý Thường Kiệt";
$full_name = $_SESSION['full_name'] ?? '';
include '../includes/header.php';
?>
<div class="container-attendance shadow-sm">
    <div class="mb-2 d-flex justify-content-between align-items-center">
        <a href="../dashboard.php" class="back-link"><i class="bi bi-arrow-left-circle"></i> Về Trang chủ</a>
    </div>
    <h2 class="attendance-title"><i class="bi bi-clipboard-check"></i> Chọn sự kiện để điểm danh</h2>
    <ul class="attendance-list px-0">
        <?php if ($events): ?>
            <?php foreach ($events as $event): ?>
            <li>
                <a href="attendance_event.php?event_id=<?= $event['id'] ?>" class="attendance-link">
                    <i class="bi bi-calendar-event me-1"></i>
                    <?= htmlspecialchars($event['title']) ?>
                    <span class="text-muted ms-2" style="font-size:15px;">
                        (<?= htmlspecialchars(date('d/m/Y H:i', strtotime($event['event_date']))) ?>)
                    </span>
                </a>
            </li>
            <?php endforeach; ?>
        <?php else: ?>
            <li class="text-muted">Chưa có sự kiện nào để điểm danh.</li>
        <?php endif; ?>
    </ul>
<?php include '../includes/footer.php'; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>