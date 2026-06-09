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

    // Xóa session PHP khi đóng phiên
    unset($_SESSION['attendance_session_id']);
    unset($_SESSION['attendance_type']);
    unset($_SESSION['scanner_pin']);

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

    // Đồng bộ session PHP với phiên vừa mở lại
    $reopened = $pdo->query("SELECT id, type, pin_code FROM attendance_sessions WHERE is_active = 1 ORDER BY start_time DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($reopened) {
        $_SESSION['attendance_session_id'] = $reopened['id'];
        $_SESSION['attendance_type']       = $reopened['type'];
        $_SESSION['scanner_pin']           = $reopened['pin_code'];
    }

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

            // Lưu session ID vào PHP session để các trang thống kê có thể truy cập
            $_SESSION['attendance_session_id'] = $pdo->lastInsertId();
            $_SESSION['attendance_type']       = 'CHECK_IN';
            $_SESSION['scanner_pin']           = $pin;

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

            // Cập nhật session PHP
            $activeStmt = $pdo->query("SELECT id FROM attendance_sessions WHERE is_active = 1 ORDER BY start_time DESC LIMIT 1");
            $activeSession = $activeStmt->fetch(PDO::FETCH_ASSOC);
            if ($activeSession) {
                $_SESSION['attendance_session_id'] = $activeSession['id'];
                $_SESSION['attendance_type']       = 'CHECK_OUT';
                $_SESSION['scanner_pin']           = $pin;
            }
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

?>
<?php
$pageTitle = "Tạo Pin Điểm Danh";
$full_name = $_SESSION['full_name'] ?? '';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="ml-0 lg:ml-64 pt-4 min-h-screen bg-slate-50/50 transition-all duration-300 ease-in-out p-4 sm:p-6 lg:p-8">
    <div class="max-w-4xl mx-auto pb-12">
        
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-primary-100 text-primary-600 flex items-center justify-center shadow-sm">
                    <i class="bi bi-key text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">TẠO PIN</h2>
                    <p class="text-sm font-medium text-slate-500 mt-1">Khởi tạo phiên điểm danh mới cho trại sinh</p>
                </div>
            </div>
            <a href="attendance_list.php" class="flex items-center gap-2 bg-white hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg font-semibold transition-all shadow-sm border border-slate-200 text-sm">
                <i class="bi bi-list-check text-primary-600"></i> Xem điểm danh
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Cột trái: Form tạo PIN -->
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white rounded-2xl p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 relative overflow-hidden">
                    <form method="post" class="relative z-10">
                        <label class="block text-sm font-bold text-slate-700 mb-3 flex items-center gap-2">
                            <i class="bi bi-toggle-on text-primary-500"></i> Chọn loại điểm danh
                        </label>

                        <select name="type" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none transition-all mb-5 font-medium" required>
                            <option value="CHECK_IN" <?= $type === 'CHECK_IN' ? 'selected' : '' ?>>CHECK IN (Vào trại)</option>
                            <option value="CHECK_OUT" <?= $type === 'CHECK_OUT' ? 'selected' : '' ?>>CHECK OUT (Rời trại)</option>
                        </select>

                        <button type="submit" class="w-full flex justify-center items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-5 py-3 rounded-xl font-bold transition-all shadow-sm">
                            <i class="bi bi-plus-circle"></i> Tạo PIN mới
                        </button>
                    </form>
                    
                    <?php if ($pin): ?>
                        <div class="mt-6 pt-6 border-t border-slate-100 text-center animate-[fadeIn_0.5s_ease-out]">
                            <div class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">
                                PIN HIỆN TẠI (<?= $type ?>)
                            </div>
                            <div class="text-4xl font-black tracking-[0.2em] py-4 rounded-xl <?= $type === 'CHECK_IN' ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' : 'bg-red-50 text-red-600 border border-red-100' ?>">
                                <?= htmlspecialchars($pin) ?>
                            </div>
                            <div class="text-slate-500 mt-3 text-xs font-medium">
                                Cung cấp PIN này cho BTC để bắt đầu điểm danh
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="space-y-3">
                    <form method="post">
                        <button name="close_session" class="w-full flex justify-center items-center gap-2 bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 px-5 py-2.5 rounded-xl font-bold transition-all shadow-sm" onclick="return confirm('Đóng phiên điểm danh hiện tại?')">
                            <i class="bi bi-lock-fill"></i> Đóng phiên hiện tại
                        </button>
                    </form>

                    <?php if ($_SESSION['role'] === 'admin'): ?>
                    <form method="post">
                        <button name="open_last" class="w-full flex justify-center items-center gap-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 px-5 py-2.5 rounded-xl font-bold transition-all shadow-sm" onclick="return confirm('Mở lại phiên gần nhất?')">
                            <i class="bi bi-unlock-fill"></i> Mở lại phiên gần nhất
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Cột phải: Lịch sử -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 overflow-hidden">
                    <div class="p-5 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                            <i class="bi bi-clock-history text-primary-500"></i> Lịch sử phiên điểm danh
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase text-xs tracking-wider">
                                <tr>
                                    <th class="px-5 py-4 text-center">PIN</th>
                                    <th class="px-5 py-4">Loại</th>
                                    <th class="px-5 py-4">Trạng thái</th>
                                    <th class="px-5 py-4">Thời gian</th>
                                    <th class="px-5 py-4">Hành động</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($sessions as $s): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="px-5 py-3.5 text-center font-black text-slate-700 tracking-wider font-mono">
                                        <?= $s['pin_code'] ?>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold <?= $s['type']==='CHECK_IN' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
                                            <?= $s['type']==='CHECK_IN'?'CHECK IN':'CHECK OUT' ?>
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <?= $s['is_active']
                                          ? '<span class="inline-flex items-center px-2.5 py-1 rounded-full bg-primary-100 text-primary-700 text-xs font-bold"><i class="bi bi-circle-fill text-[8px] text-primary-500 mr-1.5 animate-pulse"></i> Đang mở</span>'
                                          : '<span class="inline-flex items-center px-2.5 py-1 rounded-full bg-slate-100 text-slate-500 text-xs font-bold border border-slate-200">Đã đóng</span>' ?>
                                    </td>
                                    <td class="px-5 py-3.5 text-xs text-slate-600">
                                        <div class="font-medium text-slate-800"><?= date('H:i d/m/Y', strtotime($s['start_time'])) ?></div>
                                        <?php if ($s['end_time']): ?>
                                            <div class="text-slate-500 flex items-center gap-1 mt-0.5">
                                                <i class="bi bi-arrow-return-right"></i> <?= date('H:i d/m/Y', strtotime($s['end_time'])) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="text-[10px] text-slate-400 uppercase mt-1">Tạo bởi: <?= htmlspecialchars($s['created_by']) ?></div>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <?php if (!$s['is_active'] && $_SESSION['role']==='admin'): ?>
                                          <a href="?delete_session=<?= $s['id'] ?>" onclick="return confirm('Xoá vĩnh viễn phiên điểm danh này?')" class="w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-600 hover:text-white flex items-center justify-center transition-colors shadow-sm border border-red-100 mx-auto" title="Xóa">
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
                <div class="mt-4 flex justify-end">
                    <form method="post">
                        <button name="delete_all_sessions" class="flex items-center gap-2 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 px-4 py-2 rounded-lg font-semibold transition-all shadow-sm text-sm" onclick="return confirm('⚠️ XOÁ TOÀN BỘ phiên + lịch sử điểm danh?\nKhông thể khôi phục!')">
                            <i class="bi bi-trash3"></i> Xóa tất cả phiên
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

