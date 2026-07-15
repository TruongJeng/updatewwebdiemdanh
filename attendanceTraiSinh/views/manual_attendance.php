<?php
/**
 * Điểm danh thủ công bằng checkbox
 * URL: manual_attendance.php?event_id=X
 */
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}
if (!in_array($_SESSION['role'], ['admin', 'teacher', 'club_leader', 'staff'])) {
    header("Location: ../../dashboard.php");
    exit();
}

$eventId = (int)($_GET['event_id'] ?? 0);
if (!$eventId) {
    header("Location: events.php");
    exit;
}

// Lấy thông tin sự kiện
$stmt = $pdo->prepare("SELECT * FROM ts_events WHERE id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$event) {
    echo "Không tìm thấy sự kiện!";
    exit;
}

$checkMsg = '';
$checkType = '';

// Xử lý lưu điểm danh
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_checkin'])) {
    $checkedStudents = $_POST['checked_students'] ?? [];
    $userId = $_SESSION['user_id'];

    // Tìm hoặc tạo phiên CHECK_IN cho event này
    $stmt = $pdo->prepare("SELECT id FROM attendance_sessions WHERE event_id = ? AND type = 'CHECK_IN' ORDER BY start_time DESC LIMIT 1");
    $stmt->execute([$eventId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        $pin = strval(rand(100000, 999999));
        $stmt = $pdo->prepare("INSERT INTO attendance_sessions (event_id, pin_code, type, created_by, is_active) VALUES (?, ?, 'CHECK_IN', ?, 1)");
        $stmt->execute([$eventId, $pin, $userId]);
        $sessionId = $pdo->lastInsertId();
    } else {
        $sessionId = $session['id'];
    }

    // Danh sách đã điểm danh trước đó
    $stmt = $pdo->prepare("
        SELECT DISTINCT al.student_id 
        FROM attendance_logs al 
        JOIN attendance_sessions s ON al.session_id = s.id
        WHERE s.event_id = ? AND al.type = 'CHECK_IN'
    ");
    $stmt->execute([$eventId]);
    $checkedBefore = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Thêm mới
    $countAdd = 0;
    foreach ($checkedStudents as $studentId) {
        $studentId = (int)$studentId;
        if (!in_array($studentId, $checkedBefore)) {
            $stmt = $pdo->prepare("INSERT INTO attendance_logs (student_id, session_id, type, scan_time, scanned_by) VALUES (?, ?, 'CHECK_IN', NOW(), ?)");
            $stmt->execute([$studentId, $sessionId, $userId]);
            $countAdd++;
        }
    }

    // Gỡ điểm danh
    $countDel = 0;
    foreach ($checkedBefore as $studentId) {
        if (!in_array((string)$studentId, $checkedStudents)) {
            $stmt = $pdo->prepare("
                DELETE al FROM attendance_logs al
                JOIN attendance_sessions s ON al.session_id = s.id
                WHERE al.student_id = ? AND s.event_id = ?
            ");
            $stmt->execute([$studentId, $eventId]);
            $countDel++;
        }
    }

    $checkMsg = "Đã lưu! Thêm: $countAdd, Gỡ: $countDel";
    $checkType = 'success';
}

// Lấy danh sách trại sinh
$campers = $pdo->query("SELECT * FROM campers WHERE is_active = 1 ORDER BY class, full_name")->fetchAll(PDO::FETCH_ASSOC);

// Danh sách đã điểm danh cho event này
$stmt = $pdo->prepare("
    SELECT DISTINCT al.student_id 
    FROM attendance_logs al
    JOIN attendance_sessions s ON al.session_id = s.id
    WHERE s.event_id = ? AND al.type = 'CHECK_IN'
");
$stmt->execute([$eventId]);
$checkedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = "Điểm danh - " . htmlspecialchars($event['title']);
$full_name = $_SESSION['full_name'] ?? '';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="ml-0 lg:ml-64 pt-4 min-h-screen bg-slate-50/50 transition-all duration-300 ease-in-out p-4 sm:p-6 lg:p-8">
    <div class="max-w-7xl mx-auto pb-12">
        <!-- Back Link -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <a href="events.php" class="text-slate-500 hover:text-primary-600 transition-colors flex items-center gap-1.5 text-sm font-medium bg-white px-3 py-1.5 rounded-full border border-slate-200 shadow-sm hover:shadow">
                <i class="bi bi-arrow-left"></i> Quay lại sự kiện
            </a>
        </div>

        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center shadow-sm">
                    <i class="bi bi-hand-index text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">ĐIỂM DANH THỦ CÔNG</h2>
                    <p class="text-sm font-medium text-slate-500 mt-1"><?= htmlspecialchars($event['title']) ?></p>
                </div>
            </div>

            <div class="flex items-center gap-2 text-sm">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-50 text-emerald-700 font-bold border border-emerald-200">
                    <i class="bi bi-check-circle-fill"></i>
                    <?= count($checkedIds) ?> / <?= count($campers) ?> đã điểm danh
                </span>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($checkMsg): ?>
        <div class="mb-6 flex items-center justify-between p-4 bg-<?= $checkType == 'success' ? 'emerald' : 'red' ?>-50 border-l-4 border-<?= $checkType == 'success' ? 'emerald' : 'red' ?>-500 text-<?= $checkType == 'success' ? 'emerald' : 'red' ?>-700 rounded-r-lg shadow-sm">
            <div class="flex items-center gap-2">
                <i class="bi bi-<?= $checkType == 'success' ? 'check-circle-fill' : 'exclamation-circle-fill' ?> text-lg"></i>
                <span class="font-medium"><?= htmlspecialchars($checkMsg) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" id="bulkCheckinForm">
            <div class="bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-amber-200 overflow-hidden mb-6 ring-4 ring-amber-500/10">
                <div class="p-4 sm:p-5 border-b border-slate-100 bg-amber-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div>
                            <h3 class="text-base sm:text-lg font-bold text-amber-800 flex items-center gap-2">
                                <i class="bi bi-pencil-square text-amber-600"></i> Chọn trại sinh có mặt
                            </h3>
                            <p class="text-sm text-amber-600/80 mt-1">Tích chọn trại sinh rồi nhấn "Lưu điểm danh"</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <!-- Search -->
                        <div class="relative">
                            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="text" id="searchInput" placeholder="Tìm theo tên, lớp..." class="pl-9 pr-3 py-2 bg-white border border-slate-200 rounded-lg text-sm outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 shadow-sm w-48" oninput="filterStudents()">
                        </div>
                        <button type="submit" name="save_checkin" class="flex items-center justify-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-5 py-2.5 rounded-xl font-semibold transition-all shadow-sm text-sm w-full sm:w-auto">
                            <i class="bi bi-save"></i> Lưu điểm danh
                        </button>
                    </div>
                </div>

                <!-- Select All -->
                <div class="px-5 py-3 bg-slate-50 border-b border-slate-200">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" id="selectAll" class="w-5 h-5 text-primary-600 border-slate-300 rounded focus:ring-primary-500 cursor-pointer" onclick="toggleAll(this)">
                        <span class="font-bold text-sm text-slate-700">Chọn tất cả</span>
                    </label>
                </div>

                <!-- Mobile Cards -->
                <div class="lg:hidden p-3 space-y-2">
                    <?php foreach ($campers as $c): ?>
                    <label class="student-row flex items-center gap-3 p-3 rounded-xl cursor-pointer transition-colors <?= in_array($c['id'], $checkedIds) ? 'bg-emerald-50/50 border border-emerald-100' : 'bg-slate-50 border border-slate-100' ?>"
                           data-name="<?= strtolower($c['full_name']) ?>" data-class="<?= strtolower($c['class']) ?>">
                        <input type="checkbox" class="student-checkbox w-5 h-5 text-primary-600 border-slate-300 rounded focus:ring-primary-500 cursor-pointer shrink-0"
                            name="checked_students[]"
                            value="<?= $c['id'] ?>"
                            <?= in_array($c['id'], $checkedIds) ? 'checked' : '' ?>>
                        <div class="min-w-0 flex-1">
                            <div class="font-bold text-slate-800 text-sm truncate"><?= htmlspecialchars($c['full_name']) ?></div>
                            <div class="text-[11px] text-slate-500"><?= htmlspecialchars($c['class']) ?> • <?= htmlspecialchars($c['student_code']) ?></div>
                        </div>
                        <?php if (in_array($c['id'], $checkedIds)): ?>
                        <span class="shrink-0 text-[10px] font-bold text-emerald-600"><i class="bi bi-check2"></i></span>
                        <?php endif; ?>
                    </label>
                    <?php endforeach; ?>
                </div>

                <!-- Desktop Table -->
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase text-xs tracking-wider">
                            <tr>
                                <th class="px-5 py-4 w-12 text-center">#</th>
                                <th class="px-5 py-4">Mã</th>
                                <th class="px-5 py-4">Họ và tên</th>
                                <th class="px-5 py-4">Lớp</th>
                                <th class="px-5 py-4">Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        <?php foreach ($campers as $c): ?>
                        <tr class="student-row hover:bg-slate-50/80 transition-colors <?= in_array($c['id'], $checkedIds) ? 'bg-emerald-50/30' : '' ?>"
                            data-name="<?= strtolower($c['full_name']) ?>" data-class="<?= strtolower($c['class']) ?>">
                            <td class="px-5 py-3.5 text-center">
                                <input type="checkbox" class="student-checkbox w-4 h-4 text-primary-600 border-slate-300 rounded focus:ring-primary-500 cursor-pointer"
                                    name="checked_students[]"
                                    value="<?= $c['id'] ?>"
                                    <?= in_array($c['id'], $checkedIds) ? 'checked' : '' ?>>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="font-mono text-xs font-bold text-slate-500 bg-slate-100 px-2 py-1 rounded"><?= htmlspecialchars($c['student_code']) ?></span>
                            </td>
                            <td class="px-5 py-3.5 font-bold text-slate-800"><?= htmlspecialchars($c['full_name']) ?></td>
                            <td class="px-5 py-3.5 text-slate-600"><?= htmlspecialchars($c['class']) ?></td>
                            <td class="px-5 py-3.5">
                                <?php if (in_array($c['id'], $checkedIds)): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded text-emerald-700 text-xs font-medium">
                                        <i class="bi bi-check2"></i> Đã điểm danh
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded text-slate-500 text-xs font-medium">
                                        Chưa điểm danh
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Bottom Save -->
                <div class="p-5 border-t border-slate-200 bg-slate-50 flex justify-end">
                    <button type="submit" name="save_checkin" class="flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-xl font-bold transition-all shadow-sm text-sm">
                        <i class="bi bi-save text-lg"></i> Lưu điểm danh
                    </button>
                </div>
            </div>
        </form>
    </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<script>
function toggleAll(master) {
    document.querySelectorAll('.student-checkbox').forEach(cb => {
        if (cb.closest('.student-row').style.display !== 'none') {
            cb.checked = master.checked;
        }
    });
}

function filterStudents() {
    const q = document.getElementById('searchInput').value.toLowerCase().trim();
    document.querySelectorAll('.student-row').forEach(row => {
        const name = row.dataset.name || '';
        const cls = row.dataset.class || '';
        row.style.display = (!q || name.includes(q) || cls.includes(q)) ? '' : 'none';
    });
}
</script>
