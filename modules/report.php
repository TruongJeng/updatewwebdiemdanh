<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../PHPSpreadsheet/vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Phân quyền: chỉ admin, giáo viên, club_leader mới được xem báo cáo
if (!in_array($_SESSION['role'], ['admin', 'teacher', 'club_leader'])) {
    header("Location: ../dashboard.php");
    exit("Bạn không có quyền truy cập chức năng này!");
}

// Lấy thông tin user
$full_name = $_SESSION['full_name'] ?? '';
if (!$full_name && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $full_name = $stmt->fetchColumn();
    $_SESSION['full_name'] = $full_name;
}

// Lấy danh sách sự kiện, lớp và học sinh để filter
$events = $pdo->query("SELECT id, title, event_date FROM events ORDER BY event_date DESC")->fetchAll();
$classes = $pdo->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL AND class != '' ORDER BY class")->fetchAll();
$students = $pdo->query("SELECT id, student_code, ho, ten, class FROM students ORDER BY class, student_code")->fetchAll();

// Xử lý filter
$event_id = $_GET['event_id'] ?? '';
$class = $_GET['class'] ?? '';
$student_id = $_GET['student_id'] ?? '';

$where = [];
$params = [];

if ($event_id) {
    $where[] = "a.event_id = ?";
    $params[] = $event_id;
}
if ($class) {
    $where[] = "s.class = ?";
    $params[] = $class;
}
if ($student_id) {
    $where[] = "s.id = ?";
    $params[] = $student_id;
}

$where_sql = '';
$where_parts = [];
if ($class) { $where_parts[] = "s.class = ?"; }
if ($student_id) { $where_parts[] = "s.id = ?"; }
if ($where_parts) { $where_sql = 'WHERE ' . implode(' AND ', $where_parts); }

// Tổng hợp dữ liệu
$sql = "SELECT s.student_code, s.ho, s.ten, s.class, COUNT(a.id) as so_lan_diem_danh
        FROM students s
        LEFT JOIN attendance a ON s.id = a.student_id " . ($event_id ? "AND a.event_id = ?" : "") . "
        $where_sql
        GROUP BY s.id, s.student_code, s.ho, s.ten, s.class
        ORDER BY s.class, s.student_code";

// Build params in the same order as SQL placeholders
$params_stat = [];
if ($event_id) $params_stat[] = $event_id;
if ($class) $params_stat[] = $class;
if ($student_id) $params_stat[] = $student_id;

$stmt = $pdo->prepare($sql);
$stmt->execute($params_stat);
$stat = $stmt->fetchAll();

