<?php 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/session.php';
require_once  __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}
// Chỉ cho phép admin, giáo viên, ban chủ nhiệm truy cập
if (!in_array($_SESSION['role'], ['admin', 'teacher', 'club_leader'])) {
    header("Location: ../dashboard.php");
    exit("Bạn không có quyền truy cập chức năng này!");
}

// Lấy phiên điểm danh đang hoạt động

$pageTitle = "Nhập Mã PIN Điểm Danh";
$full_name = $_SESSION['full_name'] ?? '';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="ml-0 lg:ml-64 pt-4 min-h-screen bg-slate-50/50 flex items-center justify-center p-4 sm:p-6 transition-all duration-300 ease-in-out">
    <div class="w-full max-w-md">
        
        <?php if(isset($_GET['error'])): ?>
        <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-r-lg shadow-sm animate-[fadeInDown_0.3s_ease-out]">
            <div class="flex items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill text-lg"></i>
                <span class="font-medium">
                    <?php
                        if ($_GET['error'] === 'not_auth') {
                            echo 'Phiên điểm danh đã hết hạn. Vui lòng nhập lại mã PIN.';
                        } elseif ($_GET['error'] === 'pin_changed') {
                            echo 'PIN đã thay đổi. Vui lòng nhập lại.';
                        } elseif ($_GET['error'] === 'session_closed') {
                            echo 'Phiên điểm danh đã kết thúc.';
                        } else {
                            echo 'Đã có lỗi xảy ra.';
                        }
                    ?>
                </span>
            </div>
        </div>
        <?php endif; ?>

        <div class="bg-white/80 backdrop-blur-xl rounded-[2rem] p-8 shadow-[0_30px_60px_-15px_rgba(37,99,235,0.15)] border border-white relative z-10 animate-[fadeInUp_0.4s_ease-out]">
            
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-primary-100 rounded-2xl flex items-center justify-center mx-auto mb-4 text-primary-600 shadow-inner">
                    <i class="bi bi-shield-lock-fill text-3xl"></i>
                </div>
                <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">Nhập mã PIN</h2>
                <p class="text-sm text-slate-500 mt-2 font-medium">Vui lòng nhập mã PIN gồm 6 số do BTC cung cấp</p>
            </div>

            <div class="space-y-6">
                <div>
                    <input 
                        type="password"
                        id="pin"
                        class="w-full text-center text-3xl tracking-[0.4em] font-black py-4 px-4 bg-slate-50 border-2 border-slate-200 rounded-xl text-slate-800 focus:border-primary-500 focus:ring-4 focus:ring-primary-500/20 outline-none transition-all placeholder:text-slate-300 placeholder:tracking-normal placeholder:font-medium placeholder:text-base"
                        placeholder="••••••"
                        maxlength="6"
                        inputmode="numeric"
                        autofocus
                    >
                </div>

                <button 
                    class="w-full flex justify-center items-center gap-2 bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white py-3.5 rounded-xl font-bold transition-all shadow-lg shadow-primary-500/30 hover:shadow-primary-500/50 hover:-translate-y-0.5"
                    onclick="submitPin()"
                    id="btnSubmit"
                >
                    <i class="bi bi-check-circle"></i> Xác nhận
                </button>

                <div id="message" class="empty:hidden"></div>
            </div>
            
        </div>
    </div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

<script>
function submitPin() {
    const pinInput = document.getElementById('pin');
    const message = document.getElementById('message');
    const btn = document.getElementById('btnSubmit');

    const pin = pinInput.value.trim();
    message.innerHTML = '';

    if (!/^\d{6}$/.test(pin)) {
        showError('PIN phải gồm 6 chữ số');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat animate-spin"></i> Đang xác thực';
    btn.classList.add('opacity-75', 'cursor-not-allowed');

    fetch('../api/check_pin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pin })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'scan_qr.php';
        } else {
            showError(data.message);
        }
    })
    .catch(() => {
        showError('Không kết nối được server');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle"></i> Xác nhận';
        btn.classList.remove('opacity-75', 'cursor-not-allowed');
    });
}

function showError(msg){
    document.getElementById('message').innerHTML = `
        <div class="mt-4 p-3 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-r-lg text-sm font-medium animate-[fadeIn_0.3s_ease-out]">
            <i class="bi bi-exclamation-triangle-fill mr-1.5"></i> ${msg}
        </div>
    `;
}

/* ENTER để xác nhận */
document.getElementById('pin').addEventListener('keypress', e => {
    if (e.key === 'Enter') submitPin();
});
</script>
