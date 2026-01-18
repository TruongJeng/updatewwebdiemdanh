<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/session.php';
require_once  __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}
// Chỉ cho phép admin, giáo viên, ban chủ nhiệm truy cập
if (!in_array($_SESSION['role'], ['admin', 'teacher', 'club_leader'])) {
    header("Location: ../dashboard.php");
    exit("Bạn không có quyền truy cập chức năng này!");
}

/* ===== ĐÓNG SESSION HIỆN TẠI ===== */
if (isset($_POST['close_session'])) {
    $stmt = $pdo->prepare("
        UPDATE attendance_sessions
        SET is_active = 0, end_time = NOW()
        WHERE is_active = 1
    ");
    $stmt->execute();

    header("Location: ".$_SERVER['PHP_SELF']."?closed=1");
    exit;
}

/* ===== MỞ LẠI SESSION CUỐI (ADMIN) ===== */
if (isset($_POST['open_last']) && $_SESSION['role'] === 'admin') {

    // đóng hết session khác
    $pdo->exec("UPDATE attendance_sessions SET is_active = 0");

    // mở lại session mới nhất
    $stmt = $pdo->prepare("
        UPDATE attendance_sessions
        SET is_active = 1, end_time = NULL
        ORDER BY start_time DESC
        LIMIT 1
    ");
    $stmt->execute();

    header("Location: ".$_SERVER['PHP_SELF']."?opened=1");
    exit;
}


/* ===== SINH PIN ===== */
function generatePin() {
    return strval(rand(100000, 999999));
}

$pin = null;
$type = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['type'])) {

    $type   = $_POST['type'] ?? null;
    $userId = $_SESSION['user_id'];

    if (!in_array($type, ['CHECK_IN', 'CHECK_OUT'])) {
        die('Type không hợp lệ');
    }

    $pin = generatePin();

    try {
        $pdo->beginTransaction();

        if ($type === 'CHECK_IN') {

            $pdo->exec("
                UPDATE attendance_sessions
                SET is_active = 0, end_time = NOW()
                WHERE is_active = 1
            ");

            $stmt = $pdo->prepare("
                INSERT INTO attendance_sessions (pin_code, type, created_by, is_active)
                VALUES (?, ?, ?, 1)
            ");
            $stmt->execute([$pin, 'CHECK_IN', $userId]);

        } else {

            $stmt = $pdo->prepare("
                UPDATE attendance_sessions
                SET type = 'CHECK_OUT',
                    pin_code = ?
                WHERE is_active = 1
                ORDER BY start_time DESC
                LIMIT 1
            ");
        $stmt->execute([$pin]);
        }

        $pdo->commit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die('Lỗi: ' . $e->getMessage());
    }
}

/* ===== XOÁ PHIÊN ĐIỂM DANH ===== */
if (isset($_GET['delete_session']) && $_SESSION['role'] === 'admin') {

    $sessionId = (int)$_GET['delete_session'];

    try {
        $pdo->beginTransaction();

        // Kiểm tra phiên có đang active không
        $stmt = $pdo->prepare("
            SELECT is_active 
            FROM attendance_sessions 
            WHERE id = ?
        ");
        $stmt->execute([$sessionId]);
        $s = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$s) {
            throw new Exception("Phiên không tồn tại");
        }

        if ((int)$s['is_active'] === 1) {
            throw new Exception("Không thể xoá phiên đang hoạt động");
        }

        // Xoá log điểm danh
        $stmt = $pdo->prepare("
            DELETE FROM attendance_logs 
            WHERE session_id = ?
        ");
        $stmt->execute([$sessionId]);

        // Xoá phiên
        $stmt = $pdo->prepare("
            DELETE FROM attendance_sessions 
            WHERE id = ?
        ");
        $stmt->execute([$sessionId]);

        $pdo->commit();
        header("Location: ".$_SERVER['PHP_SELF']."?deleted=1");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Lỗi xoá phiên: ".$e->getMessage());
    }
}
$sessions = $pdo->query("
    SELECT 
        s.id,
        s.pin_code,
        s.type,
        s.is_active,
        s.start_time,
        s.end_time,
        u.full_name AS created_by
    FROM attendance_sessions s
    JOIN users u ON s.created_by = u.id
    ORDER BY s.start_time DESC
")->fetchAll(PDO::FETCH_ASSOC);


/* ===== XOÁ TẤT CẢ SESSION ===== */
if (isset($_POST['delete_all_sessions']) && $_SESSION['role'] === 'admin') {

    // đảm bảo không còn session đang mở
    $pdo->exec("UPDATE attendance_sessions SET is_active = 0");

    // xoá log trước
    $pdo->exec("DELETE FROM attendance_logs");

    // xoá session
    $pdo->exec("DELETE FROM attendance_sessions");

    header("Location: ".$_SERVER['PHP_SELF']."?deleted_all=1");
    exit;
}

?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TẠO PIN</title>

<!-- Bootstrap + Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link rel="icon" type="image/png" href="/hethongdiemdanh/assets/logo_CLB.png">
<style>
:root{
    --primary:#3178c6;
    --bg:#f4faff;
    --card:#ffffff;
    --green:#2ecc71;
    --red:#e74c3c;
}

body{
    background:var(--bg);
    font-family:system-ui,-apple-system,BlinkMacSystemFont;
}

/* HEADER */
.header{
    background:linear-gradient(90deg,#3178c6,#6fa6e3);
    color:#fff;
    padding:14px 18px;
    font-size:18px;
    font-weight:700;
    display:flex;
    align-items:center;
    gap:10px;
}

/* CARD */
.pin-card{
    background:var(--card);
    border-radius:16px;
    padding:22px 20px;
    box-shadow:0 4px 18px #3178c61a;
    max-width:420px;
    margin:auto;
}

/* PIN DISPLAY */
.pin-box{
    font-size:38px;
    font-weight:800;
    letter-spacing:10px;
    text-align:center;
    padding:16px 10px;
    border-radius:14px;
    margin-top:16px;
}

.pin-in{
    background:#eafaf1;
    color:var(--green);
}

.pin-out{
    background:#fdecea;
    color:var(--red);
}

/* FOOTER */
.footer{
    text-align:center;
    font-size:13px;
    color:#666;
    margin-top:20px;
}
</style>
</head>

<body>
<?php
$pageTitle = "Tạo Pin Điểm Danh";
$full_name = $_SESSION['full_name'] ?? '';
include __DIR__ . '/../config/header.php';
?>

<div class="container py-4">

    <div class="pin-card">

        <form method="post">
            <label class="form-label fw-semibold">
                <i class="bi bi-toggle-on"></i> Chọn loại điểm danh
            </label>

            <select name="type" class="form-select mb-3" required>
                <option value="CHECK_IN" <?= $type === 'CHECK_IN' ? 'selected' : '' ?>>
                    CHECK IN (Vào trại)
                </option>
                <option value="CHECK_OUT" <?= $type === 'CHECK_OUT' ? 'selected' : '' ?>>
                    CHECK OUT (Rời trại)
                </option>
            </select>

            <button type="submit" class="btn btn-primary w-100 fw-semibold">
                <i class="bi bi-plus-circle"></i> Tạo PIN mới
            </button>
        </form>

        <?php if ($pin): ?>
            <div class="text-center mt-4">
                <div class="fw-semibold text-muted">
                    PIN hiện tại (<?= $type ?>)
                </div>

                <div class="pin-box <?= $type === 'CHECK_IN' ? 'pin-in' : 'pin-out' ?>">
                    <?= htmlspecialchars($pin) ?>
                </div>

                <div class="text-muted mt-2" style="font-size:13px;">
                    Cung cấp PIN này cho BTC để bắt đầu điểm danh
                </div>
            </div>
        <?php endif; ?>

        <div class="d-flex gap-2 mt-3">
            <form method="post" class="flex-fill">
                <button name="close_session"
                        class="btn btn-warning w-100"
                        onclick="return confirm('Đóng phiên điểm danh hiện tại?')">
                    <i class="bi bi-lock"></i> Đóng phiên hiện tại
                </button>
            </form>

            <?php if ($_SESSION['role'] === 'admin'): ?>
            <form method="post" class="flex-fill">
                <button name="open_last"
                        class="btn btn-success w-100"
                        onclick="return confirm('Mở lại phiên gần nhất?')">
                    <i class="bi bi-unlock"></i> Mở lại phiên
                </button>
            </form>
            <?php endif; ?>
        </div>



    </div>
<div class="card mt-4">
  <div class="card-body">
    <h5 class="mb-3">
      <i class="bi bi-clock-history"></i> Lịch sử phiên điểm danh
    </h5>

    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <th>ID</th>
          <th>PIN</th>
          <th>Loại</th>
          <th>Trạng thái</th>
          <th>Người tạo</th>
          <th>Thời gian</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sessions as $s): ?>
        <tr>
          <td><?= $s['id'] ?></td>

          <td class="fw-bold">
            <?= $s['pin_code'] ?>
          </td>

          <td>
            <span class="badge <?= $s['type']==='CHECK_IN'?'bg-success':'bg-danger' ?>">
              <?= $s['type']==='CHECK_IN'?'CHECK IN':'CHECK OUT' ?>
            </span>
          </td>

          <td>
            <?= $s['is_active']
              ? '<span class="badge bg-primary">Đang mở</span>'
              : '<span class="badge bg-secondary">Đã đóng</span>' ?>
          </td>

          <td><?= htmlspecialchars($s['created_by']) ?></td>

        <td>
            <?= date('H:i d/m/Y', strtotime($s['start_time'])) ?>
            <?php if ($s['end_time']): ?>
                <br>
                <small class="text-muted">
                    → <?= date('H:i d/m/Y', strtotime($s['end_time'])) ?>
                </small>
            <?php endif; ?>
        </td>


          <td class="text-end">
            <?php if (!$s['is_active'] && $_SESSION['role']==='admin'): ?>
              <a href="?delete_session=<?= $s['id'] ?>"
                 onclick="return confirm('Xoá vĩnh viễn phiên điểm danh này?')"
                 class="btn btn-sm btn-danger">
                 <i class="bi bi-trash"></i>
              </a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($_SESSION['role'] === 'admin'): ?>
<form method="post" class="mt-3 text-end">
    <button name="delete_all_sessions"
            class="btn btn-danger"
            onclick="return confirm('⚠️ XOÁ TOÀN BỘ phiên + lịch sử điểm danh?\nKhông thể khôi phục!')">
        <i class="bi bi-trash3"></i> Xoá tất cả phiên
    </button>
</form>
<?php endif; ?>

<?php include __DIR__ . '/../config/footer.php'; ?>


</div>

</body>
</html>
