<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/db.php';

/* ===== TIMEOUT ===== */
$timeout = 18000;

/* ===== CHECK LOGIN & TIMEOUT ===== */
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active'] > $timeout)) {
    session_unset();
    session_destroy();
    header("Location: index.php?timeout=1");
    exit();
}
$_SESSION['last_active'] = time();

/* ===== GET FULL NAME ===== */
if (empty($_SESSION['full_name'])) {
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $_SESSION['full_name'] = $stmt->fetchColumn();
}
$full_name = $_SESSION['full_name'];

/* ===== FORCE CHANGE PASSWORD ===== */
if (!empty($_SESSION['first_login'])) {
    header("Location: change_password.php");
    exit();
}

/* ===== ANALYTICS DATA ===== */
$totalStudents = $pdo->query("SELECT COUNT(*) FROM campers")->fetchColumn() ?: 0;
$totalEvents = $pdo->query("SELECT COUNT(*) FROM ts_events")->fetchColumn() ?: 0;
$totalCheckins = $pdo->query("SELECT COUNT(*) FROM attendance_logs")->fetchColumn() ?: 0;

// Chart 1 Data: Attendance per Event (Top 5)
$stmt = $pdo->query("SELECT e.title, COUNT(al.id) as count FROM ts_events e LEFT JOIN attendance_sessions s ON e.id = s.event_id LEFT JOIN attendance_logs al ON s.id = al.session_id GROUP BY e.id ORDER BY e.event_date DESC LIMIT 5");
$chart1_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
$labels_chart1 = [];
$data_chart1 = [];
foreach (array_reverse($chart1_data) as $row) {
    // Truncate long titles
    $title = mb_strlen($row['title']) > 15 ? mb_substr($row['title'], 0, 15) . '...' : $row['title'];
    $labels_chart1[] = $title;
    $data_chart1[] = (int)$row['count'];
}

