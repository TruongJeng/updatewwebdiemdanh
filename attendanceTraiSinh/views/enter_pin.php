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

if(isset($_GET['error']) && $_GET['error']=='not_auth'): ?>
<div class="alert alert-danger text-center">
  Phiên điểm danh đã hết hạn.<br>
  Vui lòng nhập lại mã PIN.
</div>
<?php endif; ?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ĐIỂM DANH CHO BTC</title>
<link rel="icon" type="image/png" href="/hethongdiemdanh/assets/logo_CLB.png">
<!-- Bootstrap + Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
:root{
    --primary:#3178c6;
    --bg:#f4faff;
    --card:#ffffff;
}

body{
    background:var(--bg);
    font-family:system-ui,-apple-system,BlinkMacSystemFont;
}

/* HEADER */
.header{
    background:linear-gradient(90deg,#3178c6,#6fa6e3);
    color:#fff;
    padding:14px 18px;
    font-size:18px;
    font-weight:700;
    display:flex;
    align-items:center;
    gap:10px;
}

/* CARD */
.pin-card{
    background:var(--card);
    border-radius:16px;
    padding:22px 20px;
    box-shadow:0 4px 18px #3178c61a;
    max-width:360px;
    margin:auto;
}

/* INPUT */
.pin-input{
    text-align:center;
    font-size:28px;
    letter-spacing:8px;
}

/* FOOTER */
.footer{
    text-align:center;
    font-size:13px;
    color:#666;
    margin-top:20px;
}
</style>
</head>

<body>

<?php
$pageTitle = "Nhập Mã PIN Điểm Danh";
$full_name = $_SESSION['full_name'] ?? '';
include __DIR__ . '/../config/header.php';
?>

<div class="container py-4">
    <?php if(isset($_GET['error'])): ?>
    <div class="alert alert-danger text-center mb-3">
    <?php
        if ($_GET['error'] === 'pin_changed') {
            echo 'PIN đã thay đổi. Vui lòng nhập lại.';
        } elseif ($_GET['error'] === 'session_closed') {
            echo 'Phiên điểm danh đã kết thúc.';
        }
    ?>
    </div>
    <?php endif; ?>

    <div class="pin-card">

        <div class="text-center mb-3 text-muted">
            Vui lòng nhập mã PIN do BTC cung cấp
        </div>

        <input 
            type="password"
            id="pin"
            class="form-control pin-input mb-3"
            placeholder="••••••"
            maxlength="6"
            inputmode="numeric"
            autofocus
        >

        <button 
            class="btn btn-primary w-100 fw-semibold"
            onclick="submitPin()"
            id="btnSubmit"
        >
            <i class="bi bi-check-circle"></i> Xác nhận
        </button>

        <div id="message" class="mt-3"></div>

    </div>

<?php include __DIR__ . '/../config/footer.php'; ?>


</div>

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
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Đang xác thực';

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
    });
}

function showError(msg){
    document.getElementById('message').innerHTML = `
        <div class="alert alert-danger py-2 mb-0">
            <i class="bi bi-exclamation-triangle"></i> ${msg}
        </div>
    `;
}
/* ENTER để xác nhận */
document.getElementById('pin').addEventListener('keypress', e => {
    if (e.key === 'Enter') submitPin();
});
</script>

</body>
</html>
