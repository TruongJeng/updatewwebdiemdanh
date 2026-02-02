<?php
require_once __DIR__ . '/../../config/session.php';
if (!in_array($_SESSION['role'], ['admin','club_leader'])) {
    die('Không có quyền');
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../../PHPSpreadsheet/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
/* ===== XOÁ MỀM ===== */
if (isset($_GET['disable'])) {
    $stmt = $pdo->prepare("UPDATE campers SET is_active = 0 WHERE student_code = ?");
    $stmt->execute([$_GET['disable']]);
    header("Location: manage_campers.php");
    exit;
}
if (isset($_GET['restore'])) {
    $student_code = $_GET['restore'];

    $stmt = $pdo->prepare("
        UPDATE campers 
        SET is_active = 1 
        WHERE student_code = ?
    ");
    $stmt->execute([$student_code]);

    header("Location: manage_campers.php?msg=restored");
    exit;
}

/* =======================
   XOÁ VĨNH VIỄN (ADMIN)
======================= */
if (isset($_GET['delete_forever']) && $_SESSION['role'] === 'admin') {
    $student_code = $_GET['delete_forever'];

    $pdo->beginTransaction();
    try {
        // Lấy student_id
        $stmt = $pdo->prepare("SELECT id FROM campers WHERE student_code = ?");
        $stmt->execute([$student_code]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($student) {
            $student_id = $student['id'];

            // Xoá lịch sử điểm danh
            $stmt = $pdo->prepare("
                DELETE FROM attendance_logs 
                WHERE student_id = ?
            ");
            $stmt->execute([$student_id]);

            // Xoá trại sinh
            $stmt = $pdo->prepare("
                DELETE FROM campers 
                WHERE id = ?
            ");
            $stmt->execute([$student_id]);
        }

        $pdo->commit();
        header("Location: manage_campers.php?msg=deleted");
        exit;

    } catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Lỗi xoá vĩnh viễn: " . $e->getMessage());
}
}

// ==========================
// XOÁ TẤT CẢ TRẠI SINH (ADMIN)
// ==========================
if (
    isset($_POST['delete_all']) &&
    isset($_SESSION['role']) &&
    $_SESSION['role'] === 'admin'
) {
    try {
        $pdo->beginTransaction();

        $pdo->exec("DELETE FROM attendance_logs");
        $pdo->exec("DELETE FROM campers");

        $pdo->commit();

        // reset auto increment – chạy riêng
        $pdo->exec("ALTER TABLE campers AUTO_INCREMENT = 1");
        $pdo->exec("ALTER TABLE attendance_logs AUTO_INCREMENT = 1");

        $deleteMsg = "✅ Đã xoá TOÀN BỘ trại sinh và lịch sử điểm danh!";
        $deleteMsgType = "success";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die('Lỗi xoá toàn bộ trại sinh: ' . $e->getMessage());

    }
}

/* ===== THÊM 1 ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_one'])) {

    // Lấy dữ liệu an toàn
    $student_code  = $_POST['student_code'] ?? null;
    $full_name     = $_POST['full_name'] ?? null;
    $class         = $_POST['class'] ?? null;
    $phone         = $_POST['phone'] ?? null;
    $phone_parent  = $_POST['phone_parent'] ?? null;
    $email = isset($_POST['email']) ? trim($_POST['email']) : null;
    $profile_photo = $_POST['profile_photo'] ?? null;

    // Check dữ liệu bắt buộc
    if (!$student_code || !$full_name) {
        die("Thiếu mã học sinh hoặc họ tên");
    }

    // Check trùng student_code
    $check = $pdo->prepare("SELECT 1 FROM campers WHERE student_code = ?");
    $check->execute([$student_code]);

    if ($check->fetch()) {
        die("Mã học sinh đã tồn tại");
    }

    // Insert
    $stmt = $pdo->prepare("
        INSERT INTO campers
        (student_code, full_name, class, phone, phone_parent, email, profile_photo, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
    ");

    $stmt->execute([
        $student_code,
        $full_name,
        $class,
        $phone,
        $phone_parent,
        $email,
        $profile_photo
    ]);
}
use PhpOffice\PhpSpreadsheet\IOFactory;

/* ===== IMPORT CSV ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_excel'])) {

    if (is_uploaded_file($_FILES['excel']['tmp_name'])) {

        $filePath = $_FILES['excel']['tmp_name'];

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            $pdo->beginTransaction();

            foreach ($rows as $index => $row) {

                // Bỏ dòng header (dòng 1)
                if ($index === 1) continue;

                $student_code  = trim($row['A'] ?? '');
                $full_name     = trim($row['B'] ?? '');
                $class         = trim($row['C'] ?? '');
                $phone         = trim($row['D'] ?? '');
                $phone_parent  = trim($row['E'] ?? '');
                $email         = trim($row['F'] ?? '');
                $profile_photo = trim($row['G'] ?? '');

                if ($student_code === '' || $full_name === '') continue;

                $stmt = $pdo->prepare("
                    INSERT IGNORE INTO campers
                    (student_code, full_name, class, phone, phone_parent, email, profile_photo, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ");

                $stmt->execute([
                    $student_code,
                    $full_name,
                    $class,
                    $phone,
                    $phone_parent,
                    $email,
                    $profile_photo
                ]);
            }

            $pdo->commit();

        } catch (Exception $e) {
            $pdo->rollBack();
            die('Lỗi import Excel: ' . $e->getMessage());
        }
    }
}
/* ===== DANH SÁCH ===== */
$campers = $pdo->query("
    SELECT student_code, full_name, phone, phone_parent, class, email, profile_photo, is_active
    FROM campers
    ORDER BY is_active DESC, full_name
")->fetchAll(PDO::FETCH_ASSOC);

// 1️⃣ Kiểm tra dữ liệu thiếu
foreach ($campers as &$c) {

    $missing = [];

    if (!isset($c['full_name']) || trim($c['full_name']) === '')
        $missing[] = 'Họ tên';

    if (!isset($c['class']) || trim($c['class']) === '')
        $missing[] = 'Lớp';

    if (!isset($c['phone']) || trim($c['phone']) === '')
        $missing[] = 'SĐT';

    if (!isset($c['phone_parent']) || trim($c['phone_parent']) === '')
        $missing[] = 'SĐT phụ huynh';

    if (!isset($c['email']) || trim($c['email']) === '')
        $missing[] = 'Email';

    if (
        !isset($c['profile_photo']) ||
        !filter_var(trim($c['profile_photo']), FILTER_VALIDATE_URL)
    ) {
        $missing[] = 'Ảnh đại diện';
    }

    $c['data_status']    = empty($missing) ? 'DU' : 'THIEU';
    $c['missing_fields'] = $missing;
    $c['missing_text']   = implode(', ', $missing);
    $c['missing_count']  = count($missing);
}

unset($c); // QUAN TRỌNG khi dùng &


// 2️⃣ Sắp xếp danh sách
usort($campers, function ($a, $b) {

    // Ưu tiên đủ dữ liệu trước
    if ($a['data_status'] !== $b['data_status']) {
        return $a['data_status'] === 'DU' ? -1 : 1;
    }

    // Hàm parse lớp học
    $parse = function ($class) {
        if (is_string($class) && preg_match('/^(\d+)([A-Z]+)(\d+)$/i', trim($class), $m)) {
            return [
                'grade'  => (int)$m[1],               // Khối
                'letter' => strtoupper($m[2]),        // A, B, C
                'num'    => (int)$m[3]                // 1, 10
            ];
        }
        // Dữ liệu lỗi đẩy xuống cuối
        return ['grade' => 0, 'letter' => 'Z', 'num' => 999];
    };

    $ca = $parse($a['class'] ?? '');
    $cb = $parse($b['class'] ?? '');

    // 1️⃣ Khối: 12 → 11 → 10
    if ($ca['grade'] !== $cb['grade']) {
        return $cb['grade'] <=> $ca['grade'];
    }

    // 2️⃣ Chữ lớp: A → B → C
    if ($ca['letter'] !== $cb['letter']) {
        return strcmp($ca['letter'], $cb['letter']);
    }

    // 3️⃣ Số lớp: 1 → 10
    return $ca['num'] <=> $cb['num'];
});

?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Quản lý trại sinh</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
body { background:#f4faff; }
.card { border-radius:14px; }
.table td { vertical-align:middle; }
.badge-active { background:#eafaf1;opacity: 1; color:#2ecc71; }
.badge-inactive { background:#fdecea;opacity: 1; color:#e74c3c; }
@media(max-width:768px){
    .desktop-only { display:none; }
}
@media(min-width:769px){
    .mobile-only { display:none; }
}
.badge {
    opacity: 1 !important;
    filter: none !important;
}

/* CHECK IN */
.badge-active {
    background-color: #eafaf1 !important;
    color: #2ecc71 !important;
    font-weight: 700;
}

/* CHECK OUT */
.badge-inactive {
    background-color: #fdecea !important;
    color: #e74c3c !important;
    font-weight: 700;}

/* Mobile Styles */
@media (max-width: 768px) {
  .list-group-item {
    background-color: #ffffff;
    border: 1px solid #eaeaea;
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 10px;
  }

  .list-group-item .fw-bold {
    font-size: 1rem;
  }

  .list-group-item .badge {
    display: inline-block;
    margin: 6px 0;
  }

  .list-group-item .btn {
    font-size: 0.9rem;
  }

  .list-group-item .text-muted {
    font-size: 0.9rem;
  }

  #tableBody {
    display: none; /* Ẩn bảng Desktop trên Mobile */
  }
}

@media (min-width: 769px) {
  #mobileList {
    display: none !important; /* Ẩn danh sách Mobile trên Desktop */
  }
}
</style>
</head>

<body>
<?php
$pageTitle = "Chỉnh sửa thông tin trại sinh";
$full_name = $_SESSION['full_name'] ?? '';
include __DIR__ . '/../config/header.php';
?>
<div class="container py-4">
<!-- ===== THÊM 1 ===== -->
<div class="card mb-4">
<div class="card-body">
<h5 class="mb-3">➕ Thêm trại sinh</h5>
<form method="post" class="row g-2">
<input type="hidden" name="add_one">
<div class="col-md-2">
<input class="form-control" name="student_code" placeholder="Mã" required>
</div>
<div class="col-md-3">
<input class="form-control" name="full_name" placeholder="Họ tên" required>
</div>
<div class="col-md-2">
<input class="form-control" name="class" placeholder="Lớp">
</div>
<div class="col-md-2">
<input class="form-control" name="phone" placeholder="Số điện thoại">
</div>
<div class="col-md-2">
<input class="form-control" name="phone_parent" placeholder="Số điện thoại phụ huynh">
</div>
<div class="col-md-3">
<input class="form-control" name="email" placeholder="Email">
</div>
<div class="col-12">
<input class="form-control" name="profile_photo" placeholder="Đường dẫn ảnh (tuỳ chọn)">
</div>
<div class="col-12">
<button class="btn btn-primary w-100">Thêm trại sinh</button>
</div>
</form>
</div>
</div>

<!-- ===== IMPORT Excel ===== -->
<div class="card mb-4">
<div class="card-body">
<h5 class="mb-3">📥 Import Excel</h5>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="import_excel">
<input type="file" name="excel" accept=".xls,.xlsx" required>
<button class="btn btn-success mt-2">Import</button>
</form>
</div>
</div>

<a href="uploads/DanhsachTraisinhmau.xlsx"
   class="btn btn-outline-primary btn-sm mb-2"
   download>
   <i class="bi bi-download"></i> Tải file Excel mẫu
</a>


<!-- ===== SEARCH ===== -->
<input type="text" id="search" class="form-control mb-3" placeholder="Tìm theo mã hoặc tên...">
<a href="api/export_campers_excel.php"
   class="btn btn-success mb-3">
   <i class="bi bi-file-earmark-excel"></i>
   Xuất danh sách trại sinh (Excel)
</a>

<!-- ===== LIST ===== -->
<div class="card">
<div class="card-body">
<h5 class="mb-3">Danh sách trại sinh</h5>

<table class="table table-hover desktop-only">
<thead>
<tr>
  <th>Mã</th>
  <th>Họ tên</th>
  <th>Lớp</th>
  <th>Số điện thoại</th>
  <th>Trạng thái</th>
  <th>Thao tác</th>
</tr>
</thead>

<tbody id="tableBody">
<?php foreach ($campers as $c): ?>
<tr>
  <td><?= $c['student_code'] ?></td>
  <td><?= $c['full_name'] ?></td>
    <td><?= $c['class'] ?></td>
  <td><?= $c['phone'] ?></td>
  <td>
    <?php if ($c['data_status'] === 'DU'): ?>
        <span class="badge bg-success">
            Đã đủ dữ liệu
        </span>
    <?php else: ?>
        <span class="badge bg-warning text-dark"
            title="Thiếu: <?= implode(', ', $c['missing_fields']) ?>">
            Thiếu dữ liệu
        </span>
    <?php endif; ?>
  </td>
  <td>
  <?php if ($c['is_active']): ?>
    <button class="btn btn-sm btn-warning me-1"
        onclick='openEdit(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
        <i class="bi bi-pencil"></i>
    </button>

    <a href="?disable=<?= $c['student_code'] ?>"
       onclick="return confirm('Xoá trại sinh này?')"
       class="btn btn-sm btn-danger">
       <i class="bi bi-trash"></i>
    </a>
    <?php if ($_SESSION['role'] === 'admin'): ?>
    <a href="?delete_forever=<?= $c['student_code'] ?>"
       onclick="return confirm('⚠️ XOÁ VĨNH VIỄN trại sinh này?\nDữ liệu điểm danh sẽ bị xoá hết!')"
       class="btn btn-sm btn-dark mt-1">
       <i class="bi bi-x-octagon"></i> Xoá vĩnh viễn
    </a>
    <?php endif; ?>

  <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
<?php if ($_SESSION['role'] === 'admin'): ?>
<tr>
<td colspan="6">
<form method="post"
      onsubmit="return confirm(
        '⚠️ XOÁ TOÀN BỘ TRẠI SINH?\n\n' +
        '• Xoá tất cả trại sinh\n' +
        '• Xoá toàn bộ lịch sử CHECK IN / OUT\n' +
        '• KHÔNG THỂ KHÔI PHỤC\n\n' +
        'Bạn có chắc chắn không?'
      );">
    <button type="submit"
            name="delete_all"
            class="btn btn-danger">
        <i class="bi bi-trash3"></i> Xoá tất cả trại sinh
    </button>
</form>
</td>
</tr>
<?php endif; ?>

</tbody>
</table>


<!-- MOBILE -->
<div class="d-lg-none list-group" id="mobileList">
  <?php foreach ($campers as $c): ?>
  <div class="list-group-item camper-row">
    <div class="d-flex justify-content-between">
      <div>
        <span class="fw-bold camper-name"><?= $c['full_name'] ?></span>
        (<span class="camper-class"><?= $c['class'] ?></span>)
      </div>
      <!-- Thay đổi trạng thái như desktop -->
      <div>
        <?php if ($c['data_status'] === 'DU'): ?>
          <span class="badge bg-success">Đủ dữ liệu</span>
        <?php else: ?>
          <span class="badge bg-warning text-dark">Thiếu dữ liệu</span>
        <?php endif; ?>
      </div>
    </div>
    <div class="text-muted">SĐT: <?= $c['phone'] ?></div>
    <div class="text-muted">SĐT phụ huynh: <?= $c['phone_parent'] ?></div>
    <div class="mt-2">
      <?php if ($c['is_active']): ?>
        <button class="btn btn-sm btn-warning me-1 camper-edit"
                onclick='openEdit(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
          <i class="bi bi-pencil"></i> Sửa
        </button>
        <a href="?disable=<?= $c['student_code'] ?>"
           onclick="return confirm('Xoá trại sinh này?')"
           class="btn btn-sm btn-danger">
           <i class="bi bi-trash"></i> Xoá
        </a>
        <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="?delete_forever=<?= $c['student_code'] ?>"
           onclick="return confirm('⚠️ XOÁ VĨNH VIỄN trại sinh?')"
           class="btn btn-sm btn-dark mt-1">
           <i class="bi bi-x-octagon"></i> Xoá vĩnh viễn
        </a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
</div>

</div>
<div class="modal fade" id="editModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">

<div class="modal-header">
<h5 class="modal-title">Sửa thông tin trại sinh</h5>
<button class="btn-close" data-bs-dismiss="modal"></button>
</div>

<form id="editForm" enctype="multipart/form-data">
<div class="modal-body">

<input type="hidden" name="student_code" id="e_code">

<div class="mb-2">
<label>Họ tên</label>
<input class="form-control" name="full_name" id="e_name" required>
</div>

<div class="mb-2">
<label>Lớp</label>
<input class="form-control" name="class" id="e_class">
</div>

<div class="mb-2">
<label>Số điện thoại</label>
<input class="form-control" name="phone" id="e_phone">
</div>

<div class="mb-2">
<label>Số điện thoại phụ huynh</label>
<input class="form-control" name="phone_parent" id="e_parent">
</div>

<div class="mb-2">
<label>Email</label>
<input class="form-control" name="email" id="e_email">
</div>

<div class="mb-2">
  <label>Ảnh đại diện (Cloudinary)</label>
  <input type="text" name="profile_photo" id="e_avatar" class="form-control" placeholder="Dán link Cloudinary avatar">
</div>

<div class="modal-footer">
<button class="btn btn-primary">Lưu</button>
</div>
</form>

</div>
</div>
</div>
<?php include __DIR__ . '/../config/footer.php'; ?>

<script>
const search = document.getElementById('search');
search.addEventListener('input', () => {
    const q = search.value.toLowerCase().trim();

    // Tìm kiếm trong bảng Desktop (Table)
    document.querySelectorAll('#tableBody tr').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });

    // Tìm kiếm trong danh sách Mobile
    document.querySelectorAll('#mobileList .list-group-item').forEach(item => {
        const name = item.querySelector('.camper-name').textContent.toLowerCase();
        const cls = item.querySelector('.camper-class').textContent.toLowerCase();

        item.style.display = (name.includes(q) || cls.includes(q)) ? '' : 'none';
    });
});
function openEdit(data) {
    document.getElementById('e_code').value = data.student_code;
    document.getElementById('e_name').value = data.full_name || '';
    document.getElementById('e_class').value = data.class || '';
    document.getElementById('e_phone').value = data.phone || '';
    document.getElementById('e_parent').value = data.phone_parent || '';
    document.getElementById('e_email').value = data.email || '';
    document.getElementById('e_avatar').value = data.profile_photo || '';

    new bootstrap.Modal(document.getElementById('editModal')).show();
}

document.getElementById('search').addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();

    document.querySelectorAll('.camper-row').forEach(row => {
        const name  = row.querySelector('.camper-name')?.innerText.toLowerCase() || '';
        const cls   = row.querySelector('.camper-class')?.innerText.toLowerCase() || '';

        row.style.display = (name.includes(q) || cls.includes(q)) ? '' : 'none';
    });
});

document.getElementById('editForm').addEventListener('submit', function(e){
    e.preventDefault();
    const formData = new FormData(this);

    fetch('api/update_camper.php', {
        method: 'POST',
        body: formData
    })
    .then(r=>r.json())
    .then(res=>{
        if(res.success){
            location.reload();
        } else {
            alert(res.message || 'Lỗi');
        }
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>