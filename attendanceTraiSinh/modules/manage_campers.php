<?php
require_once __DIR__ . '/../../config/session.php';
if (!in_array($_SESSION['role'], ['admin','club_leader'])) {
    die('Không có quyền');
}

require_once __DIR__ . '/../config/db.php';

/* ===== XOÁ MỀM ===== */
if (isset($_GET['disable'])) {
    $stmt = $pdo->prepare("UPDATE campers SET is_active = 0 WHERE student_code = ?");
    $stmt->execute([$_GET['disable']]);
    header("Location: manage_campers.php");
    exit;
}

/* ===== THÊM 1 ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_one'])) {
    $stmt = $pdo->prepare("
        INSERT INTO campers
        (student_code, full_name, phone, phone_parent, email, profile_photo, is_active)
        VALUES (?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([
        $_POST['student_code'],
        $_POST['full_name'],
        $_POST['phone'],
        $_POST['phone_parent'],
        $_POST['email'],
        $_POST['profile_photo']
    ]);
}

/* ===== IMPORT CSV ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_csv'])) {

    if (is_uploaded_file($_FILES['csv']['tmp_name'])) {

        $file = fopen($_FILES['csv']['tmp_name'], 'r');
        $pdo->beginTransaction();

        while (($row = fgetcsv($file, 1000, ",")) !== false) {

            if ($row[0] === 'student_code') continue;

            $stmt = $pdo->prepare("
                INSERT IGNORE INTO campers
                (student_code, full_name, phone, phone_parent, email, profile_photo, is_active)
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $row[0], $row[1], $row[2],
                $row[3], $row[4], $row[5]
            ]);
        }

        fclose($file);
        $pdo->commit();
    }
}

/* ===== DANH SÁCH ===== */
$campers = $pdo->query("
    SELECT student_code, full_name, phone, class, is_active
    FROM campers
    ORDER BY is_active DESC, full_name
")->fetchAll(PDO::FETCH_ASSOC);
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
.badge-active { background:#eafaf1; color:#2ecc71; }
.badge-inactive { background:#fdecea; color:#e74c3c; }
@media(max-width:768px){
    .desktop-only { display:none; }
}
@media(min-width:769px){
    .mobile-only { display:none; }
}
</style>
</head>

<body>
<?php
$pageTitle = "Chỉnh sửa trại sinh";
$full_name = $_SESSION['full_name'] ?? '';
include __DIR__ . '/../../includes/header.php';
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
<input class="form-control" name="phone" placeholder="SĐT">
</div>
<div class="col-md-2">
<input class="form-control" name="phone_parent" placeholder="SĐT PH">
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

<!-- ===== IMPORT CSV ===== -->
<div class="card mb-4">
<div class="card-body">
<h5 class="mb-3">📥 Import CSV</h5>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="import_csv">
<input type="file" name="csv" accept=".csv" required>
<button class="btn btn-success mt-2">Import</button>
</form>
<small class="text-muted">
CSV gồm: student_code, full_name, phone, phone_parent, email, profile_photo
</small>
</div>
</div>



<!-- ===== SEARCH ===== -->
<input type="text" id="search" class="form-control mb-3" placeholder="Tìm theo mã hoặc tên...">

<!-- ===== LIST ===== -->
<div class="card">
<div class="card-body">
<h5 class="mb-3">📋 Danh sách trại sinh</h5>

<table class="table table-hover desktop-only">
<thead>
<tr>
  <th>Mã</th>
  <th>Họ tên</th>
  <th>SĐT</th>
  <th>Trạng thái</th>
  <th>Thao tác</th>
</tr>
</thead>

<tbody id="tableBody">
<?php foreach ($campers as $c): ?>
<tr>
  <td><?= $c['student_code'] ?></td>
  <td><?= $c['full_name'] ?></td>
  <td><?= $c['phone'] ?></td>
  <td>
    <span class="badge <?= $c['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
      <?= $c['is_active'] ? 'Đang có dữ liệu' : 'Đã xoá' ?>
    </span>
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
  <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>


<!-- MOBILE -->
<div class="mobile-only" id="cardList">
<?php foreach ($campers as $c): ?>
<div class="border rounded p-2 mb-2">
<b><?= $c['full_name'] ?></b><br>
<?= $c['student_code'] ?> • <?= $c['phone'] ?><br>
<span class="badge <?= $c['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
<?= $c['is_active'] ? 'Đang trại' : 'Đã xoá' ?>
</span>
</div>
<?php endforeach; ?>
</div>

</div>
</div>

</div>
<div class="modal fade" id="editModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">

<div class="modal-header">
<h5 class="modal-title">Sửa trại sinh</h5>
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
<label>SĐT</label>
<input class="form-control" name="phone" id="e_phone">
</div>

<div class="mb-2">
<label>SĐT phụ huynh</label>
<input class="form-control" name="phone_parent" id="e_parent">
</div>

<div class="mb-2">
<label>Email</label>
<input class="form-control" name="email" id="e_email">
</div>

<div class="mb-2">
<label>Ảnh đại diện</label>
<input class="form-control"
       name="profile_photo_url"
       id="e_avatar"
       placeholder="Dán link Cloudinary avatar">
</div>

</div>

<div class="modal-footer">
<button class="btn btn-primary">Lưu</button>
</div>
</form>

</div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
</div>

<script>
const search = document.getElementById('search');
search.addEventListener('input', () => {
    const q = search.value.toLowerCase();
    document.querySelectorAll('#tableBody tr').forEach(tr => {
        tr.style.display = tr.innerText.toLowerCase().includes(q) ? '' : 'none';
    });
});
function openEdit(data) {
    document.getElementById('e_code').value = data.student_code;
    document.getElementById('e_name').value = data.full_name || '';
    document.getElementById('e_phone').value = data.phone || '';
    document.getElementById('e_parent').value = data.phone_parent || '';
    document.getElementById('e_email').value = data.email || '';

    new bootstrap.Modal(document.getElementById('editModal')).show();
}

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
