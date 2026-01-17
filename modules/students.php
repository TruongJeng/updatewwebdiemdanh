<?php
require_once __DIR__ . '/../config/session.php';
require __DIR__ . '/../includes/db.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
// Chỉ cho phép admin, giáo viên, ban chủ nhiệm truy cập
if (!in_array($_SESSION['role'], ['admin', 'teacher','club_leader'])) {
    header("Location: ../dashboard.php");
    exit("Bạn không có quyền truy cập chức năng này!");
}
$addMsg = '';
$addMsgType = '';
$editMsg = '';
$editMsgType = '';
$deleteMsg = '';
$deleteMsgType = '';

// Xử lý thêm học sinh (Mã học sinh tự động hoặc import)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_student'])) {
    // Tạo mã học sinh tự động: 2 số cuối năm + 4 số tăng dần
    $year = date('y');
    $stmt = $pdo->prepare("SELECT student_code FROM students WHERE LEFT(student_code,2)=? ORDER BY student_code DESC LIMIT 1");
    $stmt->execute([$year]);
    $last_code = $stmt->fetchColumn();
    if ($last_code) {
        $last_num = (int)substr($last_code, 2, 4);
    } else {
        $last_num = 0;
    }
    $next_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
    $student_code = $year . $next_num;

    $ho = trim($_POST['ho'] ?? '');
    $ten = trim($_POST['ten'] ?? '');
    $class = trim($_POST['class'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($ho && $ten) {
        $stmt = $pdo->prepare("SELECT id FROM students WHERE student_code = ?");
        $stmt->execute([$student_code]);
        if ($stmt->fetch()) {
            $addMsg = "Mã học sinh đã tồn tại!";
            $addMsgType = "danger";
        } else {
            $full_name = trim("$ho $ten");
            $stmt = $pdo->prepare("INSERT INTO students (student_code, ho, ten, full_name, class, phone, email, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$student_code, $ho, $ten, $full_name, $class, $phone, $email, $address]);
            $addMsg = "Thêm học sinh thành công! Mã: $student_code";
            $addMsgType = "success";
        }
    } else {
        $addMsg = "Vui lòng nhập đầy đủ thông tin!";
        $addMsgType = "danger";
    }
}

// Xử lý xóa học sinh
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM attendance WHERE student_id=?");
    $stmt->execute([$delete_id]);
    $stmt = $pdo->prepare("DELETE FROM students WHERE id=?");
    $stmt->execute([$delete_id]);
    $deleteMsg = "Đã xóa học sinh!"; $deleteMsgType = "success";
}

// Xử lý xóa tất cả học sinh
if (isset($_POST['delete_all'])) {
    $pdo->exec("DELETE FROM attendance");
    $pdo->exec("DELETE FROM students");
    $deleteMsg = "Đã xóa tất cả học sinh!";
    $deleteMsgType = "success";
}

// Xử lý sửa học sinh
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_student'])) {
    $edit_id = $_POST['edit_id'];
    $student_code = trim($_POST['student_code'] ?? '');
    $ho = trim($_POST['ho'] ?? '');
    $ten = trim($_POST['ten'] ?? '');
    $class = trim($_POST['class'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $full_name = trim("$ho $ten");
    $stmt = $pdo->prepare("UPDATE students SET student_code=?, ho=?, ten=?, full_name=?, class=?, phone=?, email=?, address=? WHERE id=?");
    $stmt->execute([$student_code, $ho, $ten, $full_name, $class, $phone, $email, $address, $edit_id]);
    $editMsg = "Sửa thông tin thành công!";
    $editMsgType = "success";
}

// Xử lý import CSV (tự sinh mã học sinh nếu thiếu)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['import_csv'])) {
    if (is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
        $handle = fopen($_FILES['csv_file']['tmp_name'], "r");
        $row = 0;
        $added = 0;
        $skipped = 0;
        $year = date('y');
        // Lấy mã lớn nhất hiện tại
        $stmtmax = $pdo->prepare("SELECT student_code FROM students WHERE LEFT(student_code,2)=? ORDER BY student_code DESC LIMIT 1");
        $stmtmax->execute([$year]);
        $max = 0;
        if ($rowmax = $stmtmax->fetch(PDO::FETCH_ASSOC)) {
            $code_num = (int)substr($rowmax['student_code'],2,4);
            if ($code_num > $max) $max = $code_num;
        }
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if ($row == 0) { $row++; continue; }
            $student_code = trim($data[0] ?? '');
            $ho = trim($data[1] ?? '');
            $ten = trim($data[2] ?? '');
            $class = trim($data[3] ?? '');
            $phone = trim($data[4] ?? '');
            $email = trim($data[5] ?? '');
            $address = trim($data[6] ?? '');
            $note = trim($data[7] ?? '');

            // Nếu mã học sinh trống, tự động sinh mã
            if (!$student_code || strlen($student_code) < 3) {
                $max++;
                $student_code = $year . str_pad($max, 4, '0', STR_PAD_LEFT);
            }

            if ($ten) {
                // Kiểm tra trùng mã
                $stmt = $pdo->prepare("SELECT id FROM students WHERE student_code = ?");
                $stmt->execute([$student_code]);
                if (!$stmt->fetch()) {
                    $full_name = trim("$ho $ten");
                    $stmt = $pdo->prepare("INSERT INTO students (student_code, ho, ten, class, phone, email, address, note, full_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$student_code, $ho, $ten, $class, $phone, $email, $address, $note, $full_name]);
                    $added++;
                } else {
                    $skipped++;
                }
            } else {
                $skipped++;
            }
            $row++;
        }
        fclose($handle);
        $addMsg = "Import thành công: $added dòng mới, $skipped dòng bị bỏ qua (trùng mã hoặc thiếu tên).";
        $addMsgType = "success";
    } else {
        $addMsg = "Lỗi upload file!";
        $addMsgType = "danger";
    }
}

// Xử lý export CSV
if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=students.csv');
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF"); // BOM UTF-8
    fputcsv($output, array('Mã học sinh', 'Họ', 'Tên', 'Lớp', 'Số điện thoại', 'Email', 'Địa chỉ', 'Ghi chú'));
    $stmt = $pdo->query("SELECT * FROM students ORDER BY class, ho, ten");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Thêm dấu nháy đơn để số điện thoại không bị mất số 0 khi mở bằng Excel
        $phone = $row['phone'];
        if ($phone !== "" && $phone[0] === '0') $phone = "'" . $phone;
        fputcsv($output, array(
            $row['student_code'],
            $row['ho'],
            $row['ten'],
            $row['class'],
            $phone,
            $row['email'],
            $row['address'],
            $row['note']
        ));
    }
    fclose($output);
    exit();
}

