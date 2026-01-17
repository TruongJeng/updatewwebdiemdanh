<?php
session_start();
require __DIR__ . '/../includes/db.php';

// Chỉ cho admin vào
if ($_SESSION['role'] !== 'admin') {
    header('Location: /dashboard.php');
    exit();
}

// Xử lý sắp xếp (sort)
$sortable = ['id','username','full_name','role','email'];
$order_by = in_array($_GET['sort'] ?? '', $sortable) ? $_GET['sort'] : 'id';
$order_dir = ($_GET['dir'] ?? 'asc') === 'asc' ? 'asc' : 'desc';

$msg = '';
$error = '';

// Xử lý xóa tài khoản
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    // Không cho phép xóa chính mình
    if ($delete_id == $_SESSION['user_id']) {
        $error = "Bạn không thể tự xóa chính mình!";
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
        $stmt->execute([$delete_id]);
        $msg = "Đã xóa tài khoản!";
    }
}

// Xử lý sửa tài khoản
if (isset($_POST['edit_user'])) {
    $uid = (int)$_POST['uid'];
    $field = $_POST['field'];
    $value = trim($_POST['value']);

    if ($field === 'username') {
        if ($value) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username=? AND id<>?");
            $stmt->execute([$value, $uid]);
            if ($stmt->fetch()) {
                $error = "Tên đăng nhập đã tồn tại!";
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username=? WHERE id=?");
                $stmt->execute([$value, $uid]);
                $msg = "Đã cập nhật tên đăng nhập!";
            }
        } else {
            $error = "Không được để trống tên đăng nhập!";
        }
    }
    if ($field === 'full_name') {
        if ($value) {
            $stmt = $pdo->prepare("UPDATE users SET full_name=? WHERE id=?");
            $stmt->execute([$value, $uid]);
            $msg = "Đã cập nhật họ tên!";
        } else {
            $error = "Không được để trống họ tên!";
        }
    }
    if ($field === 'role') {
        if (in_array($value, ['admin','teacher','club_leader','student'])) {
            $stmt = $pdo->prepare("UPDATE users SET role=? WHERE id=?");
            $stmt->execute([$value, $uid]);
            $msg = "Đã cập nhật quyền!";
        } else {
            $error = "Quyền không hợp lệ!";
        }
    }
    if ($field === 'email') {
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $stmt = $pdo->prepare("UPDATE users SET email=? WHERE id=?");
            $stmt->execute([$value, $uid]);
            $msg = "Đã cập nhật email!";
        } else {
            $error = "Email không hợp lệ!";
        }
    }
    if ($field === 'password') {
        if ($value) {
            $hash = password_hash($value, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?");
            $stmt->execute([$hash, $uid]);
            $msg = "Đã đổi mật khẩu!";
        } else {
            $error = "Mật khẩu mới không được để trống!";
        }
    }
}

// Thêm tài khoản mới
if (isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $role = $_POST['role'];
    $password = $_POST['password'];
    $email = trim($_POST['email']);
    if ($username && $full_name && $role && $password && $email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Email không hợp lệ!";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username=?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = "Tên đăng nhập đã tồn tại!";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, full_name, role, password_hash, email) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$username, $full_name, $role, $hash, $email]);
                $msg = "Đã thêm tài khoản mới! Mật khẩu: <b>$password</b>";
            }
        }
    } else {
        $error = "Vui lòng điền đầy đủ thông tin!";
    }
}

// Lấy danh sách tài khoản (theo sort)
$users = $pdo->query("SELECT * FROM users ORDER BY $order_by $order_dir")->fetchAll(PDO::FETCH_ASSOC);

