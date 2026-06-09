<?php
require_once __DIR__ . '/../config/session.php';
require __DIR__ . '/../includes/db.php';

// Hàm sinh mã PIN random, không trùng
function generateUniquePin($pdo, $length = 6) {
    do {
        $pin = '';
        for ($i = 0; $i < $length; $i++) $pin .= rand(0,9);
        $stmt = $pdo->prepare("SELECT id FROM events WHERE pin = ?");
        $stmt->execute([$pin]);
        $exists = $stmt->fetch();
    } while($exists);
    return $pin;
}

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
// Chỉ cho phép admin, giáo viên, ban chủ nhiệm truy cập
if (!in_array($_SESSION['role'], ['admin', 'teacher', 'club_leader'])) {
    header("Location: ../dashboard.php");
    exit("Bạn không có quyền truy cập chức năng này!");
}

// Xử lý thêm sự kiện
$addMsg = '';
$addMsgType = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_event'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_date = $_POST['event_date'] ?? '';

    if ($title && $event_date) {
        $pin = generateUniquePin($pdo, 6); // Sinh mã PIN 6 số
        $stmt = $pdo->prepare("INSERT INTO events (title, pin, description, event_date, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $pin, $description, $event_date, $_SESSION['user_id']]);
        $addMsg = "Tạo sự kiện thành công! Mã PIN: <b>$pin</b>";
        $addMsgType = "success";
    } else {
        $addMsg = "Vui lòng nhập đủ tiêu đề và ngày diễn ra!";
        $addMsgType = "danger";
    }
}


if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $search = trim($_GET['q'] ?? '');
        $params = [];
        $where = '';
        if ($search !== '') {
            $where = "WHERE (e.title LIKE ? OR e.pin LIKE ? OR e.event_date LIKE ?)";
            $search_like = "%$search%";
            $params = [$search_like, $search_like, $search_like];
        }

        $sql = "SELECT e.id, e.title, e.pin, e.event_date FROM events e $where ORDER BY e.event_date DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'events' => $events]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Không thể tải danh sách sự kiện.']);
    }
    exit;
}
// Xử lý xóa sự kiện
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM attendance WHERE event_id = ?");
    $stmt->execute([$delete_id]);
    $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
    $stmt->execute([$delete_id]);
    $addMsg = "Đã xóa sự kiện!";
    $addMsgType = "success";
}

// Xử lý sửa sự kiện
$editEvent = null;
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$edit_id]);
    $editEvent = $stmt->fetch();
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_event'])) {
    $edit_id = (int)$_POST['edit_id'];
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_date = $_POST['event_date'] ?? '';
    $pin = trim($_POST['pin'] ?? '');

    if ($title && $event_date && $pin) {
        // Kiểm tra mã PIN trùng, loại trừ sự kiện hiện tại
        $stmt = $pdo->prepare("SELECT id FROM events WHERE pin = ? AND id != ?");
        $stmt->execute([$pin, $edit_id]);
        if ($stmt->fetch()) {
            $addMsg = "Mã PIN đã được sử dụng cho sự kiện khác!";
            $addMsgType = "danger";
        } else {
            $stmt = $pdo->prepare("UPDATE events SET title=?, pin=?, description=?, event_date=? WHERE id=?");
            $stmt->execute([$title, $pin, $description, $event_date, $edit_id]);
            $addMsg = "Cập nhật sự kiện thành công!";
            $addMsgType = "success";
            $editEvent = null; // Ẩn form sửa sau khi cập nhật
        }
    } else {
        $addMsg = "Vui lòng nhập đủ tiêu đề, mã PIN và ngày diễn ra!";
        $addMsgType = "danger";
    }
}

// Xử lý tìm kiếm
$search = trim($_GET['q'] ?? '');
$params = [];
$where = '';
if ($search !== '') {
    $where = "WHERE (e.title LIKE ? OR e.pin LIKE ? OR e.event_date LIKE ?)";
    $search_like = "%$search%";
    $params = [$search_like, $search_like, $search_like];
}

