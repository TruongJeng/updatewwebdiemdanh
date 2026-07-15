<?php
/**
 * Admin Map — Xem vị trí điểm danh theo sự kiện (ts_events)
 * URL: ts_admin_map.php?event_id=X
 */
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

$eventId = (int)($_GET['event_id'] ?? 0);
if (!$eventId) {
    echo "Thiếu event_id";
    exit();
}

// Lấy event
$stmt = $pdo->prepare("SELECT * FROM ts_events WHERE id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$event) {
    echo "Không tìm thấy sự kiện";
    exit();
}

// Lấy danh sách điểm danh kèm GPS
$stmt = $pdo->prepare("
    SELECT al.id, al.student_id, al.lat, al.lng, al.gps_time, al.gps_source, al.ip_addr,
           al.type, al.scan_time,
           c.full_name, c.class, c.student_code
    FROM attendance_logs al
    JOIN campers c ON al.student_id = c.id
    JOIN attendance_sessions s ON al.session_id = s.id
    WHERE s.event_id = ?
    ORDER BY al.scan_time ASC
");
$stmt->execute([$eventId]);
$attendances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// CSRF token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_token'];

$pageTitle = "Bản đồ - " . htmlspecialchars($event['title']);
$full_name = $_SESSION['full_name'] ?? '';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style> #map { height: 60vh; z-index: 10; } </style>

<main class="ml-0 lg:ml-64 pt-4 min-h-screen bg-slate-50/50 transition-all duration-300 ease-in-out p-4 sm:p-6 lg:p-8">
    <div class="max-w-7xl mx-auto pb-12">

        <!-- Back -->
        <div class="flex items-center gap-3 mb-6">
            <a href="events.php" class="text-slate-500 hover:text-primary-600 transition-colors flex items-center gap-1.5 text-sm font-medium bg-white px-3 py-1.5 rounded-full border border-slate-200 shadow-sm hover:shadow">
                <i class="bi bi-arrow-left"></i> Quay lại sự kiện
            </a>
        </div>

        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-primary-100 text-primary-600 flex items-center justify-center shadow-sm">
                    <i class="bi bi-geo-alt text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">BẢN ĐỒ ĐIỂM DANH</h2>
                    <p class="text-sm font-medium text-slate-500 mt-1"><?= htmlspecialchars($event['title']) ?></p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <?php if ($event['is_active']): ?>
                <button id="closeEventBtn" class="flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg font-semibold transition-all shadow-sm text-sm">
                    <i class="bi bi-lock-fill"></i> Đóng sự kiện
                </button>
                <?php else: ?>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-slate-100 text-slate-500 text-sm font-bold border border-slate-200">
                    <i class="bi bi-lock-fill"></i> Đã đóng
                </span>
                <button id="reopenEventBtn" class="flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg font-semibold transition-all shadow-sm text-sm">
                    <i class="bi bi-unlock-fill"></i> Mở lại
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Map -->
            <div class="lg:col-span-2">
                <div class="bg-white p-4 rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100">
                    <div id="map" class="rounded-xl border border-slate-200 shadow-inner"></div>
                </div>
            </div>

            <!-- List -->
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
                                        <i class="bi bi-clock"></i> <?= htmlspecialchars($a['scan_time']) ?>
                                    </div>
                                    <div class="text-xs font-bold mt-1 <?= $a['type'] === 'CHECK_IN' ? 'text-emerald-600' : 'text-red-600' ?>">
                                        <?= $a['type'] === 'CHECK_IN' ? 'Check-in' : 'Check-out' ?>
                                    </div>
                                    <?php if ($a['lat']): ?>
                                    <div class="text-xs text-emerald-600 flex items-center gap-1 mt-1">
                                        <i class="bi bi-geo-alt-fill"></i> GPS đính kèm
                                    </div>
                                    <?php else: ?>
                                    <div class="text-xs text-amber-500 flex items-center gap-1 mt-1">
                                        <i class="bi bi-geo-alt"></i> Không có GPS
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($a['ip_addr']): ?>
                                    <div class="text-[10px] text-slate-400 mt-0.5">IP: <?= htmlspecialchars($a['ip_addr']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <button class="w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white flex items-center justify-center shrink-0 opacity-0 group-hover:opacity-100 transition-all btn-delete" data-id="<?= $a['id'] ?>" title="Xóa">
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
<?php include __DIR__ . '/../../includes/footer.php'; ?>

<script>
const attendances = <?= json_encode($attendances, JSON_UNESCAPED_UNICODE) ?>;
const eventId = <?= $eventId ?>;
const csrfToken = <?= json_encode($csrf) ?>;

const map = L.map('map').setView([10.762622, 106.660172], 6);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
const markers = {};

attendances.forEach(a => {
    if (a.lat && a.lng) {
        const color = a.type === 'CHECK_IN' ? '#10b981' : '#ef4444';
        const icon = L.divIcon({
            className: '',
            html: `<div style="background:${color};width:12px;height:12px;border-radius:50%;border:2px solid white;box-shadow:0 2px 6px rgba(0,0,0,0.3)"></div>`,
            iconSize: [12, 12],
            iconAnchor: [6, 6]
        });

        const m = L.marker([parseFloat(a.lat), parseFloat(a.lng)], { icon }).addTo(map);
        const popup = `<div class="p-1" style="font-family:Inter,system-ui,sans-serif">
            <b style="font-size:13px">${esc(a.full_name)}</b> <span style="font-size:11px;background:#f1f5f9;padding:1px 5px;border-radius:4px">${esc(a.class)}</span><br>
            <div style="font-size:11px;color:#64748b;margin-top:4px">${a.type === 'CHECK_IN' ? '✅ Check-in' : '🔴 Check-out'}</div>
            <div style="font-size:11px;color:#64748b">${a.gps_time ? 'GPS: '+esc(a.gps_time) : ''}</div>
            <div style="font-size:11px;color:#64748b">Lúc: ${esc(a.scan_time)}</div>
            ${a.ip_addr ? '<div style="font-size:10px;color:#94a3b8">IP: '+esc(a.ip_addr)+'</div>' : ''}
            <button style="margin-top:6px;font-size:11px;background:#fef2f2;color:#dc2626;border:none;padding:4px 10px;border-radius:6px;cursor:pointer" onclick="deleteLog(${a.id})">Xóa</button>
        </div>`;
        m.bindPopup(popup);
        markers[a.id] = m;
    }
});

const all = Object.values(markers);
if (all.length) {
    map.fitBounds(L.featureGroup(all).getBounds().pad(0.2));
}

function deleteLog(id) {
    if (!confirm('Xóa điểm danh này?')) return;
    fetch('../api/delete_attendance_log.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ log_id: id, csrf: csrfToken })
    }).then(r => r.json()).then(j => {
        if (j.success) {
            if (markers[id]) map.removeLayer(markers[id]);
            location.reload();
        } else alert('Lỗi: ' + (j.message || 'Không thể xóa'));
    }).catch(e => alert('Lỗi: ' + e));
}

document.querySelectorAll('.btn-delete').forEach(btn =>
    btn.addEventListener('click', function() { deleteLog(this.dataset.id); })
);

// Đóng/mở sự kiện
document.getElementById('closeEventBtn')?.addEventListener('click', function() {
    if (!confirm('Đóng sự kiện này? Học sinh sẽ không thể tự điểm danh.')) return;
    fetch('../api/events_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=toggle_active&id=' + eventId + '&active=0'
    }).then(r => r.json()).then(j => {
        if (j.success) location.reload();
        else alert(j.message);
    });
});

document.getElementById('reopenEventBtn')?.addEventListener('click', function() {
    if (!confirm('Mở lại sự kiện này?')) return;
    fetch('../api/events_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=toggle_active&id=' + eventId + '&active=1'
    }).then(r => r.json()).then(j => {
        if (j.success) location.reload();
        else alert(j.message);
    });
});

function esc(s) { return (s||'').toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
</script>
