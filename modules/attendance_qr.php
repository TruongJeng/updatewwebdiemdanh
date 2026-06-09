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
    static $env = null;
    if ($env === null) {
        $env = require __DIR__ . '/../config/env.php';
    }
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $env['mail']['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $env['mail']['username'];
        $mail->Password = $env['mail']['password'];
        $mail->SMTPSecure = $env['mail']['secure'];
        $mail->Port = $env['mail']['port'];

        $mail->setFrom($env['mail']['from_email'], $env['mail']['from_name']);
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
<?php
if (isset($_SESSION['user_id'])) {
    include '../includes/sidebar.php';
}
?>

<main class="<?= isset($_SESSION['user_id']) ? 'ml-0 lg:ml-64' : '' ?> min-h-screen bg-slate-50/50 transition-all duration-300 ease-in-out p-4 sm:p-6 flex items-center justify-center">
    <div class="w-full max-w-2xl bg-white rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 overflow-hidden relative mt-8">
        <!-- Decorator -->
        <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-primary-400 via-primary-500 to-primary-600"></div>

        <div class="p-8 sm:p-10">
            <!-- Event Info -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-primary-50 text-primary-600 mb-4 shadow-sm border border-primary-100">
                    <i class="bi bi-qr-code-scan text-3xl"></i>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-800 tracking-tight mb-2">
                    <?= htmlspecialchars($event['title']) ?>
                </h1>
                <?php if ($event['event_date']): ?>
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-slate-100 text-slate-600 text-sm font-medium mt-2">
                    <i class="bi bi-calendar-event"></i>
                    <?= date('d/m/Y H:i', strtotime($event['event_date'])) ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if (isset($_SESSION['user_id'])): ?>
                <!-- Admin View: Show QR Code -->
                <div class="bg-slate-50 rounded-3xl p-8 border border-slate-200 flex flex-col items-center justify-center relative overflow-hidden group shadow-inner">
                    <div class="absolute inset-0 bg-gradient-to-br from-primary-500/5 to-purple-500/5 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    
                    <h3 class="text-lg font-bold text-slate-700 mb-6 text-center z-10 flex items-center gap-2">
                        <i class="bi bi-phone"></i> Quét mã QR này để điểm danh
                    </h3>
                    
                    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 z-10 mb-8 transition-transform duration-500 hover:scale-105 hover:shadow-md">
                        <div id="qrcode" class="flex justify-center items-center"></div>
                    </div>
                    
                    <div class="text-center z-10 w-full max-w-sm">
                        <p class="text-sm font-medium text-slate-500 mb-3">Hoặc truy cập link trực tiếp:</p>
                        <div class="bg-white px-4 py-3.5 rounded-xl border border-slate-200 text-sm font-mono text-primary-600 shadow-sm flex items-center justify-between relative group/link cursor-pointer hover:border-primary-400 hover:ring-2 hover:ring-primary-500/20 transition-all overflow-hidden" onclick="copyLink()">
                            <span id="qr-link" class="truncate pr-4 mr-2"></span>
                            <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center border border-slate-100 group-hover/link:bg-primary-50 group-hover/link:border-primary-200 transition-colors shrink-0">
                                <i class="bi bi-clipboard text-slate-400 group-hover/link:text-primary-600 transition-colors"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-center">
                    <a href="admin_map.php?event_id=<?= urlencode($event_id) ?>" class="inline-flex items-center justify-center gap-2 bg-slate-800 hover:bg-slate-900 text-white px-6 py-3 rounded-xl font-semibold transition-all shadow-sm hover:shadow-md">
                        <i class="bi bi-geo-alt text-lg"></i> Xem bản đồ & Kết thúc
                    </a>
                </div>

                <script src="https://cdn.jsdelivr.net/npm/qrcodejs/qrcode.min.js"></script>
                <script>
                function updateQR() {
                    var t = Math.floor(Date.now()/1000/30);
                    var url = window.location.origin + window.location.pathname + window.location.search;
                    if(url.indexOf('?') > -1) url += '&t=' + t; else url += '?t=' + t;
                    
                    document.getElementById('qr-link').textContent = url;
                    // For copying
                    window.currentUrl = url;

                    document.getElementById('qrcode').innerHTML = '';
                    var size = window.innerWidth < 500 ? 200 : 260;
                    new QRCode(document.getElementById("qrcode"), {
                        text: url,
                        width: size,
                        height: size,
                        colorDark : "#0f172a", // slate-900
                        colorLight : "#ffffff",
                        correctLevel : QRCode.CorrectLevel.H
                    });
                }
                
                function copyLink() {
                    navigator.clipboard.writeText(window.currentUrl).then(() => {
                        const icon = document.querySelector('.bi-clipboard');
                        const container = icon.closest('.w-8');
                        
                        icon.classList.replace('bi-clipboard', 'bi-check2');
                        icon.classList.replace('text-slate-400', 'text-emerald-600');
                        icon.classList.replace('group-hover/link:text-primary-600', 'group-hover/link:text-emerald-600');
                        
                        container.classList.replace('bg-slate-50', 'bg-emerald-50');
                        container.classList.replace('group-hover/link:bg-primary-50', 'group-hover/link:bg-emerald-100');
                        container.classList.replace('border-slate-100', 'border-emerald-200');
                        
                        setTimeout(() => {
                            icon.classList.replace('bi-check2', 'bi-clipboard');
                            icon.classList.replace('text-emerald-600', 'text-slate-400');
                            icon.classList.replace('group-hover/link:text-emerald-600', 'group-hover/link:text-primary-600');
                            
                            container.classList.replace('bg-emerald-50', 'bg-slate-50');
                            container.classList.replace('group-hover/link:bg-emerald-100', 'group-hover/link:bg-primary-50');
                            container.classList.replace('border-emerald-200', 'border-slate-100');
                        }, 2000);
                    });
                }

                updateQR();
                setInterval(updateQR, 10000);
                window.addEventListener('resize', updateQR);
                </script>

            <?php else: ?>
                <!-- Student View: Check-in Form -->
                
                <?php if ($msg): ?>
                    <div class="mb-8 p-6 rounded-2xl <?= $success ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-red-50 border border-red-200 text-red-800' ?> text-center shadow-sm">
                        <?php if ($success): ?>
                            <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto mb-4">
                                <i class="bi bi-check-lg text-3xl"></i>
                            </div>
                        <?php else: ?>
                            <div class="w-16 h-16 rounded-full bg-red-100 text-red-600 flex items-center justify-center mx-auto mb-4">
                                <i class="bi bi-exclamation-triangle text-3xl"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="text-lg font-medium leading-relaxed">
                            <?= $msg ?>
                        </div>
                        
                        <?php if ($success): ?>
                        <div class="mt-6">
                            <form method="get" action="../">
                                <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2.5 rounded-xl font-semibold transition-all shadow-sm">
                                    <i class="bi bi-door-open"></i> Đóng / Tắt điểm danh
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!$success): ?>
                <div class="bg-slate-50 p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-inner">
                    <h3 class="text-xl font-bold text-slate-800 mb-6 flex items-center justify-center gap-2">
                        <i class="bi bi-person-badge text-primary-500"></i>
                        Thông tin cá nhân
                    </h3>
                    
                    <form id="checkinForm" method="post" autocomplete="off" class="space-y-5">
                        <!-- Hidden fields for GPS -->
                        <input type="hidden" name="lat" id="lat" value="">
                        <input type="hidden" name="lng" id="lng" value="">
                        <input type="hidden" name="gps_time" id="gps_time" value="">
                        <input type="hidden" name="gps_source" id="gps_source" value="">

                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5" for="full_name">Họ và tên</label>
                            <input type="text" id="full_name" name="full_name" required autocomplete="name" class="w-full px-4 py-3 rounded-xl border border-slate-300 bg-white text-slate-800 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none transition-all" placeholder="Nhập đầy đủ họ và tên">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5" for="class">Lớp</label>
                            <input type="text" id="class" name="class" required autocomplete="organization" class="w-full px-4 py-3 rounded-xl border border-slate-300 bg-white text-slate-800 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none transition-all" placeholder="Ví dụ: 10A1">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5" for="email">Email liên hệ</label>
                            <input type="email" id="email" name="email" required autocomplete="email" class="w-full px-4 py-3 rounded-xl border border-slate-300 bg-white text-slate-800 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none transition-all" placeholder="email@example.com">
                        </div>
                        
                        <div class="pt-4">
                            <button id="submitBtn" type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white px-6 py-3.5 rounded-xl font-bold transition-all shadow-sm hover:shadow flex items-center justify-center gap-2 text-lg group">
                                <i class="bi bi-check2-circle group-hover:scale-110 transition-transform"></i> Xác nhận điểm danh
                            </button>
                        </div>
                    </form>
                </div>

                <script>
                // Geolocation
                (function(){
                    const form = document.getElementById('checkinForm');
                    const submitBtn = document.getElementById('submitBtn');

                    form.addEventListener('submit', function(e){
                        if (document.getElementById('lat').value !== '' || document.getElementById('lng').value !== '') {
                            return; 
                        }
                        e.preventDefault();
                        
                        // Thay đổi state button
                        const originalContent = submitBtn.innerHTML;
                        submitBtn.disabled = true;
                        submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
                        submitBtn.innerHTML = '<i class="bi bi-arrow-repeat animate-spin"></i> Đang lấy vị trí...';

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
                            
                            const dt = new Date();
                            const pad = n => (n<10?'0':'')+n;
                            const gpsTime = dt.getFullYear() + '-' + pad(dt.getMonth()+1) + '-' + pad(dt.getDate()) + ' ' + pad(dt.getHours()) + ':' + pad(dt.getMinutes()) + ':' + pad(dt.getSeconds());
                            
                            document.getElementById('gps_time').value = gpsTime;
                            document.getElementById('gps_source').value = 'browser_geo';
                            form.submit();
                        }, function(err){
                            document.getElementById('gps_source').value = 'denied_or_timeout';
                            submitBtn.disabled = false;
                            form.submit();
                        }, geoOpts);
                    });
                })();
                </script>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>