// Lấy danh sách học sinh
$where = [];
$params = [];

if (!empty($_GET['q'])) {
    $q = "%".trim($_GET['q'])."%";
    $where[] = "(student_code LIKE ? OR full_name LIKE ? OR ho LIKE ? OR ten LIKE ? OR class LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $params = array_merge($params, array_fill(0,7,$q));
}
if (!empty($_GET['class'])) {
    $where[] = "class = ?";
    $params[] = $_GET['class'];
}
$where_sql = $where ? "WHERE ".implode(" AND ", $where) : "";
$stmt = $pdo->prepare("SELECT * FROM students $where_sql ORDER BY class, ho, ten");
$stmt->execute($params);
$students = $stmt->fetchAll();

// Nếu sửa, lấy thông tin học sinh cần sửa
$editStudent = null;
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$edit_id]);
    $editStudent = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>CLB Kỹ năng Đoàn - Hội Trường THPT Lý Thường Kiệt</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="/hethongdiemdanh/assets/logo_CLB.png">
    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #e8f1fb; }
        .container-student {
            background: #fff; max-width: 1150px; margin: 38px auto 24px auto;
            padding: 32px 22px 28px 22px; border-radius: 14px;
            box-shadow: 0 4px 24px #3178c615, 0 1.5px 8px #a8c8f088;
        }
        .title-main { color: #3178c6; font-weight: 700; text-align: center; margin-bottom: 16px; }
        .form-label { color: #3178c6; font-weight: 500; }
        .btn-main {
            background: #6fa6e3; color: #fff; font-weight: 600;
            border-radius: 8px; transition: background 0.2s;
        }
        .btn-main:hover { background: #3178c6;}
        .table thead { background: #a8c8f0; color: #3178c6; }
        .table tbody tr td { vertical-align: middle; }
        .back-link { color: #3178c6; text-decoration: none;}
        .back-link:hover { text-decoration: underline; color: #1757a6;}
        .actions a { margin-right: 10px; color: #3178c6; text-decoration: none;}
        .actions a:hover { color: #1757a6; text-decoration: underline;}
        .logout-link { color: #e72c2c; float:right; text-decoration:none;}
        .logout-link:hover { text-decoration: underline;}
        @media (max-width: 600px) {
            .container-student { padding: 12px 4px; }
            .title-main { font-size: 1.1em; }
        }
    </style>
</head>
<body>
<?php
$pageTitle = "QUẢN LÝ HỌC SINH";
$full_name = $_SESSION['full_name'] ?? '';
include '../includes/header.php';
?>
<div class="container-student shadow-sm">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="../dashboard.php" class="back-link"><i class="bi bi-arrow-left-circle"></i> Về Trang chủ</a>
    </div>
    <h2 class="title-main"><i class="bi bi-people"></i> Quản lý học sinh</h2>
    <!-- Thông báo -->
    <?php if ($addMsg): ?>
        <div class="alert alert-<?= $addMsgType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($addMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
    <?php endif; ?>
    <?php if ($editMsg): ?>
        <div class="alert alert-<?= $editMsgType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($editMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
    <?php endif; ?>
    <?php if ($deleteMsg): ?>
        <div class="alert alert-<?= $deleteMsgType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($deleteMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
    <?php endif; ?>

    <!-- Nút Thêm học sinh (ẩn/hiện form) -->
    <div class="mb-3 d-flex justify-content-end">
        <button class="btn btn-main" id="showAddFormBtn"><i class="bi bi-plus-circle"></i> Thêm học sinh</button>
    </div>

    <!-- Form Thêm học sinh (ẩn mặc định)-->
    <div id="addStudentForm" class="mb-4" style="display:none;">
        <form method="POST" class="row g-3">
            <h5 class="mb-2" style="color:#3178c6;">Thêm học sinh mới</h5>
            <div class="col-md-2">
                <label class="form-label">Mã học sinh (tự động)</label>
                <input type="text" class="form-control" name="student_code" id="student_code" readonly style="background:#f1f1f1;">
            </div>
            <div class="col-md-2">
                <label class="form-label">Họ</label>
                <input type="text" class="form-control" name="ho" id="inputHo" required oninput="updateFullName()">
            </div>
            <div class="col-md-2">
                <label class="form-label">Tên</label>
                <input type="text" class="form-control" name="ten" id="inputTen" required oninput="updateFullName()">
            </div>
            <div class="col-md-2">
                <label class="form-label">Lớp</label>
                <input type="text" class="form-control" name="class">
            </div>
            <div class="col-md-2">
                <label class="form-label">Số ĐT</label>
                <input type="text" class="form-control" name="phone">
            </div>
            <div class="col-md-2">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" name="email">
            </div>
            <div class="col-md-12">
                <label class="form-label">Địa chỉ</label>
                <input type="text" class="form-control" name="address">
            </div>
            <div class="col-md-12">
                <label class="form-label">Họ và tên:</label>
                <span id="fullNamePreview" style="font-weight:600; color:#3178c6;">...</span>
            </div>
            <div class="col-12 d-flex justify-content-end">
                <button type="submit" name="add_student" class="btn btn-main px-4"><i class="bi bi-plus-circle"></i> Thêm học sinh</button>
                <button type="button" class="btn btn-outline-secondary ms-2" onclick="hideAddForm()">Hủy</button>
            </div>
        </form>
    </div>

    <!-- Form sửa học sinh -->
    <?php if ($editStudent): ?>
    <div class="mb-4">
    <form method="POST" class="row g-3">
        <h5 class="mb-2" style="color:#3178c6;">Sửa thông tin học sinh</h5>
        <input type="hidden" name="edit_id" value="<?= $editStudent['id'] ?>">
        <div class="col-md-2">
            <label class="form-label">Mã học sinh</label>
            <input type="text" class="form-control" name="student_code" value="<?= htmlspecialchars($editStudent['student_code']) ?>" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">Họ</label>
            <input type="text" class="form-control" name="ho" value="<?= htmlspecialchars($editStudent['ho']) ?>" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">Tên</label>
            <input type="text" class="form-control" name="ten" value="<?= htmlspecialchars($editStudent['ten']) ?>" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">Lớp</label>
            <input type="text" class="form-control" name="class" value="<?= htmlspecialchars($editStudent['class']) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Số ĐT</label>
            <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($editStudent['phone']) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($editStudent['email']) ?>">
        </div>
        <div class="col-md-12">
            <label class="form-label">Địa chỉ</label>
            <input type="text" class="form-control" name="address" value="<?= htmlspecialchars($editStudent['address']) ?>">
        </div>
        <div class="col-12 d-flex justify-content-end">
            <button type="submit" name="edit_student" class="btn btn-main px-4 me-2"><i class="bi bi-save"></i> Lưu thay đổi</button>
            <a href="students.php" class="btn btn-outline-secondary">Hủy</a>
        </div>
    </form>
    </div>
    <?php endif; ?>

    <div class="row g-3 mb-4 align-items-end">
        <div class="col-md-5">
            <form method="POST" enctype="multipart/form-data">
                <label class="form-label">Import học sinh từ file CSV</label>
                <input type="file" class="form-control" name="csv_file" accept=".csv" required>
                <button type="submit" name="import_csv" class="btn btn-outline-primary mt-2 w-100">
                    <i class="bi bi-upload"></i> Import
                </button>
            </form>
        </div>
        <div class="col-md-3">
            <a href="../assets/download.php?file=student.csv" class="btn btn-outline-secondary w-100">
                <i class="bi bi-download"></i> Tải file mẫu
            </a>
        </div>
        <div class="col-md-3">
            <form method="GET">
                <button type="submit" name="export_csv" value="1" class="btn btn-outline-success w-100">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
                </button>
            </form>
        </div>
    </div>


    <!-- Nút XÓA TẤT CẢ -->
    <form method="post" onsubmit="return confirm('Bạn chắc chắn muốn xóa TẤT CẢ học sinh?');" class="mb-3 d-flex justify-content-end">
        <button type="submit" name="delete_all" class="btn btn-danger">
            <i class="bi bi-trash3"></i> XÓA TẤT CẢ
        </button>
    </form>

    <h5 class="mt-4 mb-2" style="color:#3178c6;">Danh sách học sinh</h5>
    <!--Tìm kiếm và lọc-->
    <form method="get" class="row g-3 mb-3">
        <div class="col-md-5">
            <input type="text" class="form-control" name="q" value="<?= htmlspecialchars($_GET['q']??'') ?>" placeholder="Tìm mã số, tên, lớp, số ĐT, email...">
        </div>
        <div class="col-md-4">
            <select name="class" class="form-select">
                <option value="">--Lọc theo lớp--</option>
                <?php
                $classList = $pdo->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL AND class!='' ORDER BY class")->fetchAll();
                foreach($classList as $c) {
                    $sel = (($_GET['class']??'')==$c['class']) ? 'selected' : '';
                    echo "<option value='".htmlspecialchars($c['class'])."' $sel>".htmlspecialchars($c['class'])."</option>";
                }
                ?>
            </select>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search"></i> Tìm kiếm/Lọc</button>
        </div>
    </form>

    <div class="table-responsive">
    <table class="table table-bordered align-middle">
        <thead>
            <tr>
                <th>Mã học sinh</th>
                <th><b>Họ và tên</b></th>
                <th>Lớp</th>
                <th>Số ĐT</th>
                <th>Email</th>
                <th>Địa chỉ</th>
                <th style="width:110px;">Hành động</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($students as $student): ?>
            <tr>
                <td><?= htmlspecialchars($student['student_code']) ?></td>
                <td><b><?= htmlspecialchars(trim($student['ho'] . ' ' . $student['ten'])) ?></b></td>
                <td><?= htmlspecialchars($student['class']) ?></td>
                <td><?= htmlspecialchars($student['phone']) ?></td>
                <td><?= htmlspecialchars($student['email']) ?></td>
                <td><?= htmlspecialchars($student['address']) ?></td>
                <td class="actions">
                    <a href="students.php?edit_id=<?= $student['id'] ?>" title="Sửa"><i class="bi bi-pencil-square"></i></a>
                    <a href="students.php?delete_id=<?= $student['id'] ?>" onclick="return confirm('Bạn chắc chắn muốn xóa?')" title="Xóa"><i class="bi bi-trash"></i></a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php include '../includes/footer.php'; ?>
</div>
<!-- Bootstrap JS & show/hide add form, live họ tên, hiển thị mã học sinh mẫu -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('showAddFormBtn').onclick = function() {
    document.getElementById('addStudentForm').style.display = 'block';
    this.style.display = 'none';
    setTimeout(function(){document.getElementById('inputHo').focus();}, 100);
    return false;
};
function hideAddForm() {
    document.getElementById('addStudentForm').style.display = 'none';
    document.getElementById('showAddFormBtn').style.display = 'inline-block';
    document.getElementById('inputHo').value = '';
    document.getElementById('inputTen').value = '';
    updateFullName();
}
// Hiển thị mã học sinh mẫu (dự đoán số tiếp theo)
window.addEventListener('DOMContentLoaded', function() {
    <?php
    $year = date('y');
    $stmt = $pdo->prepare("SELECT student_code FROM students WHERE LEFT(student_code,2)=? ORDER BY student_code DESC LIMIT 1");
    $stmt->execute([$year]);
    $max = 0;
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $code_num = (int)substr($row['student_code'],2,4);
        if ($code_num > $max) $max = $code_num;
    }
    $next_num = str_pad($max+1, 4, '0', STR_PAD_LEFT);
    $auto_code = $year . $next_num;
    ?>
    document.getElementById('student_code').value = '<?= $auto_code ?>';
});
function updateFullName() {
    var ho = document.getElementById('inputHo').value.trim();
    var ten = document.getElementById('inputTen').value.trim();
    var full = (ho + ' ' + ten).replace(/\s+/g,' ').trim();
    document.getElementById('fullNamePreview').textContent = full ? full : '...';
}
</script>
</body>
</html>