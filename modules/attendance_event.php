<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
$event_id = $_GET['event_id'] ?? 0;
if (!$event_id) {
    header("Location: attendance.php");
    exit();
}

// Lấy thông tin sự kiện
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();
if (!$event) {
    echo "Không tìm thấy sự kiện!";
    exit();
}

$checkMsg = '';
$checkType = '';

// Thêm học sinh mới
/*if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $add_full_name = trim($_POST['add_full_name'] ?? '');
    $add_class = trim($_POST['add_class'] ?? '');
    if ($add_full_name && $add_class) {
        // Tách họ tên
        $parts = explode(' ', trim(preg_replace('/\s+/', ' ', $add_full_name)));
        $ten = array_pop($parts);
        $ho = implode(' ', $parts);
        $stmt = $pdo->prepare("INSERT INTO students (full_name, ho, ten, class) VALUES (?, ?, ?, ?)");
        $stmt->execute([$add_full_name, $ho, $ten, $add_class]);
        $sid = $pdo->lastInsertId();
        $student_code = "HS" . date('YmdHis') . $sid;
        $stmt = $pdo->prepare("UPDATE students SET student_code=? WHERE id=?");
        $stmt->execute([$student_code, $sid]);
        $checkMsg = "Đã thêm học sinh mới thành công!";
        $checkType = "success";
        // Reload danh sách
        header("Location: attendance_admin.php?event_id=" . $event_id); exit();
    } else {
        $checkMsg = "Vui lòng nhập đủ họ tên và lớp!";
        $checkType = "danger";
    }
}
*/
// Lấy lại danh sách học sinh và tình trạng điểm danh
$stmt = $pdo->query("SELECT * FROM students ORDER BY class, full_name");
$students = $stmt->fetchAll();
$stmt = $pdo->prepare("SELECT student_id FROM attendance WHERE event_id = ?");
$stmt->execute([$event_id]);
$checked_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Xử lý lưu điểm danh đồng loạt (checkbox)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_checkin'])) {
    $checked_students = $_POST['checked_students'] ?? [];
    // Lấy danh sách đã điểm danh rồi
    $stmt = $pdo->prepare("SELECT student_id FROM attendance WHERE event_id = ?");
    $stmt->execute([$event_id]);
    $checked_before = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Thêm mới những học sinh được tick mà chưa có
    $count_add = 0;
    foreach ($checked_students as $student_id) {
        if (!in_array($student_id, $checked_before)) {
            // Kiểm tra student_id có tồn tại trong bảng students không (chống tick nhầm id rỗng)
            $stmtTest = $pdo->prepare("SELECT id, student_code, full_name, class FROM students WHERE id=?");
            $stmtTest->execute([$student_id]);
            $student = $stmtTest->fetch();
            if ($student) {
                // Nếu thiếu mã học sinh thì sinh mã mới, tách họ tên nếu cần
                if (empty($student['student_code'])) {
                    $parts = explode(' ', trim(preg_replace('/\s+/', ' ', $student['full_name'])));
                    $ten = array_pop($parts);
                    $ho = implode(' ', $parts);
                    $student_code = "HS" . date('YmdHis') . $student['id'];
                    $stmtUpdate = $pdo->prepare("UPDATE students SET student_code=?, ho=?, ten=? WHERE id=?");
                    $stmtUpdate->execute([$student_code, $ho, $ten, $student['id']]);
                }
                $stmtIns = $pdo->prepare("INSERT INTO attendance (event_id, student_id, checkin_method) VALUES (?, ?, 'manual')");
                $stmtIns->execute([$event_id, $student_id]);
                $count_add++;
            }
        }
    }
    // Xóa những học sinh đã điểm danh nhưng đã bị bỏ tick
    $count_del = 0;
    foreach ($checked_before as $student_id) {
        if (!in_array($student_id, $checked_students)) {
            $stmtDel = $pdo->prepare("DELETE FROM attendance WHERE event_id = ? AND student_id = ?");
            $stmtDel->execute([$event_id, $student_id]);
            $count_del++;
        }
    }
    // Xóa lock của user này trong bảng tạm khi lưu xong
    $stmt = $pdo->prepare("DELETE FROM attendance_checking_tmp WHERE event_id=? AND user_id=?");
    $stmt->execute([$event_id, $_SESSION['user_id']]);

    $checkMsg = "Đã lưu điểm danh! Thêm mới: $count_add, Gỡ: $count_del";
    $checkType = "success";

    // Lấy lại danh sách checked_ids sau khi điểm danh
    $stmt = $pdo->prepare("SELECT student_id FROM attendance WHERE event_id = ?");
    $stmt->execute([$event_id]);
    $checked_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Kiểm tra trạng thái: có đang ở chế độ chỉnh sửa điểm danh không?
