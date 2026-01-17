<?php
require_once __DIR__ . '/../config/session.php';
require __DIR__ . '/../includes/db.php';

// Hàm sinh mã PIN random, không trùng
function generateUniquePin($pdo, $length = 6) {
    do {
        $pin = '';
        for ($i = 0; $i < $length; $i++) $pin .= rand(0,9);
        $stmt = $pdo->prepare("SELECT id FROM events WHERE pin = ?");
        $stmt->execute([$pin]);
        $exists = $stmt->fetch();
    } while($exists);
    return $pin;
}

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
// Chỉ cho phép admin, giáo viên, ban chủ nhiệm truy cập
if (!in_array($_SESSION['role'], ['admin', 'teacher', 'club_leader'])) {
    header("Location: ../dashboard.php");
    exit("Bạn không có quyền truy cập chức năng này!");
}

// Xử lý thêm sự kiện
$addMsg = '';
$addMsgType = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_event'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_date = $_POST['event_date'] ?? '';

    if ($title && $event_date) {
        $pin = generateUniquePin($pdo, 6); // Sinh mã PIN 6 số
        $stmt = $pdo->prepare("INSERT INTO events (title, pin, description, event_date, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $pin, $description, $event_date, $_SESSION['user_id']]);
        $addMsg = "Tạo sự kiện thành công! Mã PIN: <b>$pin</b>";
        $addMsgType = "success";
    } else {
        $addMsg = "Vui lòng nhập đủ tiêu đề và ngày diễn ra!";
        $addMsgType = "danger";
    }
}


if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $search = trim($_GET['q'] ?? '');
        $params = [];
        $where = '';
        if ($search !== '') {
            $where = "WHERE (e.title LIKE ? OR e.pin LIKE ? OR e.event_date LIKE ?)";
            $search_like = "%$search%";
            $params = [$search_like, $search_like, $search_like];
        }

        $sql = "SELECT e.id, e.title, e.pin, e.event_date FROM events e $where ORDER BY e.event_date DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'events' => $events]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Không thể tải danh sách sự kiện.']);
    }
    exit;
}
// Xử lý xóa sự kiện
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM attendance WHERE event_id = ?");
    $stmt->execute([$delete_id]);
    $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
    $stmt->execute([$delete_id]);
    $addMsg = "Đã xóa sự kiện!";
    $addMsgType = "success";
}

// Xử lý sửa sự kiện
$editEvent = null;
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$edit_id]);
    $editEvent = $stmt->fetch();
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_event'])) {
    $edit_id = (int)$_POST['edit_id'];
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_date = $_POST['event_date'] ?? '';
    $pin = trim($_POST['pin'] ?? '');

    if ($title && $event_date && $pin) {
        // Kiểm tra mã PIN trùng, loại trừ sự kiện hiện tại
        $stmt = $pdo->prepare("SELECT id FROM events WHERE pin = ? AND id != ?");
        $stmt->execute([$pin, $edit_id]);
        if ($stmt->fetch()) {
            $addMsg = "Mã PIN đã được sử dụng cho sự kiện khác!";
            $addMsgType = "danger";
        } else {
            $stmt = $pdo->prepare("UPDATE events SET title=?, pin=?, description=?, event_date=? WHERE id=?");
            $stmt->execute([$title, $pin, $description, $event_date, $edit_id]);
            $addMsg = "Cập nhật sự kiện thành công!";
            $addMsgType = "success";
            $editEvent = null; // Ẩn form sửa sau khi cập nhật
        }
    } else {
        $addMsg = "Vui lòng nhập đủ tiêu đề, mã PIN và ngày diễn ra!";
        $addMsgType = "danger";
    }
}

// Xử lý tìm kiếm
$search = trim($_GET['q'] ?? '');
$params = [];
$where = '';
if ($search !== '') {
    $where = "WHERE (e.title LIKE ? OR e.pin LIKE ? OR e.event_date LIKE ?)";
    $search_like = "%$search%";
    $params = [$search_like, $search_like, $search_like];
}

