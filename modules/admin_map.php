<?php
// admin_map.php
session_start();
require_once __DIR__ . '/../includes/db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Kiểm tra quyền admin (tùy biến theo app của bạn)
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$event_id = $_GET['event_id'] ?? '';
if (!$event_id) {
    echo "Thiếu event_id";
    exit();
}

// Lấy event + attendances (chỉ attendance có lat/lng)
$stmt = $pdo->prepare("SELECT id, title, event_date, is_closed FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();
if (!$event) { echo "Không tìm thấy sự kiện"; exit(); }

$stmt = $pdo->prepare("
    SELECT a.id, a.student_id, a.lat, a.lng, a.gps_time, s.full_name, s.class, s.email, a.created_at
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    WHERE a.event_id = ?
    ORDER BY a.created_at ASC
");
$stmt->execute([$event_id]);
$attendances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// CSRF token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_token'];
?>
<?php
$pageTitle = "Bản đồ điểm danh - " . htmlspecialchars($event['title']);
include '../includes/header.php';
include '../includes/sidebar.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
/* Leaflet map needs explicitly defined height */
#map { height: 60vh; z-index: 10; }
</style>

<main class="ml-0 lg:ml-64 pt-4 min-h-screen bg-slate-50/50 transition-all duration-300 ease-in-out p-4 sm:p-6 lg:p-8">
    <div class="max-w-7xl mx-auto pb-12">
        <!-- Back Link & Actions -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <div class="flex items-center gap-3">
                <a href="../modules/attendance_qr.php?event_id=<?= urlencode($event_id) ?>" class="text-slate-500 hover:text-primary-600 transition-colors flex items-center gap-1.5 text-sm font-medium bg-white px-3 py-1.5 rounded-full border border-slate-200 shadow-sm hover:shadow">
                    <i class="bi bi-arrow-left"></i> Quay lại QR
                </a>
                <a href="../dashboard.php" class="text-slate-500 hover:text-slate-700 transition-colors flex items-center gap-1.5 text-sm font-medium bg-white px-3 py-1.5 rounded-full border border-slate-200 shadow-sm hover:shadow">
                    <i class="bi bi-house-door"></i> Trang chủ
                </a>
            </div>
            
            <div class="flex items-center gap-3">
                <?php if (!$event['is_closed']): ?>
                    <button id="confirmBtn" class="flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg font-semibold transition-all shadow-sm text-sm">
                        <i class="bi bi-lock-fill"></i> Kết thúc điểm danh
                    </button>
                <?php else: ?>
                    <button id="reopenBtn" class="flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg font-semibold transition-all shadow-sm text-sm">
                        <i class="bi bi-unlock-fill"></i> Mở lại điểm danh
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Header -->
        <div class="flex items-center gap-3 mb-6">
            <div class="w-12 h-12 rounded-xl bg-primary-100 text-primary-600 flex items-center justify-center shadow-sm">
                <i class="bi bi-geo-alt text-2xl"></i>
            </div>
            <div>
                <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">BẢN ĐỒ ĐIỂM DANH</h2>
                <p class="text-sm font-medium text-slate-500 mt-1"><?= htmlspecialchars($event['title']) ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Map Column -->
            <div class="lg:col-span-2 flex flex-col gap-6">
                <div class="bg-white p-4 rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100">
                    <div id="map" class="rounded-xl border border-slate-200 shadow-inner"></div>
                </div>
            </div>

            <!-- List Column -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 flex flex-col h-full max-h-[calc(60vh+2rem)]">
                    <div class="p-5 border-b border-slate-100">
                        <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                            <i class="bi bi-list-check text-primary-500"></i> Danh sách (<span id="count-att"><?= count($attendances) ?></span>)
                        </h3>
                    </div>
                    
                    <div class="p-2 overflow-y-auto flex-1">
                        <?php if (count($attendances) > 0): ?>
                            <ul class="space-y-2">
                            <?php foreach ($attendances as $a): ?>
                                <li class="p-4 rounded-xl hover:bg-slate-50 border border-transparent hover:border-slate-100 transition-colors flex items-start justify-between gap-3 group">
                                    <div>
                                        <div class="font-bold text-slate-800 mb-0.5">
                                            <?= htmlspecialchars($a['full_name']) ?> 
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-600 ml-1"><?= htmlspecialchars($a['class']) ?></span>
                                        </div>
                                        <div class="text-xs text-slate-500 flex items-center gap-1 mt-1">
                                            <i class="bi bi-clock"></i> <?= htmlspecialchars($a['created_at']) ?>
                                        </div>
                                        <?php if ($a['lat']): ?>
                                        <div class="text-xs text-emerald-600 flex items-center gap-1 mt-1">
                                            <i class="bi bi-geo-alt-fill"></i> Đã đính kèm GPS
                                        </div>
                                        <?php else: ?>
                                        <div class="text-xs text-amber-500 flex items-center gap-1 mt-1">
                                            <i class="bi bi-geo-alt"></i> Không có GPS
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <button class="w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white flex items-center justify-center shrink-0 opacity-0 group-hover:opacity-100 transition-all btn-delete" data-id="<?= $a['id'] ?>" title="Xóa điểm danh">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="text-center py-10">
                                <div class="w-12 h-12 rounded-full bg-slate-50 text-slate-300 flex items-center justify-center mx-auto mb-3">
                                    <i class="bi bi-inbox text-2xl"></i>
                                </div>
                                <p class="text-slate-500 text-sm">Chưa có ai điểm danh.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const attendances = <?= json_encode($attendances, JSON_UNESCAPED_UNICODE) ?>;
const eventId = <?= json_encode($event_id) ?>;
const csrfToken = <?= json_encode($csrf) ?>;

const map = L.map('map').setView([10.762622, 106.660172], 6); // Default view
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
const markers = {};

attendances.forEach(a=>{
    if (a.lat && a.lng) {
        const m = L.marker([parseFloat(a.lat), parseFloat(a.lng)]).addTo(map);
        const popup = `<div class="p-1">
            <b class="text-sm text-slate-800">${escapeHtml(a.full_name)}</b> <span class="text-xs bg-slate-100 px-1 py-0.5 rounded ml-1">${escapeHtml(a.class)}</span><br>
            <div class="text-xs text-slate-500 mt-1">${a.gps_time ? 'GPS Time: '+escapeHtml(a.gps_time) : ''}</div>
            <div class="text-xs text-slate-500">Checkin: ${escapeHtml(a.created_at)}</div>
            <button class="mt-2 text-xs bg-red-50 text-red-600 hover:bg-red-600 hover:text-white px-2 py-1 rounded transition-colors" onclick="deleteAttendance(${a.id})">Xóa điểm danh</button>
        </div>`;
        m.bindPopup(popup);
        markers[a.id] = m;
    }
});

const all = Object.values(markers);
if (all.length) {
    const group = L.featureGroup(all);
    map.fitBounds(group.getBounds().pad(0.2));
}

function deleteAttendance(id){
    if (!confirm('Bạn có chắc muốn xóa điểm danh này?')) return;
    fetch('delete_attendance.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ attendance_id: id, event_id: eventId, csrf: csrfToken })
    }).then(r=>r.json()).then(j=>{
        if (j.success) {
            alert('Xóa thành công');
            if (markers[id]) map.removeLayer(markers[id]);
            location.reload();
        } else alert('Lỗi: '+(j.error||'Không thể xóa'));
    }).catch(e=>alert('Lỗi: '+e));
}

document.querySelectorAll('.btn-delete').forEach(btn=> btn.addEventListener('click', function(){ deleteAttendance(this.dataset.id); }));

// Xử lý đóng điểm danh
document.getElementById('confirmBtn')?.addEventListener('click', function() {
    if (!confirm('Sau khi xác nhận, điểm danh sẽ đóng và không thể thay đổi. Bạn đồng ý?')) return;

    fetch('confirm_event.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ event_id: eventId, csrf: csrfToken })
    }).then(r => r.json()).then(j => {
        if (j.success) {
            alert('Sự kiện đã được đóng.');
            location.reload();
        } else alert('Lỗi: ' + (j.error || 'Không thể đóng điểm danh.'));
    });
});

// Xử lý mở lại điểm danh
document.getElementById('reopenBtn')?.addEventListener('click', function() {
    if (!confirm('Bạn có chắc chắn muốn mở lại điểm danh?')) return;

    fetch('reopen_event.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ event_id: eventId, csrf: csrfToken })
    }).then(r => r.json()).then(j => {
        if (j.success) {
            alert('Điểm danh đã được mở lại.');
            location.reload();
        } else alert('Lỗi: ' + (j.error || 'Không thể mở lại điểm danh.'));
    });
});

function escapeHtml(s){ return (s||'').toString().replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
</script>

<?php include '../includes/footer.php'; ?>