<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$event_id = $_GET['event_id'] ?? '';
if (!$event_id) {
    echo "Thiếu mã sự kiện!";
    exit();
}

// Lấy thông tin sự kiện
$stmt = $pdo->prepare("SELECT title, event_date, is_closed FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();
if (!$event) {
    echo "Không tìm thấy sự kiện!";
    exit();
}

// Nếu event đã đóng, không cho điểm danh (hiển thị cảnh báo)
$event_closed = !empty($event['is_closed']);


// Hàm lấy IP client
function get_client_ip() {
    foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) $ip = explode(',', $ip)[0];
            return trim($ip);
        }
    }
    return 'UNKNOWN';
}

// Hàm tách họ tên
function tach_ho_ten($full_name) {
    $full_name = trim(preg_replace('/\s+/', ' ', $full_name));
    $parts = explode(' ', $full_name);
    $ten = array_pop($parts);
    $ho = implode(' ', $parts);
    return [$ho, $ten];
}

// Hàm sinh mã học sinh tự động tăng dần theo năm + số thứ tự
function tao_ma_hoc_sinh() {
    global $pdo;
    $year = date('y');
    $stmt = $pdo->prepare("SELECT student_code FROM students WHERE LEFT(student_code,2)=? ORDER BY student_code DESC LIMIT 1");
    $stmt->execute([$year]);
    $last_code = $stmt->fetchColumn();
    if ($last_code && preg_match('/^(\d{2})(\d{4})$/', $last_code, $m)) {
        $last_num = (int)$m[2];
    } else {
        $last_num = 0;
    }
    $next_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
    return $year . $next_num;
}

