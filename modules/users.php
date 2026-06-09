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
<?php
$pageTitle = "QUẢN LÝ TÀI KHOẢN";
$full_name = $_SESSION['full_name'] ?? '';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<main class="ml-0 lg:ml-64 pt-4 min-h-screen bg-slate-50/50 transition-all duration-300 ease-in-out p-4 sm:p-6 lg:p-8">
    <div class="max-w-7xl mx-auto pb-12">
        <div class="flex items-center gap-3 mb-6">
            <a href="../dashboard.php" class="text-slate-500 hover:text-primary-600 transition-colors flex items-center gap-1.5 text-sm font-medium bg-white px-3 py-1.5 rounded-full border border-slate-200 shadow-sm hover:shadow">
                <i class="bi bi-arrow-left"></i> Về Trang chủ
            </a>
        </div>
        
        <div class="flex items-center gap-3 mb-8">
            <div class="w-12 h-12 rounded-xl bg-primary-100 text-primary-600 flex items-center justify-center shadow-sm">
                <i class="bi bi-person-gear text-2xl"></i>
            </div>
            <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">QUẢN LÝ TÀI KHOẢN</h2>
        </div>
        
        <!-- Alerts -->
        <?php if ($msg): ?>
            <div class="mb-6 flex items-center justify-between p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 rounded-r-lg shadow-sm">
                <div class="flex items-center gap-2">
                    <i class="bi bi-check-circle-fill text-lg"></i>
                    <span class="font-medium"><?= htmlspecialchars($msg) ?></span>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mb-6 flex items-center justify-between p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-r-lg shadow-sm">
                <div class="flex items-center gap-2">
                    <i class="bi bi-exclamation-circle-fill text-lg"></i>
                    <span class="font-medium"><?= htmlspecialchars($error) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Alpine State for Add Form & Edit Modal -->
        <div x-data="{ showAddForm: false, showEditModal: false, editUser: {} }">
            
            <div class="flex justify-end mb-6">
                <button x-show="!showAddForm" @click="showAddForm = true" class="flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-5 py-2.5 rounded-lg font-semibold transition-all shadow-sm hover:shadow-md text-sm">
                    <i class="bi bi-person-plus"></i> Thêm tài khoản mới
                </button>
            </div>

            <!-- Add User Form -->
            <div x-show="showAddForm" x-collapse x-cloak class="mb-8">
                <div class="bg-white rounded-2xl p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100">
                    <h3 class="text-lg font-bold text-slate-800 mb-5 flex items-center gap-2">
                        <i class="bi bi-person-plus text-primary-500"></i> Tạo tài khoản
                    </h3>
                    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-5" x-data="{ showPass: false }">
                        <div class="xl:col-span-1">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Tên đăng nhập</label>
                            <input type="text" name="username" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 outline-none">
                        </div>
                        
                        <div class="xl:col-span-1 md:col-span-2">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Họ tên đầy đủ</label>
                            <input type="text" name="full_name" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 outline-none">
                        </div>
                        
                        <div class="xl:col-span-1">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Quyền</label>
                            <select name="role" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 outline-none">
                                <option value="">Chọn quyền...</option>
                                <option value="admin">Admin</option>
                                <option value="teacher">Giáo viên</option>
                                <option value="club_leader">Ban chủ nhiệm</option>
                                <option value="student">Học sinh</option>
                            </select>
                        </div>
                        
                        <div class="xl:col-span-1">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Mật khẩu</label>
                            <div class="relative">
                                <input :type="showPass ? 'text' : 'password'" name="password" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 outline-none">
                                <button type="button" @click="showPass = !showPass" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                    <i class="bi" :class="showPass ? 'bi-eye' : 'bi-eye-slash'"></i>
                                </button>
                            </div>
                        </div>

                        <div class="xl:col-span-1 lg:col-span-2">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Email</label>
                            <input type="email" name="email" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 outline-none">
                        </div>
                        
                        <div class="xl:col-span-5 lg:col-span-4 md:col-span-2 mt-2 pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                            <button type="button" @click="showAddForm = false" class="px-5 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100 transition-colors">Hủy</button>
                            <button type="submit" name="add_user" class="px-5 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-sm font-semibold shadow-sm transition-all flex items-center gap-2">
                                <i class="bi bi-person-plus"></i> Thêm tài khoản
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 overflow-hidden mb-6">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <h3 class="text-base font-bold text-slate-800">Danh sách tài khoản</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase text-xs tracking-wider">
                            <tr>
                                <th class="px-5 py-4 w-16 text-center"><?= sort_link('id', 'ID', $order_by, $order_dir) ?></th>
                                <th class="px-5 py-4"><?= sort_link('username', 'Tên đăng nhập', $order_by, $order_dir) ?></th>
                                <th class="px-5 py-4"><?= sort_link('full_name', 'Họ tên', $order_by, $order_dir) ?></th>
                                <th class="px-5 py-4"><?= sort_link('role', 'Quyền', $order_by, $order_dir) ?></th>
                                <th class="px-5 py-4"><?= sort_link('email', 'Email', $order_by, $order_dir) ?></th>
                                <th class="px-5 py-4 text-center w-32">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $u): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="px-5 py-3.5 text-center font-medium text-slate-500"><?= $u['id'] ?></td>
                                    <td class="px-5 py-3.5 font-bold text-slate-800">
                                        <?= htmlspecialchars($u['username']) ?>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <?= htmlspecialchars($u['full_name']) ?>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <?php
                                        switch($u['role']) {
                                            case 'admin': 
                                                echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Quản trị viên</span>';
                                                break;
                                            case 'teacher': 
                                                echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Giáo viên</span>';
                                                break;
                                            case 'club_leader': 
                                                echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Ban chủ nhiệm</span>';
                                                break;
                                            case 'student': 
                                                echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800">Học sinh</span>';
                                                break;
                                            default: 
                                                echo htmlspecialchars($u['role']);
                                        }
                                        ?>
                                    </td>
                                    <td class="px-5 py-3.5 text-slate-600 truncate max-w-[200px]"><?= htmlspecialchars($u['email']) ?></td>
                                    <td class="px-5 py-3.5 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <button @click="showEditModal = true; editUser = {
                                                    id: <?= $u['id'] ?>, 
                                                    username: '<?= htmlspecialchars(addslashes($u['username'])) ?>',
                                                    full_name: '<?= htmlspecialchars(addslashes($u['full_name'])) ?>',
                                                    role: '<?= $u['role'] ?>',
                                                    email: '<?= htmlspecialchars(addslashes($u['email'])) ?>',
                                                    field: 'username'
                                                }" 
                                                class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white flex items-center justify-center transition-colors" title="Sửa">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                            <a href="users.php?delete_id=<?= $u['id'] ?>" onclick="return confirm('Bạn chắc chắn muốn xóa tài khoản này?')" class="w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-600 hover:text-white flex items-center justify-center transition-colors" title="Xóa">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                            <?php else: ?>
                                            <span class="w-8 h-8 flex items-center justify-center text-slate-300" title="Không thể tự xóa mình">
                                                <i class="bi bi-person-lock"></i>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-slate-500 bg-slate-50/50">
                                    <div class="flex flex-col items-center justify-center">
                                        <i class="bi bi-inbox text-4xl mb-3 text-slate-300"></i>
                                        <p>Chưa có tài khoản nào!</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Edit Modal Overlay -->
            <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center overflow-auto bg-slate-900/50 backdrop-blur-sm"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0">
                
                <!-- Modal Content -->
                <div @click.away="showEditModal = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                     x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                     x-transition:leave-end="opacity-0 scale-95 translate-y-4">
                    
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-slate-800">Sửa thông tin tài khoản</h3>
                        <button @click="showEditModal = false" class="text-slate-400 hover:text-slate-600 transition-colors">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    
                    <form method="post" class="p-6">
                        <input type="hidden" name="uid" :value="editUser.id">
                        
                        <div class="mb-5">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Trường muốn sửa</label>
                            <select name="field" x-model="editUser.field" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:bg-white focus:ring-2 outline-none transition-colors">
                                <option value="username">Tên đăng nhập</option>
                                <option value="full_name">Họ tên</option>
                                <option value="role">Quyền</option>
                                <option value="email">Email</option>
                                <option value="password">Mật khẩu mới</option>
                            </select>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Giá trị mới</label>
                            
                            <!-- Username Input -->
                            <input x-show="editUser.field === 'username'" type="text" name="value" :value="editUser.field === 'username' ? editUser.username : ''" :required="editUser.field === 'username'" :disabled="editUser.field !== 'username'" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 outline-none">
                            
                            <!-- Full Name Input -->
                            <input x-show="editUser.field === 'full_name'" type="text" name="value" :value="editUser.field === 'full_name' ? editUser.full_name : ''" :required="editUser.field === 'full_name'" :disabled="editUser.field !== 'full_name'" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 outline-none">
                            
                            <!-- Email Input -->
                            <input x-show="editUser.field === 'email'" type="email" name="value" :value="editUser.field === 'email' ? editUser.email : ''" :required="editUser.field === 'email'" :disabled="editUser.field !== 'email'" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 outline-none">
                            
                            <!-- Password Input -->
                            <div x-show="editUser.field === 'password'" class="relative" x-data="{ showModalPass: false }">
                                <input :type="showModalPass ? 'text' : 'password'" name="value" :required="editUser.field === 'password'" :disabled="editUser.field !== 'password'" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 outline-none">
                                <button type="button" @click="showModalPass = !showModalPass" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                    <i class="bi" :class="showModalPass ? 'bi-eye' : 'bi-eye-slash'"></i>
                                </button>
                            </div>

                            <!-- Role Select -->
                            <select x-show="editUser.field === 'role'" name="value" :value="editUser.field === 'role' ? editUser.role : ''" :required="editUser.field === 'role'" :disabled="editUser.field !== 'role'" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 outline-none">
                                <option value="admin">Admin</option>
                                <option value="teacher">Giáo viên</option>
                                <option value="club_leader">Ban chủ nhiệm</option>
                                <option value="student">Học sinh</option>
                            </select>
                        </div>
                        
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" @click="showEditModal = false" class="px-5 py-2.5 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100 transition-colors">Hủy</button>
                            <button type="submit" name="edit_user" class="px-5 py-2.5 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-sm font-semibold shadow-sm transition-all flex items-center gap-2">
                                <i class="bi bi-save"></i> Lưu thay đổi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>