// Xuất file Excel nếu được yêu cầu
if (isset($_GET['export_csv'])) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle("Attendance Report");

    // Ghi tiêu đề cột
    $sheet->setCellValue('A1', 'Mã số');
    $sheet->setCellValue('B1', 'Họ và tên');
    $sheet->setCellValue('C1', 'Lớp');
    $sheet->setCellValue('D1', 'Số lần điểm danh');

    // Ghi dữ liệu vào các dòng
    $rowIndex = 2;
    foreach ($stat as $row) {
        $sheet->setCellValue('A' . $rowIndex, $row['student_code']);
        $sheet->setCellValue('B' . $rowIndex, trim($row['ho'] . ' ' . $row['ten']));
        $sheet->setCellValue('C' . $rowIndex, $row['class']);
        $sheet->setCellValue('D' . $rowIndex, $row['so_lan_diem_danh']);
        $rowIndex++;
    }

    // Xuất file Excel
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="attendance_report.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}
?>
<?php
$pageTitle = "Thống kê điểm danh";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="ml-0 lg:ml-64 pt-4 min-h-screen bg-slate-50/50 transition-all duration-300 ease-in-out p-4 sm:p-6 lg:p-8">
    <div class="max-w-7xl mx-auto pb-12">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-primary-100 text-primary-600 flex items-center justify-center shadow-sm">
                    <i class="bi bi-bar-chart-line text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">THỐNG KÊ ĐIỂM DANH</h2>
                    <p class="text-sm font-medium text-slate-500 mt-1">Báo cáo tổng hợp tình trạng điểm danh</p>
                </div>
            </div>
            <a href="?<?= http_build_query(array_merge($_GET, ['export_csv' => 1])) ?>" class="flex items-center gap-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 px-4 py-2 rounded-lg font-semibold transition-all shadow-sm text-sm">
                <i class="bi bi-file-earmark-excel"></i> Xuất Excel
            </a>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-2xl p-4 sm:p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 mb-6">
            <form method="get" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Sự kiện</label>
                    <select name="event_id" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20">
                        <option value="">-- Tất cả --</option>
                        <?php foreach ($events as $e): ?>
                            <option value="<?= $e['id'] ?>" <?= $event_id == $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Lớp</label>
                    <select name="class" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20">
                        <option value="">-- Tất cả --</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= htmlspecialchars($c['class']) ?>" <?= $class == $c['class'] ? 'selected' : '' ?>><?= htmlspecialchars($c['class']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Học sinh</label>
                    <select name="student_id" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20">
                        <option value="">-- Tất cả --</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $student_id == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars(trim($s['ho'].' '.$s['ten'])) ?> (<?= $s['class'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-bold transition-all shadow-sm flex items-center justify-center gap-2">
                        <i class="bi bi-funnel"></i> Lọc
                    </button>
                </div>
            </form>
        </div>

        <!-- Results -->
        <?php if (empty($stat)): ?>
            <div class="text-center py-12 bg-white rounded-2xl border border-slate-100 shadow-sm">
                <div class="w-16 h-16 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-4">
                    <i class="bi bi-inbox text-3xl"></i>
                </div>
                <p class="text-slate-500 font-medium">Không có dữ liệu phù hợp với bộ lọc.</p>
            </div>
        <?php else: ?>

        <!-- Mobile Cards -->
        <div class="lg:hidden space-y-2 mb-6">
            <?php foreach ($stat as $row): ?>
                <div class="mobile-card border-l-4 <?= $row['so_lan_diem_danh'] > 0 ? 'border-l-emerald-400' : 'border-l-slate-300' ?>">
                    <div class="flex items-start justify-between mb-1">
                        <div class="min-w-0">
                            <div class="font-bold text-slate-800"><?= htmlspecialchars(trim($row['ho'].' '.$row['ten'])) ?></div>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-[10px] font-bold font-mono bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded"><?= htmlspecialchars($row['student_code']) ?></span>
                                <span class="text-xs text-slate-500"><?= htmlspecialchars($row['class']) ?></span>
                            </div>
                        </div>
                        <span class="shrink-0 inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold <?= $row['so_lan_diem_danh'] > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
                            <?= $row['so_lan_diem_danh'] ?> lần
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Desktop Table -->
        <div class="hidden lg:block bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase text-xs tracking-wider">
                        <tr>
                            <th class="px-5 py-4">Mã HS</th>
                            <th class="px-5 py-4">Họ và tên</th>
                            <th class="px-5 py-4">Lớp</th>
                            <th class="px-5 py-4">Số lần điểm danh</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($stat as $row): ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-5 py-3.5">
                                <span class="font-mono text-xs font-bold text-slate-500 bg-slate-100 px-2 py-1 rounded"><?= htmlspecialchars($row['student_code']) ?></span>
                            </td>
                            <td class="px-5 py-3.5 font-bold text-slate-800"><?= htmlspecialchars(trim($row['ho'].' '.$row['ten'])) ?></td>
                            <td class="px-5 py-3.5 text-slate-600"><?= htmlspecialchars($row['class']) ?></td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold <?= $row['so_lan_diem_danh'] > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500 border border-slate-200' ?>">
                                    <?= $row['so_lan_diem_danh'] ?> lần
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>