// Hàm gửi mail xác nhận chuyên nghiệp, nhúng logo
function send_confirm_mail($to_email, $to_name, $subject, $body_html, $logo_path = null) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'mail.clbkynangdoanhoiltk.io.vn';
        $mail->SMTPAuth = true;
        $mail->Username = 'no-reply@clbkynangdoanhoiltk.io.vn';
        $mail->Password = 'Giang15052006@';
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;

        $mail->setFrom('no-reply@clbkynangdoanhoiltk.io.vn', 'CLB Kỹ năng Đoàn - Hội Trường THPT Lý Thường Kiệt');
        $mail->addAddress($to_email, $to_name);

        if ($logo_path && file_exists($logo_path)) {
            $mail->AddEmbeddedImage($logo_path, 'clb_logo');
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        if ($logo_path && file_exists($logo_path)) {
            $body_html = '<div style="text-align:center;margin-bottom:18px;"><img src="cid:clb_logo" style="max-height:70px;" alt="CLB Kỹ năng Đoàn - Hội"></div>' . $body_html;
        }
        $mail->Body    = $body_html;
        $mail->CharSet = 'UTF-8';

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

$msg = '';
$success = false;
$checked_name = '';

/*
  === CHÚ Ý (SỬA NHỎ Ở ĐÂY) ===
  Mình chỉ thêm khả năng nhận và lưu lat/lng/gps_time/gps_source nếu client gửi lên.
  Nếu DB chưa có các cột lat,lng,gps_time,gps_source, chạy migration.sql phía trên.
*/

if (!isset($_SESSION['user_id']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $class = trim($_POST['class'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Các trường GPS (có thể rỗng nếu user từ chối)
    $lat = isset($_POST['lat']) && $_POST['lat'] !== '' ? (float) $_POST['lat'] : null;
    $lng = isset($_POST['lng']) && $_POST['lng'] !== '' ? (float) $_POST['lng'] : null;
    $gps_time = isset($_POST['gps_time']) && $_POST['gps_time'] !== '' ? $_POST['gps_time'] : null; // string datetime
    $gps_source = isset($_POST['gps_source']) ? trim($_POST['gps_source']) : '';

    if ($event_closed) {
        $msg = 'Sự kiện đã được đóng. Không thể điểm danh.';
    } elseif ($full_name === '' || $class === '' || $email === '') {
        $msg = 'Vui lòng nhập đầy đủ họ tên, lớp và email!';
    } else {
        $ip = get_client_ip();
        list($ho, $ten) = tach_ho_ten($full_name);

        // Kiểm tra học sinh đã tồn tại chưa
        $stmt = $pdo->prepare("SELECT id, student_code FROM students WHERE full_name=? AND class=?");
        $stmt->execute([$full_name, $class]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($student && $student['id']) {
            $student_id = $student['id'];
            $student_code = $student['student_code'];
            $stmtUpdateMail = $pdo->prepare("UPDATE students SET email=? WHERE id=?");
            $stmtUpdateMail->execute([$email, $student_id]);
        } else {
            $student_code = tao_ma_hoc_sinh();
            $club_id = NULL;
            $is_active = 1;
            $phone = '';
            $address = '';
            $note = '';
            $stmt = $pdo->prepare("INSERT INTO students (student_code, full_name, class, club_id, is_active, ho, ten, phone, email, address, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$student_code, $full_name, $class, $club_id, $is_active, $ho, $ten, $phone, $email, $address, $note]);
            $student_id = $pdo->lastInsertId();
        }

        // Kiểm tra đã điểm danh event này chưa (student_id + event_id)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE event_id=? AND student_id=?");
        $stmt->execute([$event_id, $student_id]);
        if ($stmt->fetchColumn() > 0) {
            $msg = "Bạn đã điểm danh cho sự kiện này!";
            $success = false;
        } else {
            // Nếu bảng attendance có cột lat,lng,gps_time,gps_source thì insert cùng, nếu không thì insert như cũ.
            // Mình kiểm tra nhanh bằng try/catch: nếu insert có thêm cột lỗi, fallback về insert cũ.
            try {
                $stmt = $pdo->prepare("INSERT INTO attendance (event_id, student_id, checkin_method, ip_addr, lat, lng, gps_time, gps_source) VALUES (?, ?, 'barcode', ?, ?, ?, ?, ?)");
                // gps_time đưa vào null hoặc formatted datetime
                $stmt->execute([$event_id, $student_id, $ip, $lat, $lng, $gps_time, $gps_source]);
            } catch (PDOException $e) {
                // Fallback nếu DB không có cột lat/lng: dùng insert cũ
                $stmt = $pdo->prepare("INSERT INTO attendance (event_id, student_id, checkin_method, ip_addr) VALUES (?, ?, 'barcode', ?)");
                $stmt->execute([$event_id, $student_id, $ip]);
            }

            $subject = "[TB] XÁC NHẬN ĐIỂM DANH SỰ KIỆN " . $event['title'];
            $body_html = "<div style='font-family:Arial,sans-serif;'>
                <h2 style='color:#3178c6;'>XÁC NHẬN ĐIỂM DANH THÀNH CÔNG</h2>
                <p>Kính chào <b>$full_name</b>,</p>
                <p>Ban Chủ nhiệm trân trọng thông báo: bạn đã điểm danh thành công cho sự kiện <b style='color:#6f42c1;'>{$event['title']}</b> vào ngày <b>" . date('d/m/Y') . "</b>.</p>
                <p>Xin cảm ơn bạn đã hiện diện và đồng hành cùng chương trình. Sự tham gia của bạn là nguồn động viên to lớn, góp phần tạo nên thành công cho hoạt động lần này.</p>";
            $body_html .= "<hr>
                <small>
                Trân trọng,<br>
                <b>Ban Chủ nhiệm CLB Kỹ năng Đoàn – Hội Trường THPT Lý Thường Kiệt</b>
                </small>
                </div>";
            $logo_path = $_SERVER['DOCUMENT_ROOT'] . "/hethongdiemdanh/assets/Logo_CLB.png";
            send_confirm_mail($email, $full_name, $subject, $body_html, $logo_path);

            $checked_name = htmlspecialchars($full_name);
            $msg = "<span class='checked-name'>$checked_name</span> đã điểm danh thành công!<br><span class='student-code'>Mã học sinh: <b>$student_code</b></span><br><div class='mt-2'><span class='bi bi-check-circle-fill' style='font-size:2em;color:#3178c6;'></span></div><div class='mt-2'><b>Bạn có thể tắt điểm danh/thoát!</b></div>";
            $success = true;
        }
    }
}

$pageTitle = "Điểm danh QR - " . htmlspecialchars($event['title']);
$full_name = $_SESSION['full_name'] ?? '';
include '../includes/header.php';
?>
<div class="main-wrap">
    <div class="event-title">
        Sự kiện: <?= htmlspecialchars($event['title']) ?>
    </div>
    <?php if ($event['event_date']): ?>
        <div class="event-date">
            <i class="bi bi-calendar-event"></i> <?= date('d/m/Y H:i', strtotime($event['event_date'])) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="qr-block">
            <div class="qr-inner">
                <div class="qr-title">Quét mã QR này để điểm danh:</div>
                <div id="qrcode" style="display: flex; justify-content: center; align-items: center;"></div>
                <div class="mt-2 small" style="color:#888;word-break:break-all;">Hoặc truy cập:<br>
                    <span style="color:#3178c6;" id="qr-link"></span>
                </div>
                <div class="admin-action">
                    <!-- Chuyển nút Kết thúc để mở bản đồ quản lý (admin_map.php) -->
                    <a href="admin_map.php?event_id=<?= urlencode($event_id) ?>" class="btn btn-finish"><i class="bi bi-geo-alt"></i> Kết thúc điểm danh</a>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/qrcodejs/qrcode.min.js"></script>
        <script>
        function updateQR() {
            var t = Math.floor(Date.now()/1000/30);
            var url = window.location.origin + window.location.pathname + window.location.search;
            if(url.indexOf('?') > -1) url += '&t=' + t; else url += '?t=' + t;
            document.getElementById('qr-link').textContent = url;
            document.getElementById('qrcode').innerHTML = '';
            var size = window.innerWidth < 500 ? 180 : 240;
            new QRCode(document.getElementById("qrcode"), {
                text: url,
                width: size,
                height: size,
                colorDark : "#3178c6",
                colorLight : "#fff",
                correctLevel : QRCode.CorrectLevel.H
            });
        }
        updateQR();
        setInterval(updateQR, 10000);
        window.addEventListener('resize', updateQR);
        </script>
    <?php else: ?>
        <div class="form-checkin">
        <h5 class="mb-3 text-center" style="color:#3178c6;"><i class="bi bi-qr-code"></i> Form điểm danh</h5>
        <?php if ($msg): ?>
            <div class="<?= $success ? 'msg-success' : 'msg-error' ?>">
                <?php if ($success): ?><i class="bi bi-check-circle-fill"></i><?php endif; ?>
                <?= $msg ?>
                <?php if ($success): ?>
                <form method="get" action="../">
                    <button type="submit" class="btn btn-exit w-100"><i class="bi bi-door-open"></i> Đóng/tắt điểm danh</button>
                </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if (!$success): ?>
        <form id="checkinForm" method="post" autocomplete="off">
            <!-- Hidden fields for GPS -->
            <input type="hidden" name="lat" id="lat" value="">
            <input type="hidden" name="lng" id="lng" value="">
            <input type="hidden" name="gps_time" id="gps_time" value="">
            <input type="hidden" name="gps_source" id="gps_source" value="">

            <div class="mb-3">
                <label class="form-label" for="full_name">Họ và tên:</label>
                <input type="text" id="full_name" name="full_name" class="form-control" required autocomplete="name">
            </div>
            <div class="mb-3">
                <label class="form-label" for="class">Lớp:</label>
                <input type="text" id="class" name="class" class="form-control" required autocomplete="organization">
            </div>
            <div class="mb-3">
                <label class="form-label" for="email">Email:</label>
                <input type="email" id="email" name="email" class="form-control" required autocomplete="email">
            </div>
            <button id="submitBtn" type="submit" class="btn btn-primary w-100" style="font-weight:600;">
                <i class="bi bi-check-circle"></i> Điểm danh
            </button>
        </form>

        <script>
        // Geolocation: lấy lat/lng trước khi submit. Nếu user từ chối hoặc timeout, submit không có tọa độ.
        (function(){
            const form = document.getElementById('checkinForm');
            const submitBtn = document.getElementById('submitBtn');

            form.addEventListener('submit', function(e){
                // nếu đã có giá trị lat/lng (ví dụ từ lần trước), submit luôn
                if (document.getElementById('lat').value !== '' || document.getElementById('lng').value !== '') {
                    return; // allow submit
                }
                e.preventDefault();
                submitBtn.disabled = true;
                submitBtn.innerText = 'Đang lấy vị trí...';

                if (!navigator.geolocation) {
                    document.getElementById('gps_source').value = 'unsupported';
                    submitBtn.disabled = false;
                    form.submit();
                    return;
                }

                const geoOpts = { enableHighAccuracy: false, timeout: 7000, maximumAge: 0 };
                navigator.geolocation.getCurrentPosition(function(pos){
                    document.getElementById('lat').value = pos.coords.latitude;
                    document.getElementById('lng').value = pos.coords.longitude;
                    // format YYYY-MM-DD HH:MM:SS
                    const dt = new Date();
                    const pad = n => (n<10?'0':'')+n;
                    const gpsTime = dt.getFullYear() + '-' + pad(dt.getMonth()+1) + '-' + pad(dt.getDate()) + ' ' + pad(dt.getHours()) + ':' + pad(dt.getMinutes()) + ':' + pad(dt.getSeconds());
                    document.getElementById('gps_time').value = gpsTime;
                    document.getElementById('gps_source').value = 'browser_geo';
                    form.submit();
                }, function(err){
                    // user denied or timeout
                    document.getElementById('gps_source').value = 'denied_or_timeout';
                    submitBtn.disabled = false;
                    form.submit();
                }, geoOpts);
            });
        })();
        </script>

        <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
<style>
body { background: linear-gradient(135deg, #c9e5ff 0%, #f8e7ff 100%);}
.main-wrap { max-width:700px; margin:40px auto 0 auto; background:#fff; border-radius:16px; box-shadow:0 6px 32px #3178c615; padding:38px 24px;}
.qr-block { text-align:center; margin-bottom: 30px; }
.qr-inner { display: flex; flex-direction: column; align-items: center; }
.qr-title { font-weight:700; font-size:1.2em; color:#3178c6; margin-bottom:12px;}
.form-label { color:#3178c6; font-weight:600;}
.msg-success { background: linear-gradient(90deg,#e0f7fa 60%, #f3e5f5 100%); color: #3178c6; font-weight:700; border:1px solid #b3d8fd; border-radius:8px; box-shadow:0 2px 12px #3178c62a; padding:18px; font-size:1.19em; text-align:center;}
.msg-error { color: #d62c2c; font-weight:700; }
.checked-name { color:#3178c6; font-size:1.23em; font-weight:800;}
.student-code { color:#6f42c1; font-weight:600; font-size:1.06em;}
.event-title { text-align:center; color:#3178c6; font-size:1.38em; font-weight:700; margin-bottom:24px;}
.event-date { text-align:center; color:#6f42c1; margin-bottom:18px;}
.form-checkin { background: linear-gradient(90deg,#f8fafc 60%, #e2e7f9 100%); border-radius:12px; box-shadow:0 2px 14px #3178c62a; padding:24px 18px;}
.btn-primary { background:#3178c6; font-weight:600;}
.btn-primary:hover { background:#1757a6;}
.btn-finish, .btn-exit { background: #6f42c1; color: #fff; font-weight: 600; border-radius: 7px; margin-top: 16px;}
.btn-finish:hover, .btn-exit:hover { background: #4b2976;}
.bi-check-circle-fill { color:#3178c6; font-size:2em; margin-bottom:8px;}
.admin-action { text-align:center; margin-top:16px;}
/* Tối ưu cho điện thoại */
@media (max-width: 600px) {
    .main-wrap {padding:10px 2px; max-width:100vw;}
    .form-checkin, .qr-block {padding:10px 2px;}
    .event-title{font-size:1.1em;}
    .qr-title{font-size:1em;}
    .form-label{font-size:0.95em;}
    input.form-control {font-size:1em;}
    .btn {font-size:1em;}
    .qr-inner {padding:0;}
}
</style>
</body>
</html>