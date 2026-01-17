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

/* ===== SINH PIN ===== */
function generatePin() {
    return strval(rand(100000, 999999));
}

$pin = null;
$type = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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
            ");
            $stmt->execute([$pin]);
        }

        $pdo->commit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die('Lỗi: ' . $e->getMessage());
    }
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

    </div>

<?php include __DIR__ . '/../config/footer.php'; ?>


</div>

</body>
</html>
