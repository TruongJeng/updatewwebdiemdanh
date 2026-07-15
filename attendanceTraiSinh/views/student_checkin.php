<?php
/**
 * Trang học sinh tự điểm danh / Màn hình chiếu QR cho BTC
 * URL: student_checkin.php?event_id=X
 * Nếu là BTC (đã login): Hiển thị QR Code thay đổi mỗi 15s.
 * Nếu là HS (chưa login): Hiển thị form nhập mã số.
 */
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../config/db.php';

$isAdmin = isset($_SESSION['user_id']) && in_array($_SESSION['role'], ['admin', 'teacher', 'club_leader', 'staff']);

$eventId = (int)($_GET['event_id'] ?? 0);
if (!$eventId) {
    echo "Thiếu mã sự kiện!";
    exit;
}

// Lấy thông tin sự kiện
$stmt = $pdo->prepare("SELECT id, title, event_date, description, is_active FROM ts_events WHERE id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo "Không tìm thấy sự kiện!";
    exit;
}

$pageTitle = "Điểm danh tự động - " . htmlspecialchars($event['title']);
$full_name = $_SESSION['full_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <?php if ($isAdmin): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <?php endif; ?>
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes popIn {
            0% { transform: scale(0.9); opacity: 0; }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); opacity: 1; }
        }
        .animate-fadeInUp { animation: fadeInUp 0.5s ease-out; }
        .animate-popIn { animation: popIn 0.4s ease-out; }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe', 300: '#93c5fd',
                            400: '#60a5fa', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8',
                            800: '#1e40af', 900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 text-slate-800">

<?php 
if ($isAdmin) {
    include __DIR__ . '/../../includes/header.php';
    include __DIR__ . '/../../includes/sidebar.php';
}
?>