$pageTitle = "CLB Kỹ năng Đoàn - Bảng Thống Kê";
include __DIR__ . '/includes/header.php';
?>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<main class="ml-0 lg:ml-64 pt-4 min-h-screen bg-slate-50/50 dark:bg-slate-900 transition-colors duration-300 ease-in-out p-4 sm:p-6 lg:p-8">
  <div class="max-w-6xl mx-auto pb-12">
    <!-- Header Title -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-primary-100 dark:bg-primary-900/40 text-primary-600 dark:text-primary-400 flex items-center justify-center shadow-sm">
                <i class="bi bi-bar-chart-fill text-xl"></i>
            </div>
            <div>
                <h2 class="text-2xl font-extrabold text-slate-800 dark:text-slate-100 tracking-tight">BẢNG THỐNG KÊ</h2>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Năm học 2025 - 2026</p>
            </div>
        </div>
        <div class="inline-flex items-center gap-2.5 px-4 py-2 rounded-xl bg-white dark:bg-slate-800 border border-emerald-100 dark:border-emerald-900/30 shadow-sm text-emerald-700 dark:text-emerald-400 font-medium text-sm">
            <i class="bi bi-lightbulb-fill text-amber-400 text-lg"></i>
            Phiên bản thử nghiệm BETA
        </div>
    </div>

    <!-- Stats Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6 mb-8">
        
        <!-- Card 1: Students -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-slate-100 dark:border-slate-700 hover:shadow-md transition-shadow flex items-center gap-5 relative overflow-hidden">
            <div class="w-14 h-14 rounded-full bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400 shrink-0">
                <i class="bi bi-people-fill text-2xl"></i>
            </div>
            <div>
                <p class="text-sm font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Tổng Trại Sinh</p>
                <h3 class="text-3xl font-black text-slate-800 dark:text-slate-100 mt-1"><?= number_format($totalStudents) ?></h3>
            </div>
        </div>

        <!-- Card 2: Events -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-slate-100 dark:border-slate-700 hover:shadow-md transition-shadow flex items-center gap-5 relative overflow-hidden">
            <div class="w-14 h-14 rounded-full bg-primary-50 dark:bg-primary-900/30 flex items-center justify-center text-primary-600 dark:text-primary-400 shrink-0">
                <i class="bi bi-calendar-event-fill text-2xl"></i>
            </div>
            <div>
                <p class="text-sm font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Tổng Sự Kiện</p>
                <h3 class="text-3xl font-black text-slate-800 dark:text-slate-100 mt-1"><?= number_format($totalEvents) ?></h3>
            </div>
        </div>

        <!-- Card 3: Checkins -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-slate-100 dark:border-slate-700 hover:shadow-md transition-shadow flex items-center gap-5 relative overflow-hidden sm:col-span-2 lg:col-span-1">
            <div class="w-14 h-14 rounded-full bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                <i class="bi bi-clipboard-check-fill text-2xl"></i>
            </div>
            <div>
                <p class="text-sm font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Tổng Lượt Điểm Danh</p>
                <h3 class="text-3xl font-black text-slate-800 dark:text-slate-100 mt-1"><?= number_format($totalCheckins) ?></h3>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Bar Chart -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-slate-100 dark:border-slate-700">
            <h3 class="font-bold text-slate-700 dark:text-slate-200 mb-4 flex items-center gap-2">
                <i class="bi bi-bar-chart-line text-primary-500"></i> Lượt tham gia 5 sự kiện gần nhất
            </h3>
            <div class="relative h-[300px] w-full">
                <canvas id="eventChart"></canvas>
            </div>
        </div>

        <!-- Doughnut Chart -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-slate-100 dark:border-slate-700">
            <h3 class="font-bold text-slate-700 dark:text-slate-200 mb-4 flex items-center gap-2">
                <i class="bi bi-pie-chart-fill text-amber-500"></i> Tỉ lệ hoàn thành sự kiện
            </h3>
            <div class="relative h-[300px] w-full flex items-center justify-center">
                <canvas id="ratioChart"></canvas>
            </div>
        </div>

    </div>

  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Shared styling depending on theme
    const isDark = document.documentElement.classList.contains('dark');
    const textColor = isDark ? '#94a3b8' : '#64748b';
    const gridColor = isDark ? '#334155' : '#f1f5f9';

    Chart.defaults.color = textColor;
    Chart.defaults.font.family = "'Inter', sans-serif";

    // Chart 1: Bar Chart (Events)
    const ctx1 = document.getElementById('eventChart').getContext('2d');
    new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: <?= json_encode($labels_chart1) ?>,
            datasets: [{
                label: 'Lượt điểm danh',
                data: <?= json_encode($data_chart1) ?>,
                backgroundColor: 'rgba(39, 184, 126, 0.8)', // primary-500
                borderRadius: 6,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: gridColor, drawBorder: false },
                    ticks: { stepSize: 10 }
                },
                x: {
                    grid: { display: false, drawBorder: false }
                }
            }
        }
    });

    // Chart 2: Doughnut Chart (Fake data for now as total ratio)
    const ctx2 = document.getElementById('ratioChart').getContext('2d');
    const totalCampers = <?= $totalStudents ?>;
    const maxPossibleCheckins = <?= $totalEvents ?> * totalCampers;
    const actualCheckins = <?= $totalCheckins ?>;
    const missed = maxPossibleCheckins > 0 ? maxPossibleCheckins - actualCheckins : 0;

    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: ['Có mặt', 'Vắng mặt'],
            datasets: [{
                data: [actualCheckins, missed],
                backgroundColor: [
                    'rgba(39, 184, 126, 0.9)', // primary-500
                    'rgba(241, 245, 249, 0.2)'  // slate-100 (darker in dark mode context via transparency)
                ],
                hoverBackgroundColor: [
                    'rgba(28, 150, 101, 1)',   // primary-600
                    'rgba(226, 232, 240, 0.5)' // slate-200
                ],
                borderWidth: 2,
                borderColor: isDark ? '#1e293b' : '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // Observe theme changes to update charts dynamically
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.attributeName === "class") {
                const currentIsDark = document.documentElement.classList.contains('dark');
                // A complete implementation would update chart instances here.
                // For simplicity, requiring a reload or handling in Chart.js directly.
            }
        });
    });
    observer.observe(document.documentElement, { attributes: true });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