// Lấy danh sách sự kiện (có tìm kiếm)
$sql = "SELECT e.*, u.full_name AS creator FROM events e LEFT JOIN users u ON e.created_by = u.id $where ORDER BY event_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>CLB Kỹ năng Đoàn - Hội Trường THPT Lý Thường Kiệt</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="/hethongdiemdanh/assets/logo_CLB.png">

    <!-- Bootstrap 5 & Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #e8f1fb; }
        .event-container {
            background: #fff; 
            max-width: 900px; 
            margin: 40px auto 24px auto; 
            padding: 32px 20px 26px 20px; 
            border-radius: 14px; 
            box-shadow: 0 4px 24px #3178c615, 0 1.5px 8px #a8c8f088;
        }
        .event-title {
            color: #3178c6; 
            font-weight: 700; 
            text-align: center; 
            margin-bottom: 14px;
        }
        .form-label { color: #3178c6; font-weight: 500; }
        .btn-main {
            background: #6fa6e3; 
            color: #fff; 
            font-weight: 600; 
            border-radius: 8px; 
            transition: background 0.2s;
        }
        .btn-main:hover { background: #3178c6;}
        .table thead { background: #a8c8f0; color: #3178c6; }
        .table tbody tr td { vertical-align: middle; }
        .back-link { color: #3178c6; text-decoration: none; }
        .back-link:hover { text-decoration: underline; color: #1757a6;}
        .actions a { margin-right: 8px; }
        @media (max-width: 600px) {
            .event-container { padding: 14px 6px; }
            .event-title { font-size: 1.1em; }
        }
    </style>
</head>
<body>
<?php
$pageTitle = "QUẢN LÝ SỰ KIỆN";
$full_name = $_SESSION['full_name'] ?? '';
include '../includes/header.php';
?>
<div class="event-container shadow-sm">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="../dashboard.php" class="back-link"><i class="bi bi-arrow-left-circle"></i> Về Trang chủ</a>
    </div>
    <h2 class="event-title"><i class="bi bi-calendar-event"></i> Quản lý sự kiện</h2>

    <!-- Form tìm kiếm -->
    <form method="get" class="row g-3 mb-3">
        <div class="col-md-10">
            <input type="text" class="form-control" name="q" value="<?= htmlspecialchars($search) ?>"
                   placeholder="Tìm tên sự kiện, mã PIN hoặc ngày tháng...">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Tìm kiếm</button>
        </div>
    </form>

    <?php if ($addMsg): ?>
        <div class="alert alert-<?= $addMsgType ?> alert-dismissible fade show" role="alert">
            <?= $addMsg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
    <?php endif; ?>

    <!-- FORM SỬA SỰ KIỆN -->
    <?php if ($editEvent): ?>
    <form method="POST" class="row g-3 mb-4">
        <h5 class="mb-2" style="color:#3178c6;">Sửa sự kiện</h5>
        <input type="hidden" name="edit_id" value="<?= $editEvent['id'] ?>">
        <div class="col-md-4">
            <label class="form-label" for="title">Tiêu đề sự kiện</label>
            <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($editEvent['title']) ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="pin">Mã PIN</label>
            <input type="text" class="form-control" id="pin" name="pin" value="<?= htmlspecialchars($editEvent['pin']) ?>" required maxlength="10">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="event_date">Ngày diễn ra</label>
            <input type="datetime-local" class="form-control" id="event_date" name="event_date" value="<?= date('Y-m-d\TH:i', strtotime($editEvent['event_date'])) ?>" required>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="description">Mô tả</label>
            <input type="text" class="form-control" id="description" name="description" value="<?= htmlspecialchars($editEvent['description']) ?>">
        </div>
        <div class="col-12 d-flex justify-content-end">
            <button type="submit" name="edit_event" class="btn btn-main px-4"><i class="bi bi-save"></i> Lưu thay đổi</button>
            <a href="events.php" class="btn btn-outline-secondary ms-2">Hủy</a>
        </div>
    </form>
    <?php else: ?>
    <!-- FORM THÊM SỰ KIỆN (KHÔNG CÓ PIN, PIN RANDOM) -->
    <form method="POST" class="row g-3 mb-4">
        <h5 class="mb-2" style="color:#3178c6;">Thêm sự kiện mới</h5>
        <div class="col-md-5">
            <label class="form-label" for="title">Tiêu đề sự kiện</label>
            <input type="text" class="form-control" id="title" name="title" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="event_date">Ngày diễn ra</label>
            <input type="datetime-local" class="form-control" id="event_date" name="event_date" required>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="description">Mô tả</label>
            <input type="text" class="form-control" id="description" name="description">
        </div>
        <div class="col-12 d-flex justify-content-end">
            <button type="submit" name="add_event" class="btn btn-main px-4"><i class="bi bi-plus-circle"></i> Tạo sự kiện</button>
        </div>
    </form>
    <?php endif; ?>

    <h5 class="mt-4 mb-2" style="color:#3178c6;">Danh sách sự kiện</h5>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th style="min-width:120px;">Sự kiện</th>
                    <th style="min-width:80px;">Mã PIN</th>
                    <th style="min-width:120px;">Mô tả</th>
                    <th style="min-width:160px;">Ngày diễn ra</th>
                    <th style="min-width:110px;">Người tạo</th>
                    <th style="min-width:90px;">Điểm danh</th>
                    <th style="min-width:90px;">Hành động</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($events): ?>
                <?php foreach ($events as $event): ?>
                <tr>
                    <td><?= htmlspecialchars($event['title']) ?></td>
                    <td><?= htmlspecialchars($event['pin'] ?? '') ?></td>
                    <td><?= htmlspecialchars($event['description']) ?></td>
                    <td><?= htmlspecialchars($event['event_date']) ?></td>
                    <td><?= htmlspecialchars($event['creator']) ?></td>
                    <td class="text-center">
                        <a href="attendance_event.php?event_id=<?= $event['id'] ?>" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-clipboard-check"></i> Điểm danh
                        </a>
                        <a href="attendance_view.php?event_id=<?= $event['id'] ?>" class="btn btn-outline-info btn-sm" title="Xem danh sách điểm danh">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                    <td class="actions text-center">
                        <a href="events.php?edit_id=<?= $event['id'] ?>" class="text-primary" title="Sửa"><i class="bi bi-pencil-square"></i></a>
                        <a href="events.php?delete_id=<?= $event['id'] ?>" class="text-danger" onclick="return confirm('Xóa sự kiện này?')" title="Xóa"><i class="bi bi-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" class="text-center text-muted">Chưa có sự kiện nào.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>