<main class="<?= $isAdmin ? 'ml-0 lg:ml-64 pt-16' : '' ?> min-h-screen bg-slate-50/50 transition-all duration-300 ease-in-out p-4 sm:p-6 flex items-center justify-center">
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

            <?php if (!$event['is_active']): ?>
                <div class="p-8 text-center bg-red-50 rounded-3xl border border-red-100">
                    <div class="w-16 h-16 rounded-full bg-red-100 text-red-500 flex items-center justify-center mx-auto mb-4">
                        <i class="bi bi-lock-fill text-3xl"></i>
                    </div>
                    <h3 class="font-bold text-slate-800 text-lg mb-2">Sự kiện đã đóng</h3>
                    <p class="text-slate-500 text-sm">Không thể điểm danh cho sự kiện này nữa.</p>
                </div>
            <?php else: ?>

                <?php if ($isAdmin): ?>
                    <!-- ============================================== -->
                    <!-- BẢN DÀNH CHO ADMIN: HIỂN THỊ MÃ QR TỰ ĐỔI 15S  -->
                    <!-- ============================================== -->
                    <div class="bg-slate-50 rounded-3xl p-8 border border-slate-200 flex flex-col items-center justify-center relative overflow-hidden group shadow-inner">
                        <div class="absolute inset-0 bg-gradient-to-br from-primary-500/5 to-purple-500/5 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        
                        <h3 class="text-lg font-bold text-slate-700 mb-6 text-center z-10 flex items-center gap-2">
                            <i class="bi bi-phone"></i> Quét mã QR này để điểm danh
                        </h3>
                        
                        <!-- Lớp overlay loading -->
                        <div id="loadingOverlay" class="absolute inset-0 bg-white/60 backdrop-blur-[2px] z-20 flex items-center justify-center transition-opacity duration-300 opacity-0 pointer-events-none">
                            <i class="bi bi-arrow-repeat text-4xl text-primary-600 animate-spin"></i>
                        </div>

                        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 z-10 mb-8 transition-transform duration-500 hover:scale-105 hover:shadow-md relative">
                            <div id="qrcode" class="flex justify-center items-center"></div>
                        </div>
                        
                        <div class="text-center z-10 w-full max-w-sm">
                            <div class="w-full h-1 bg-slate-200 rounded-full mb-4 overflow-hidden relative">
                                <div id="progressFill" class="absolute top-0 left-0 h-full bg-primary-500 origin-left"></div>
                            </div>
                            <p class="text-sm font-medium text-slate-500 mb-3">Hoặc truy cập link trực tiếp (tự động đổi sau 15s):</p>
                            <div class="bg-white px-4 py-3.5 rounded-xl border border-slate-200 text-xs font-mono text-primary-600 shadow-sm flex items-center justify-between relative group/link cursor-pointer hover:border-primary-400 hover:ring-2 hover:ring-primary-500/20 transition-all overflow-hidden" onclick="copyLink()">
                                <span id="qr-link" class="truncate pr-4 mr-2 text-slate-400">Đang tạo...</span>
                                <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center border border-slate-100 group-hover/link:bg-primary-50 group-hover/link:border-primary-200 transition-colors shrink-0">
                                    <i class="bi bi-clipboard text-slate-400 group-hover/link:text-primary-600 transition-colors"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-center gap-3">
                        <a href="ts_admin_map.php?event_id=<?= $eventId ?>" class="inline-flex items-center justify-center gap-2 bg-slate-800 hover:bg-slate-900 text-white px-6 py-3 rounded-xl font-semibold transition-all shadow-sm hover:shadow-md">
                            <i class="bi bi-geo-alt text-lg"></i> Xem bản đồ
                        </a>
                    </div>

                    <style>
                        .animate-drain { animation: drain 15s linear infinite; }
                        @keyframes drain { from { width: 100%; } to { width: 0%; } }
                    </style>

                    <script>
                    const eventId = <?= $eventId ?>;
                    const qrContainer = document.getElementById('qrcode');
                    let currentUrl = '';

                    function fetchQRToken() {
                        const overlay = document.getElementById('loadingOverlay');
                        overlay.classList.remove('opacity-0');

                        fetch('../api/get_qr_token.php?event_id=' + eventId)
                            .then(r => r.json())
                            .then(data => {
                                if(data.success) {
                                    currentUrl = data.url;
                                    document.getElementById('qr-link').textContent = currentUrl;
                                    
                                    qrContainer.innerHTML = '';
                                    const size = window.innerWidth < 500 ? 200 : 260;
                                    new QRCode(qrContainer, {
                                        text: currentUrl,
                                        width: size,
                                        height: size,
                                        colorDark : "#0f172a",
                                        colorLight : "#ffffff",
                                        correctLevel : QRCode.CorrectLevel.L
                                    });

                                    // Reset thanh progress
                                    const fill = document.getElementById('progressFill');
                                    fill.classList.remove('animate-drain');
                                    void fill.offsetWidth;
                                    fill.classList.add('animate-drain');
                                }
                            })
                            .finally(() => {
                                overlay.classList.add('opacity-0');
                            });
                    }

                    function copyLink() {
                        if (!currentUrl) return;
                        navigator.clipboard.writeText(currentUrl).then(() => {
                            const icon = document.querySelector('.bi-clipboard');
                            const container = icon.closest('.w-8');
                            icon.classList.replace('bi-clipboard', 'bi-check2');
                            icon.classList.replace('text-slate-400', 'text-emerald-600');
                            container.classList.replace('bg-slate-50', 'bg-emerald-50');
                            setTimeout(() => {
                                icon.classList.replace('bi-check2', 'bi-clipboard');
                                icon.classList.replace('text-emerald-600', 'text-slate-400');
                                container.classList.replace('bg-emerald-50', 'bg-slate-50');
                            }, 2000);
                        });
                    }

                    fetchQRToken();
                    setInterval(fetchQRToken, 15000);
                    </script>

                <?php else: ?>
                    <!-- ============================================== -->
                    <!-- BẢN DÀNH CHO HỌC SINH: NHẬP MÃ SỐ              -->
                    <!-- ============================================== -->
                    
                    <!-- Xác thực Token A -->
                    <?php
                    $token = $_GET['t'] ?? '';
                    $isValidToken = false;
                    if ($token) {
                        $decoded = base64_decode($token);
                        if (strpos($decoded, '|') !== false) {
                            list($tEventId, $time, $hash) = explode('|', $decoded);
                            $secret = 'clbkynang_qr_secret_2026';
                            $expectedHash = hash_hmac('sha256', "$tEventId|$time", $secret);
                            // Token hợp lệ nếu chữ ký đúng, đúng event, và thời gian không quá 20s
                            if ($hash === $expectedHash && (int)$tEventId === $eventId && (time() - $time <= 20)) {
                                $isValidToken = true;
                            }
                        }
                    }
                    ?>

                    <?php if (!$isValidToken): ?>
                        <div class="p-8 text-center bg-red-50 rounded-3xl border border-red-100">
                            <div class="w-16 h-16 rounded-full bg-red-100 text-red-500 flex items-center justify-center mx-auto mb-4">
                                <i class="bi bi-qr-code text-3xl"></i>
                            </div>
                            <h3 class="font-bold text-slate-800 text-lg mb-2">Mã QR đã hết hạn!</h3>
                            <p class="text-slate-500 text-sm">Mã QR này chỉ có hiệu lực trong 15 giây. Vui lòng hướng camera lên màn hình chiếu để quét lại mã mới nhất.</p>
                        </div>
                    <?php else: ?>
                        <!-- Form -->
                        <div class="bg-slate-50 p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-inner" id="formSection">
                            <h3 class="text-xl font-bold text-slate-800 mb-6 flex items-center justify-center gap-2">
                                <i class="bi bi-person-badge text-primary-500"></i>
                                Nhập mã trại sinh
                            </h3>
                            
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-1" for="studentCode">Mã trại sinh (Không bắt buộc)</label>
                                    <input type="text" id="studentCode" inputmode="numeric" pattern="[0-9]*"
                                        class="w-full px-4 py-3 bg-white border border-slate-300 rounded-xl text-slate-800 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none transition-all placeholder:text-slate-400 shadow-sm"
                                        placeholder="Ví dụ: 250001" autofocus>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-1" for="fullName">Họ và tên *</label>
                                    <input type="text" id="fullName" required
                                        class="w-full px-4 py-3 bg-white border border-slate-300 rounded-xl text-slate-800 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none transition-all placeholder:text-slate-400 shadow-sm"
                                        placeholder="Nguyễn Văn A">
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-slate-700 mb-1" for="className">Lớp *</label>
                                        <input type="text" id="className" required
                                            class="w-full px-4 py-3 bg-white border border-slate-300 rounded-xl text-slate-800 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none transition-all placeholder:text-slate-400 shadow-sm"
                                            placeholder="10A1">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-slate-700 mb-1" for="email">Email *</label>
                                        <input type="email" id="email" required
                                            class="w-full px-4 py-3 bg-white border border-slate-300 rounded-xl text-slate-800 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none transition-all placeholder:text-slate-400 shadow-sm"
                                            placeholder="email@gmail.com">
                                    </div>
                                </div>

                                <!-- GPS Status -->
                                <div id="gpsStatus" class="flex items-center gap-2 px-4 py-3 rounded-xl bg-white border border-slate-200 text-sm font-medium text-slate-500 shadow-sm mt-2">
                                    <i class="bi bi-geo-alt animate-pulse text-amber-500"></i>
                                    <span>Đang định vị GPS...</span>
                                </div>

                                <button id="btnSubmit" onclick="submitCheckin()"
                                    class="w-full mt-2 flex justify-center items-center gap-2 bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white py-3.5 rounded-xl font-bold transition-all shadow-lg shadow-primary-500/30 hover:shadow-primary-500/50 text-lg group">
                                    <i class="bi bi-check2-circle group-hover:scale-110 transition-transform"></i> Xác nhận
                                </button>

                                <div id="message" class="empty:hidden"></div>
                            </div>
                        </div>

                        <!-- Kết quả -->
                        <div id="resultSection" class="hidden bg-slate-50 p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-inner text-center animate-popIn">
                            <div class="w-20 h-20 rounded-full bg-emerald-100 border-4 border-white shadow-sm flex items-center justify-center mx-auto mb-4 relative">
                                <img id="resultAvatar" class="w-full h-full rounded-full object-cover" src="" alt="">
                                <div class="absolute -bottom-1 -right-1 w-7 h-7 bg-emerald-500 border-2 border-white rounded-full flex items-center justify-center text-white">
                                    <i class="bi bi-check font-bold"></i>
                                </div>
                            </div>
                            <h3 id="resultName" class="text-xl font-extrabold text-slate-800 mb-1"></h3>
                            <p id="resultClass" class="text-sm text-slate-500 font-medium mb-4"></p>
                            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-emerald-100 text-emerald-700 font-bold text-sm border border-emerald-200 shadow-sm">
                                <i class="bi bi-check-circle-fill"></i> Đã ghi nhận điểm danh
                            </div>
                            
                            <p class="mt-6 text-sm text-slate-500">Bạn có thể đóng trang này.</p>
                        </div>

                        <script>
                        const EVENT_ID = <?= $eventId ?>;
                        let gpsData = { lat: null, lng: null, gps_time: null, gps_source: 'unknown' };

                        (function getGPS() {
                            const statusEl = document.getElementById('gpsStatus');
                            if (!navigator.geolocation) {
                                gpsData.gps_source = 'unsupported';
                                statusEl.innerHTML = '<i class="bi bi-geo-alt-fill text-amber-500"></i> <span>Trình duyệt không hỗ trợ</span>';
                                return;
                            }

                            navigator.geolocation.getCurrentPosition(
                                function(pos) {
                                    gpsData.lat = pos.coords.latitude;
                                    gpsData.lng = pos.coords.longitude;
                                    gpsData.gps_source = 'browser_geo';

                                    const dt = new Date();
                                    const pad = n => (n < 10 ? '0' : '') + n;
                                    gpsData.gps_time = dt.getFullYear() + '-' + pad(dt.getMonth()+1) + '-' + pad(dt.getDate()) + ' ' + pad(dt.getHours()) + ':' + pad(dt.getMinutes()) + ':' + pad(dt.getSeconds());

                                    statusEl.innerHTML = '<i class="bi bi-geo-alt-fill text-emerald-500"></i> <span class="text-emerald-600">Đã định vị thành công</span>';
                                },
                                function(err) {
                                    gpsData.gps_source = 'denied_or_timeout';
                                    statusEl.innerHTML = '<i class="bi bi-geo-alt-fill text-red-400"></i> <span class="text-red-500">Không lấy được vị trí GPS</span>';
                                },
                                { enableHighAccuracy: false, timeout: 7000, maximumAge: 0 }
                            );
                        })();

                        function submitCheckin() {
                            const code = document.getElementById('studentCode').value.trim();
                            const fullName = document.getElementById('fullName').value.trim();
                            const className = document.getElementById('className').value.trim();
                            const email = document.getElementById('email').value.trim();
                            const btn = document.getElementById('btnSubmit');
                            const msg = document.getElementById('message');
                            msg.innerHTML = '';

                            if (!fullName || !className || !email) {
                                showMsg('Vui lòng nhập đầy đủ Họ tên, Lớp và Email', 'error');
                                return;
                            }

                            if (!gpsData.lat || !gpsData.lng) {
                                showMsg('Bạn phải cho phép truy cập vị trí (GPS) để có thể điểm danh. Vui lòng bật vị trí và tải lại trang.', 'error');
                                return;
                            }

                            btn.disabled = true;
                            btn.innerHTML = '<i class="bi bi-arrow-repeat animate-spin"></i> Đang xử lý...';
                            btn.classList.add('opacity-75');

                            fetch('../api/student_checkin_api.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    student_code: code,
                                    full_name: fullName,
                                    class: className,
                                    email: email,
                                    event_id: EVENT_ID,
                                    lat: gpsData.lat,
                                    lng: gpsData.lng,
                                    gps_time: gpsData.gps_time,
                                    gps_source: gpsData.gps_source
                                })
                            })
                            .then(r => r.json())
                            .then(data => {
                                if (data.success) {
                                    document.getElementById('formSection').classList.add('hidden');
                                    document.getElementById('resultSection').classList.remove('hidden');
                                    document.getElementById('resultName').textContent = data.student.name;
                                    document.getElementById('resultClass').textContent = 'Lớp ' + data.student.class;
                                    document.getElementById('resultAvatar').src = data.student.avatar;
                                } else {
                                    showMsg(data.message, data.already_checked ? 'warn' : 'error');
                                }
                            })
                            .catch(() => {
                                showMsg('Lỗi kết nối mạng. Vui lòng thử lại.', 'error');
                            })
                            .finally(() => {
                                btn.disabled = false;
                                btn.innerHTML = '<i class="bi bi-check2-circle"></i> Xác nhận';
                                btn.classList.remove('opacity-75');
                            });
                        }

                        function showMsg(text, type) {
                            const colors = {
                                error: 'bg-red-50 border-red-200 text-red-700',
                                warn: 'bg-amber-50 border-amber-200 text-amber-700'
                            };
                            const icons = {
                                error: 'bi-exclamation-triangle-fill',
                                warn: 'bi-info-circle-fill'
                            };
                            document.getElementById('message').innerHTML = `
                                <div class="mt-4 p-4 ${colors[type]} border rounded-xl text-sm font-medium flex items-center gap-3 animate-fadeInUp">
                                    <i class="bi ${icons[type]} text-xl"></i> ${text}
                                </div>
                            `;
                        }

                        document.getElementById('studentCode')?.addEventListener('keypress', e => {
                            if (e.key === 'Enter') submitCheckin();
                        });
                        </script>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Footer -->
        <div class="text-center mt-6 text-xs text-slate-400 font-medium mb-8">
            CLB Kỹ năng Đoàn – Hội • Trường THPT Lý Thường Kiệt
        </div>
    </div>
</main>
<?php 
if ($isAdmin) {
    include __DIR__ . '/../../includes/footer.php';
}
?>
</body>
</html>