$editCheckin = isset($_POST['start_checkin']) || (isset($_POST['save_checkin']) && !$checkMsg);

$pageTitle = "Điểm danh - " . htmlspecialchars($event['title']);
$full_name = $_SESSION['full_name'] ?? '';
include '../includes/header.php';
?>
<div class="container-attendance shadow-sm" style="background:#fff;max-width:950px;margin:40px auto 24px auto;padding:32px 22px 28px 22px;border-radius:14px;box-shadow:0 4px 24px #3178c615,0 1.5px 8px #a8c8f088;">
    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
        <a href="attendance.php" class="back-link"><i class="bi bi-arrow-left-circle"></i> Sự kiện khác</a>
        <div>
            <a href="attendance_qr.php?event_id=<?= $event_id ?>" class="btn btn-outline-primary btn-sm me-2">
                <i class="bi bi-qr-code-scan"></i> Điểm danh bằng mã QR
            </a>
            <a href="export_attendance.php?event_id=<?= $event_id ?>" class="btn btn-outline-success btn-sm">
                <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
            </a>
        </div>
    </div>
    <h2 class="attendance-title" style="color:#3178c6;font-weight:700;text-align:center;margin-bottom:16px;font-size:1.7rem;"><i class="bi bi-clipboard-check"></i> Điểm danh: <?= htmlspecialchars($event['title']) ?></h2>
    <?php if ($checkMsg): ?>
        <div class="alert alert-<?= $checkType ?> alert-dismissible fade show mt-2 mb-2" role="alert">
            <?= htmlspecialchars($checkMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
    <?php endif; ?>

    <!-- Nút mở form thêm mới học sinh 
   <button class="btn btn-outline-primary mb-3" data-bs-toggle="modal" data-bs-target="#addStudentModal"><i class="bi bi-person-plus"></i> Thêm học sinh mới</button>

 Modal form thêm mới học sinh 
    <div class="modal fade" id="addStudentModal" tabindex="-1">
      <div class="modal-dialog">
        <form method="post" class="modal-content">
          <div class="modal-header"><h5 class="modal-title">Thêm học sinh mới</h5></div>
          <div class="modal-body">
            <div class="mb-2">
                <label>Họ và tên</label>
                <input type="text" class="form-control" name="add_full_name" required>
            </div>
            <div class="mb-2">
                <label>Lớp</label>
                <input type="text" class="form-control" name="add_class" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" name="add_student" class="btn btn-success">Thêm</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
          </div>
        </form>
      </div>
    </div> -->

    <?php if (!$editCheckin): // Chế độ chỉ xem ?>
        <div class="d-flex justify-content-end mb-3">
            <form method="post">
                <button type="submit" name="start_checkin" class="btn btn-primary">
                    <i class="bi bi-pencil-square"></i> Sửa/Bắt đầu điểm danh
                </button>
            </form>
        </div>
        <h5 style="color:#3178c6;">Danh sách học sinh & trạng thái điểm danh</h5>
        <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0">
            <thead>
                <tr>
                    <th>Mã HS</th>
                    <th>Họ tên</th>
                    <th>Lớp</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($students as $st): ?>
            <tr class="<?= in_array($st['id'], $checked_ids) ? 'checked' : '' ?>">
                <td><?= htmlspecialchars($st['student_code']) ?></td>
                <td><?= htmlspecialchars($st['full_name']) ?></td>
                <td><?= htmlspecialchars($st['class']) ?></td>
                <td>
                    <?php if (in_array($st['id'], $checked_ids)): ?>
                        <span class="badge bg-success"><i class="bi bi-check-circle"></i> Đã điểm danh</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Chưa điểm danh</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php else: // Chế độ chỉnh sửa điểm danh ?>
        <form method="POST" id="bulkCheckinForm">
            <div class="d-flex justify-content-between mb-2 flex-wrap">
                <span style="color:#3178c6;font-weight:600;">Tích chọn rồi nhấn <b>Lưu điểm danh</b>.</span>
                <button type="submit" name="save_checkin" class="btn btn-success mt-2 mt-sm-0">
                    <i class="bi bi-save"></i> Lưu điểm danh
                </button>
            </div>
            <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
                <thead>
                    <tr>
                        <th>
                            <input type="checkbox" id="selectAll" class="form-check-input select-all" title="Chọn tất cả">
                        </th>
                        <th>Mã HS</th>
                        <th>Họ tên</th>
                        <th>Lớp</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($students as $st): ?>
                <tr class="<?= in_array($st['id'], $checked_ids) ? 'checked' : '' ?>">
                    <td>
                        <input type="checkbox" class="form-check-input student-checkbox"
                            name="checked_students[]"
                            value="<?= $st['id'] ?>"
                            data-student="<?= $st['id'] ?>"
                            <?= in_array($st['id'], $checked_ids) ? 'checked' : '' ?>>
                    </td>
                    <td><?= htmlspecialchars($st['student_code']) ?></td>
                    <td><?= htmlspecialchars($st['full_name']) ?></td>
                    <td><?= htmlspecialchars($st['class']) ?></td>
                    <td>
                        <?php if (in_array($st['id'], $checked_ids)): ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle"></i> Đã điểm danh</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Chưa điểm danh</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <div class="d-flex justify-content-end mb-2">
                <button type="submit" name="save_checkin" class="btn btn-success">
                    <i class="bi bi-save"></i> Lưu điểm danh
                </button>
            </div>
        </form>
        <script>
        // Chọn tất cả / Bỏ chọn tất cả
        document.getElementById('selectAll').onclick = function() {
            let checkboxes = document.querySelectorAll('.student-checkbox');
            for(const cb of checkboxes) { if(!cb.disabled) cb.checked = this.checked; }
        };

        // ======= Đồng bộ chọn tạm thời giữa các admin ========
        const eventId = <?= (int)$event_id ?>;
        let lockedStudents = new Set();

        function syncLockStatus() {
            fetch('attendance_checking_api.php?event_id=' + eventId)
              .then(resp => resp.json())
              .then(data => {
                lockedStudents = new Set(data.map(item => item.student_id));
                document.querySelectorAll('.student-checkbox').forEach(cb => {
                    let sid = cb.getAttribute('data-student');
                    if (lockedStudents.has(Number(sid))) {
                        cb.disabled = true;
                        cb.closest('tr').classList.add('table-warning');
                    } else {
                        cb.disabled = false;
                        cb.closest('tr').classList.remove('table-warning');
                    }
                });
              });
        }
        // Khi tick/untick thì cập nhật trạng thái cho mọi người
        document.querySelectorAll('.student-checkbox').forEach(cb => {
            cb.addEventListener('change', function() {
                let sid = this.getAttribute('data-student');
                let action = this.checked ? 'check' : 'uncheck';
                fetch('attendance_checking_api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `event_id=${eventId}&student_id=${sid}&action=${action}`
                });
            });
        });
        // Đồng bộ mỗi 2 giây
        setInterval(syncLockStatus, 2000);
        window.addEventListener('focus', syncLockStatus);
        syncLockStatus();
        </script>
    <?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>