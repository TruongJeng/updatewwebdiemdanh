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

$pageTitle = "CLB Kỹ năng Đoàn - Hội Trường THPT Lý Thường Kiệt";
include __DIR__ . '/includes/header.php';
?>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="ml-0 lg:ml-64 pt-4 min-h-screen bg-slate-50/50 transition-all duration-300 ease-in-out p-4 sm:p-6 lg:p-8">
  <div class="max-w-5xl mx-auto pb-12">
    <!-- Header Title -->
    <div class="flex items-center gap-3 mb-8">
        <div class="w-10 h-10 rounded-xl bg-primary-100 text-primary-600 flex items-center justify-center shadow-sm">
            <i class="bi bi-pin-angle-fill text-xl"></i>
        </div>
        <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">BẢNG THÔNG TIN</h2>
    </div>

    <!-- Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        
        <!-- Card: Hiện tại -->
        <div class="group bg-white rounded-2xl p-6 shadow-sm border-l-4 border-primary-500 hover:shadow-[0_10px_40px_-10px_rgba(37,99,235,0.15)] hover:-translate-y-1 transition-all duration-300 flex flex-col justify-center relative overflow-hidden">
            <div class="absolute -right-4 -bottom-4 text-primary-50 opacity-50 group-hover:scale-110 transition-transform duration-500">
                <i class="bi bi-calendar-event text-8xl"></i>
            </div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-full bg-primary-50 flex items-center justify-center text-primary-600">
                        <i class="bi bi-calendar-event text-lg"></i>
                    </div>
                    <h3 class="font-bold text-slate-700 uppercase tracking-wide text-sm">Hiện tại</h3>
                </div>
                <p class="text-slate-600 font-medium">Năm học <span class="font-bold text-primary-700 bg-primary-50 px-2 py-1 rounded-md ml-1">2025 – 2026</span></p>
            </div>
        </div>

        <!-- Card: Warning -->
        <div class="md:col-span-2 group bg-white rounded-2xl p-6 shadow-sm border-l-4 border-amber-500 hover:shadow-[0_10px_40px_-10px_rgba(245,158,11,0.15)] hover:-translate-y-1 transition-all duration-300 relative overflow-hidden">
            <div class="absolute -right-6 -top-6 text-amber-50 rotate-12 group-hover:rotate-0 transition-transform duration-500">
                <i class="bi bi-exclamation-triangle-fill text-9xl"></i>
            </div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-full bg-amber-50 flex items-center justify-center text-amber-600">
                        <i class="bi bi-exclamation-triangle text-lg"></i>
                    </div>
                    <h3 class="font-bold text-slate-700 uppercase tracking-wide text-sm">Thông báo quan trọng</h3>
                </div>
                <p class="text-slate-600 leading-relaxed text-sm sm:text-base">
                    Đây là <strong class="text-amber-700 font-bold bg-amber-50 px-2 py-0.5 rounded mx-1">phiên bản thử nghiệm BETA 01</strong>.<br>
                    CLB Kỹ năng Đoàn – Hội Trường THPT Lý Thường Kiệt đang trong quá trình phát triển, nên có thể vẫn còn một số thiếu sót. Rất mong quý thầy cô và các bạn đóng góp ý kiến để hệ thống hoàn thiện hơn.
                </p>
            </div>
        </div>

        <!-- Card: Thanks -->
        <div class="md:col-span-2 lg:col-span-3 group bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-emerald-100 hover:shadow-[0_10px_40px_-10px_rgba(16,185,129,0.1)] hover:-translate-y-1 transition-all duration-300 bg-gradient-to-br from-white to-emerald-50/50">
            <div class="flex items-center gap-4 mb-5">
                <div class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600 shadow-inner group-hover:scale-110 transition-transform">
                    <i class="bi bi-suit-heart-fill text-xl text-red-500 animate-pulse"></i>
                </div>
                <h3 class="font-bold text-slate-800 text-lg uppercase tracking-wide">Lời Cảm Ơn</h3>
            </div>
            <p class="text-slate-600 leading-relaxed mb-6 text-base sm:text-lg">
                CLB Kỹ năng Đoàn – Hội xin chân thành cảm ơn quý thầy cô và các bạn đã tin tưởng và trải nghiệm hệ thống.
            </p>
            <div class="inline-flex items-center gap-2.5 px-4 py-2.5 rounded-xl bg-white border border-emerald-100 shadow-sm text-emerald-700 font-medium text-sm">
                <i class="bi bi-lightbulb-fill text-amber-400 text-lg drop-shadow-sm"></i>
                Mỗi góp ý của bạn là một bước tiến để xây dựng nền tảng tốt hơn.
            </div>
        </div>
        
    </div>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
