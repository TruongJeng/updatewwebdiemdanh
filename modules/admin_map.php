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
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bản đồ điểm danh - <?= htmlspecialchars($event['title']) ?></title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
#map { width:100%; height:70vh; border-radius:8px; }
.container { max-width:1100px; margin:20px auto; }
.att-list { max-height: 220px; overflow:auto; }
</style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h4>Bản đồ điểm danh - <?= htmlspecialchars($event['title']) ?></h4>
        <div>
            <?php if (!$event['is_closed']): ?>
                <!-- Nút để kết thúc điểm danh -->
                <button id="confirmBtn" class="btn btn-success">Xác nhận kết thúc điểm danh</button>
            <?php else: ?>
                <!-- Nút để mở lại điểm danh -->
                <button id="reopenBtn" class="btn btn-warning">Mở lại điểm danh</button>
            <?php endif; ?>
            <a href="../modules/attendance_qr.php?event_id=<?= urlencode($event_id) ?>" class="btn btn-secondary"> Quay lại</a>
            <a href="../dashboard.php" class="btn btn-secondary">X</a>
        </div>
    </div>

    <div id="map"></div>

    <div class="mt-3">
        <h6>Danh sách điểm danh</h6>
        <div class="att-list list-group">
            <?php foreach ($attendances as $a): ?>
                <div class="list-group-item d-flex justify-content-between align-items-start">
                    <div>
                        <strong><?= htmlspecialchars($a['full_name']) ?></strong> - <?= htmlspecialchars($a['class']) ?>
                        <div class="small text-muted"><?= htmlspecialchars($a['created_at']) ?> <?= ($a['lat'] ? " (GPS {$a['lat']},{$a['lng']})" : "(No GPS)") ?></div>
                    </div>
                    <div>
                        <button class="btn btn-sm btn-danger btn-delete" data-id="<?= $a['id'] ?>">Xóa</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const attendances = <?= json_encode($attendances, JSON_UNESCAPED_UNICODE) ?>;
const eventId = <?= json_encode($event_id) ?>;
const csrfToken = <?= json_encode($csrf) ?>;

const map = L.map('map').setView([21.0, 105.8], 6);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
const markers = {};

attendances.forEach(a=>{
    if (a.lat && a.lng) {
        const m = L.marker([parseFloat(a.lat), parseFloat(a.lng)]).addTo(map);
        const popup = `<div>
            <b>${escapeHtml(a.full_name)}</b><br>${escapeHtml(a.class)}<br>
            ${a.gps_time ? 'GPS: '+escapeHtml(a.gps_time) : ''}<br>
            <small>Pointed at ${escapeHtml(a.created_at)}</small><br>
            <button class="btn btn-sm btn-danger" onclick="deleteAttendance(${a.id})">Xóa điểm danh</button>
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
</body>
</html>