// Hàm tạo link sort
function sort_link($col, $label, $order_by, $order_dir) {
    $dir = ($order_by == $col && $order_dir == 'asc') ? 'desc' : 'asc';
    $icon = '';
    if ($order_by == $col) {
        $icon = $order_dir == 'asc' ? '<i class="bi bi-arrow-down"></i>' : '<i class="bi bi-arrow-up"></i>';
    }
    return "<a href=\"?sort=$col&dir=$dir\" style=\"text-decoration:none;color:inherit;\">$label $icon</a>";
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>CLB Kỹ năng Đoàn - Hội Trường THPT Lý Thường Kiệt</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="/assets/logo_CLB.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #e8f1fb; }
        .container-user { max-width: 950px; margin: 40px auto 24px auto; background: #fff; border-radius: 14px; box-shadow: 0 4px 24px #3178c615, 0 1.5px 8px #a8c8f088; padding: 32px 18px 26px 18px;}
        .btn-main { background: #6fa6e3; color: #fff; font-weight: 600; border-radius: 8px; transition: background 0.2s;}
        .btn-main:hover { background: #3178c6;}
        .table thead { background: #a8c8f0; color: #3178c6; }
        .table tbody tr td { vertical-align: middle; }
        .back-link { color: #3178c6; text-decoration: none;}
        .back-link:hover { text-decoration: underline; color: #1757a6;}
        .logout-link { color: #e72c2c; float:right; text-decoration:none;}
        .logout-link:hover { text-decoration: underline;}
        .eye-btn { background: none; border: none; color: #3178c6; font-size: 1.2em; cursor: pointer; }
        @media (max-width: 600px) {
            .container-user { padding: 10px 2vw; }
        }
    </style>
</head>
<body>
<?php
$pageTitle = "QUẢN LÝ TÀI KHOẢN";
$full_name = $_SESSION['full_name'] ?? '';
include '../includes/header.php';
?>
<div class="container-user shadow-sm">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="../dashboard.php" class="back-link"><i class="bi bi-arrow-left-circle"></i> Về Trang chủ</a>
    </div>
    <h2 class="mb-4 mt-2" style="color:#3178c6;font-weight:700;"><i class="bi bi-person-gear"></i> Quản lý tài khoản</h2>
    <?php if ($msg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
    <?php endif; ?>

    <h4 class="mt-3 mb-2" style="color:#3178c6;">Thêm tài khoản mới</h4>
    <form method="post" class="row g-3 mb-4">
        <div class="col-md-2">
            <input type="text" name="username" class="form-control" placeholder="Tên đăng nhập" required>
        </div>
        <div class="col-md-3">
            <input type="text" name="full_name" class="form-control" placeholder="Họ tên đầy đủ" required>
        </div>
        <div class="col-md-2">
            <select name="role" class="form-select" required>
                <option value="">Chọn quyền</option>
                <option value="admin">Admin</option>
                <option value="teacher">Giáo viên</option>
                <option value="club_leader">Ban chủ nhiệm</option>
                <option value="student">Học sinh</option>
            </select>
        </div>
        <div class="col-md-3">
            <input type="email" name="email" class="form-control" placeholder="Email" required>
        </div>
        <div class="col-md-2 position-relative">
            <input type="password" name="password" class="form-control" id="add_pass" placeholder="Mật khẩu" required>
            <button type="button" class="eye-btn position-absolute top-0 end-0" style="z-index:2;" onclick="toggleAddPass()" tabindex="-1">
                <i class="bi bi-eye-slash" id="addPassIcon"></i>
            </button>
        </div>
        <div class="col-12">
            <button type="submit" name="add_user" class="btn btn-main w-100 mt-1"><i class="bi bi-person-plus"></i> Thêm tài khoản</button>
        </div>
    </form>

    <h4 class="mb-2" style="color:#3178c6;">Danh sách tài khoản</h4>
    <div class="table-responsive">
    <table class="table table-bordered align-middle">
        <thead>
            <tr>
                <th><?= sort_link('id', 'ID', $order_by, $order_dir) ?></th>
                <th><?= sort_link('username', 'Tên đăng nhập', $order_by, $order_dir) ?></th>
                <th><?= sort_link('full_name', 'Tên đầy đủ', $order_by, $order_dir) ?></th>
                <th><?= sort_link('role', 'Quyền', $order_by, $order_dir) ?></th>
                <th><?= sort_link('email', 'Email', $order_by, $order_dir) ?></th>
                <th class="text-center">Sửa</th>
                <th class="text-center">Xóa</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($users as $u): ?>
            <tr>
                <td><?= $u['id'] ?></td>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><?= htmlspecialchars($u['full_name']) ?></td>
                <td>
                <?php
                switch($u['role']) {
                    case 'admin': echo 'Quản trị viên'; break;
                    case 'teacher': echo 'Giáo viên/Giảng viên'; break;
                    case 'club_leader': echo 'Ban chủ nhiệm'; break;
                    case 'student': echo 'Học sinh'; break;
                    default: echo htmlspecialchars($u['role']);
                }
                ?>
                </td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td class="text-center">
                    <button class="btn btn-sm btn-primary"
                        onclick="showEditModal(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['username'])) ?>', '<?= htmlspecialchars(addslashes($u['full_name'])) ?>', '<?= $u['role'] ?>', '<?= htmlspecialchars(addslashes($u['email'])) ?>')">
                        <i class="bi bi-pencil-square"></i> Sửa
                    </button>
                </td>
                <td class="text-center">
                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                    <a href="users.php?delete_id=<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bạn có chắc muốn xóa tài khoản này?');">
                        <i class="bi bi-trash"></i> Xóa
                    </a>
                    <?php else: ?>
                    <span class="text-muted"><i class="bi bi-person-lock"></i></span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Modal sửa -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content" id="editForm">
      <div class="modal-header">
        <h5 class="modal-title">Sửa tài khoản</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="uid" id="edit_uid">
        <div class="mb-3">
            <label class="form-label">Trường muốn sửa</label>
            <select name="field" class="form-select" id="fieldSelect" required onchange="updateEditField()">
                <option value="username">Tên đăng nhập</option>
                <option value="full_name">Họ tên</option>
                <option value="role">Quyền</option>
                <option value="email">Email</option>
                <option value="password">Mật khẩu</option>
            </select>
        </div>
        <div class="mb-3" id="fieldInputDiv">
            <label class="form-label" id="valueLabel">Giá trị mới</label>
            <input type="text" name="value" class="form-control" id="edit_value" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="edit_user" class="btn btn-main">Lưu</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
      </div>
    </form>
  </div>
  <?php include '../includes/footer.php'; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleAddPass() {
    let input = document.getElementById('add_pass');
    let icon = document.getElementById('addPassIcon');
    if(input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    }
}

function showEditModal(id, username, fullname, role, email) {
    document.getElementById('edit_uid').value = id;
    document.getElementById('fieldSelect').value = 'username';
    updateEditField();
    document.getElementById('edit_value').value = username;
    var myModal = new bootstrap.Modal(document.getElementById('editModal'));
    myModal.show();

    // Lưu thêm các giá trị để có thể lấy ra khi chuyển trường sửa
    window.editUserInfo = {username, fullname, role, email};
}

function updateEditField() {
    var field = document.getElementById('fieldSelect').value;
    var parentDiv = document.getElementById('fieldInputDiv');
    let value = '';
    if(window.editUserInfo) {
        if(field==='username') value = window.editUserInfo.username;
        if(field==='full_name') value = window.editUserInfo.fullname;
        if(field==='role') value = window.editUserInfo.role;
        if(field==='email') value = window.editUserInfo.email;
    }
    parentDiv.innerHTML = '';
    if (field === 'username') {
        parentDiv.innerHTML = '<label class="form-label">Tên đăng nhập mới</label><input type="text" name="value" class="form-control" id="edit_value" value="'+(value||'')+'" required>';
    } else if (field === 'full_name') {
        parentDiv.innerHTML = '<label class="form-label">Họ tên mới</label><input type="text" name="value" class="form-control" id="edit_value" value="'+(value||'')+'" required>';
    } else if (field === 'role') {
        parentDiv.innerHTML = `<label class="form-label">Quyền mới</label>
        <select name="value" class="form-select" id="edit_value" required>
            <option value="admin"${value==='admin'?' selected':''}>Admin</option>
            <option value="teacher"${value==='teacher'?' selected':''}>Giáo viên</option>
            <option value="club_leader"${value==='club_leader'?' selected':''}>Ban chủ nhiệm</option>
            <option value="student"${value==='student'?' selected':''}>Học sinh</option>
        </select>`;
    } else if (field === 'email') {
        parentDiv.innerHTML = '<label class="form-label">Email mới</label><input type="email" name="value" class="form-control" id="edit_value" value="'+(value||'')+'" required>';
    } else if (field === 'password') {
        parentDiv.innerHTML = `<label class="form-label">Mật khẩu mới</label>
        <div class="position-relative">
            <input type="password" name="value" class="form-control" id="edit_value" required>
            <button type="button" class="eye-btn position-absolute top-0 end-0" style="z-index:2;" onclick="toggleEditPass()" tabindex="-1">
                <i class="bi bi-eye-slash" id="editPassIcon"></i>
            </button>
        </div>`;
    }
}

function toggleEditPass() {
    let input = document.getElementById('edit_value');
    let icon = document.getElementById('editPassIcon');
    if(input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    }
}
</script>
</body>
</html>