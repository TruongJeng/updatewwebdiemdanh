<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
if (!isset($full_name)) $full_name = $_SESSION['full_name'] ?? 'Người dùng';

// Lấy danh sách sự kiện
$stmt = $pdo->query("SELECT id, title, event_date FROM events ORDER BY event_date DESC");
$events = $stmt->fetchAll();
?>
<?php
$pageTitle = "CHỌN SỰ KIỆN ĐIỂM DANH";
$full_name = $_SESSION['full_name'] ?? '';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<main class="ml-0 lg:ml-64 pt-4 min-h-screen bg-slate-50/50 transition-all duration-300 ease-in-out p-4 sm:p-6 lg:p-8">
    <div class="max-w-4xl mx-auto pb-12">
        <!-- Back Link -->
        <div class="flex items-center gap-3 mb-6">
            <a href="../dashboard.php" class="text-slate-500 hover:text-primary-600 transition-colors flex items-center gap-1.5 text-sm font-medium bg-white px-3 py-1.5 rounded-full border border-slate-200 shadow-sm hover:shadow">
                <i class="bi bi-arrow-left"></i> Về Trang chủ
            </a>
        </div>
        
        <!-- Header -->
        <div class="flex items-center gap-3 mb-8">
            <div class="w-12 h-12 rounded-xl bg-primary-100 text-primary-600 flex items-center justify-center shadow-sm">
                <i class="bi bi-clipboard-check text-2xl"></i>
            </div>
            <div>
                <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">ĐIỂM DANH SỰ KIỆN</h2>
                <p class="text-sm font-medium text-slate-500 mt-1">Chọn một sự kiện dưới đây để bắt đầu điểm danh</p>
            </div>
        </div>

        <!-- Event List -->
        <div class="bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 overflow-hidden">
            <ul class="divide-y divide-slate-100">
                <?php if ($events): ?>
                    <?php foreach ($events as $event): ?>
                    <li class="group">
                        <a href="attendance_event.php?event_id=<?= $event['id'] ?>" class="flex items-center justify-between p-5 hover:bg-primary-50 transition-colors">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-full bg-primary-50 text-primary-500 group-hover:bg-white group-hover:text-primary-600 flex items-center justify-center shadow-sm border border-primary-100 transition-colors">
                                    <i class="bi bi-calendar-event text-lg"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-700 group-hover:text-primary-700 transition-colors text-base"><?= htmlspecialchars($event['title']) ?></h3>
                                    <p class="text-sm text-slate-500 mt-0.5 flex items-center gap-1.5">
                                        <i class="bi bi-clock"></i>
                                        <?= htmlspecialchars(date('d/m/Y H:i', strtotime($event['event_date']))) ?>
                                    </p>
                                </div>
                            </div>
                            <div class="text-slate-300 group-hover:text-primary-500 transition-colors group-hover:translate-x-1 duration-300">
                                <i class="bi bi-chevron-right text-xl"></i>
                            </div>
                        </a>
                    </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="p-12 text-center">
                        <div class="w-16 h-16 rounded-full bg-slate-50 text-slate-300 flex items-center justify-center mx-auto mb-4">
                            <i class="bi bi-calendar-x text-3xl"></i>
                        </div>
                        <h3 class="font-bold text-slate-700 mb-1">Chưa có sự kiện nào</h3>
                        <p class="text-slate-500 text-sm">Hãy tạo sự kiện mới trong phần Quản lý sự kiện.</p>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</main>
<?php include '../includes/footer.php'; ?>