// Lấy danh sách sự kiện (có tìm kiếm)
$sql = "SELECT e.*, u.full_name AS creator FROM events e LEFT JOIN users u ON e.created_by = u.id $where ORDER BY event_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

$pageTitle = "QUẢN LÝ SỰ KIỆN";
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
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-primary-100 text-primary-600 flex items-center justify-center shadow-sm">
                    <i class="bi bi-calendar-event text-2xl"></i>
                </div>
                <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">QUẢN LÝ SỰ KIỆN</h2>
            </div>
            
            <form method="get" class="relative max-w-sm w-full">
                <i class="bi bi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm tên, mã PIN, ngày..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 shadow-sm transition-all">
            </form>
        </div>
        
        <!-- Alerts -->
        <?php if ($addMsg): ?>
            <div class="mb-6 flex items-center justify-between p-4 bg-<?= $addMsgType == 'success' ? 'emerald' : 'red' ?>-50 border-l-4 border-<?= $addMsgType == 'success' ? 'emerald' : 'red' ?>-500 text-<?= $addMsgType == 'success' ? 'emerald' : 'red' ?>-700 rounded-r-lg shadow-sm">
                <div class="flex items-center gap-2">
                    <i class="bi bi-<?= $addMsgType == 'success' ? 'check-circle-fill' : 'exclamation-circle-fill' ?> text-lg"></i>
                    <span class="font-medium"><?= $addMsg ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Alpine State for Forms -->
        <div x-data="{ showAddForm: <?= $editEvent ? 'false' : 'false' ?> }">
            <?php if (!$editEvent): ?>
                <div class="flex justify-end mb-6">
                    <button x-show="!showAddForm" @click="showAddForm = true; $nextTick(() => $refs.inputTitle.focus())" class="flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-5 py-2.5 rounded-lg font-semibold transition-all shadow-sm hover:shadow-md text-sm">
                        <i class="bi bi-plus-circle"></i> Thêm sự kiện
                    </button>
                </div>
            <?php endif; ?>

            <!-- Form Edit Event -->
            <?php if ($editEvent): ?>
            <div class="mb-8 bg-white rounded-2xl p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-primary-100 ring-2 ring-primary-500/20">
                <h3 class="text-lg font-bold text-slate-800 mb-5 flex items-center gap-2">
                    <i class="bi bi-pencil-square text-primary-500"></i> Sửa sự kiện
                </h3>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
                    <input type="hidden" name="edit_id" value="<?= $editEvent['id'] ?>">
                    
                    <div class="lg:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2" for="title">Tiêu đề sự kiện</label>
                        <input type="text" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 outline-none transition-all" id="title" name="title" value="<?= htmlspecialchars($editEvent['title']) ?>" required>
                    </div>
                    
                    <div class="lg:col-span-1">
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2" for="pin">Mã PIN</label>
                        <input type="text" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 outline-none transition-all font-mono" id="pin" name="pin" value="<?= htmlspecialchars($editEvent['pin']) ?>" required maxlength="10">
                    </div>
                    
                    <div class="lg:col-span-1">
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2" for="event_date">Ngày diễn ra</label>
                        <input type="datetime-local" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 outline-none transition-all" id="event_date" name="event_date" value="<?= date('Y-m-d\TH:i', strtotime($editEvent['event_date'])) ?>" required>
                    </div>
                    
                    <div class="lg:col-span-4">
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2" for="description">Mô tả</label>
                        <input type="text" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 outline-none transition-all" id="description" name="description" value="<?= htmlspecialchars($editEvent['description']) ?>">
                    </div>
                    
                    <div class="lg:col-span-4 mt-2 pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                        <a href="events.php" class="px-5 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100 transition-colors">Hủy</a>
                        <button type="submit" name="edit_event" class="px-5 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-sm font-semibold shadow-sm transition-all flex items-center gap-2">
                            <i class="bi bi-save"></i> Lưu thay đổi
                        </button>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <!-- Form Add Event -->
            <div x-show="showAddForm" x-collapse x-cloak class="mb-8">
                <div class="bg-white rounded-2xl p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100">
                    <h3 class="text-lg font-bold text-slate-800 mb-5 flex items-center gap-2">
                        <i class="bi bi-calendar-plus text-primary-500"></i> Thêm sự kiện mới
                    </h3>
                    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
                        
                        <div class="lg:col-span-2">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2" for="title">Tiêu đề sự kiện</label>
                            <input type="text" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 outline-none transition-all" id="title" name="title" x-ref="inputTitle" required>
                        </div>
                        
                        <div class="lg:col-span-1">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2" for="event_date">Ngày diễn ra</label>
                            <input type="datetime-local" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 outline-none transition-all" id="event_date" name="event_date" required>
                        </div>
                        
                        <div class="lg:col-span-1">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2" for="description">Mô tả</label>
                            <input type="text" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 outline-none transition-all" id="description" name="description">
                        </div>
                        
                        <div class="lg:col-span-4 mt-2 pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                            <button type="button" @click="showAddForm = false" class="px-5 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100 transition-colors">Hủy</button>
                            <button type="submit" name="add_event" class="px-5 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-sm font-semibold shadow-sm transition-all flex items-center gap-2">
                                <i class="bi bi-plus-circle"></i> Tạo sự kiện
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase text-xs tracking-wider">
                        <tr>
                            <th class="px-5 py-4">Sự kiện</th>
                            <th class="px-5 py-4 text-center">Mã PIN</th>
                            <th class="px-5 py-4">Mô tả</th>
                            <th class="px-5 py-4">Ngày diễn ra</th>
                            <th class="px-5 py-4">Người tạo</th>
                            <th class="px-5 py-4 text-center">Điểm danh</th>
                            <th class="px-5 py-4 text-center w-28">Hành động</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php if ($events): ?>
                        <?php foreach ($events as $event): ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-5 py-3.5 font-bold text-slate-800">
                                <?= htmlspecialchars($event['title']) ?>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md bg-amber-50 text-amber-700 text-xs font-bold font-mono tracking-widest border border-amber-200 shadow-sm">
                                    <?= htmlspecialchars($event['pin'] ?? '') ?>
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600 truncate max-w-[200px]">
                                <?= htmlspecialchars($event['description']) ?>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600 font-medium">
                                <?= htmlspecialchars(date('d/m/Y H:i', strtotime($event['event_date']))) ?>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600">
                                <?= htmlspecialchars($event['creator']) ?>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="attendance_event.php?event_id=<?= $event['id'] ?>" class="px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-600 hover:text-white transition-colors text-xs font-semibold flex items-center gap-1" title="Điểm danh tay">
                                        <i class="bi bi-clipboard-check"></i>
                                    </a>
                                    <a href="attendance_qr.php?event_id=<?= $event['id'] ?>" class="px-3 py-1.5 rounded-lg bg-purple-50 text-purple-600 hover:bg-purple-600 hover:text-white transition-colors text-xs font-semibold flex items-center gap-1" title="Mã QR">
                                        <i class="bi bi-qr-code"></i>
                                    </a>
                                    <a href="attendance_view.php?event_id=<?= $event['id'] ?>" class="px-3 py-1.5 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white transition-colors text-xs font-semibold flex items-center gap-1" title="Xem danh sách">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="events.php?edit_id=<?= $event['id'] ?>" class="w-8 h-8 rounded-lg bg-slate-50 text-slate-600 hover:bg-primary-600 hover:text-white flex items-center justify-center transition-colors shadow-sm border border-slate-200" title="Sửa">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <a href="events.php?delete_id=<?= $event['id'] ?>" onclick="return confirm('Bạn chắc chắn muốn xóa sự kiện này? Toàn bộ dữ liệu điểm danh cũng sẽ bị xóa!')" class="w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-600 hover:text-white flex items-center justify-center transition-colors shadow-sm border border-red-100" title="Xóa">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-slate-500 bg-slate-50/50">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="bi bi-inbox text-4xl mb-3 text-slate-300"></i>
                                    <p>Chưa có sự kiện nào được tạo.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
<?php include '../includes/footer.php'; ?>