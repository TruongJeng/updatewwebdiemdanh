<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/session.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','club_leader','staff'])) {
    die('Không có quyền');
}

//if (!isset($_SESSION['attendance_session_id'])) {
//    die('Chưa mở phiên điểm danh');
//}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Danh sách điểm danh</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link rel="icon" type="image/png" href="/hethongdiemdanh/assets/logo_CLB.png">

<style>
:root {
    --primary:#3178c6;
    --bg:#f4faff;
    --green:#54ed68;
    --red:#f00000;
}
body{background:var(--bg);font-family:system-ui;}
.page-header{
    background:linear-gradient(90deg,#3178c6,#6fa6e3);
    color:#fff;padding:14px 18px;
    font-size:18px;font-weight:700;
    display:flex;gap:10px;
}
.card-att{
    background:#fff;border-radius:14px;
    padding:14px 16px;margin-bottom:12px;
    box-shadow:0 4px 16px #3178c61a;
    border-left:6px solid var(--primary);
}
.card-att .name{font-weight:700;color:#1f3c6d}
.card-att .meta{font-size:14px;color:#555}
.badge-in {
    background-color: #eafaf1 ;
    color: #2ecc71 ;
    opacity: 1 ;
}
.badge-out {
    background-color: #fdecea  ;
    color: #e74c3c ;
    opacity: 1 ;
    filter: none ;
}
.table-wrapper{
    background:#fff;border-radius:14px;
    box-shadow:0 4px 18px #3178c618;
    overflow:visible;
}
.stat-card{
    border-radius:14px;
    padding:16px;
    text-align:center;
    box-shadow:0 4px 16px rgba(0,0,0,.12);
}
.stat-num{
    font-size:30px;
    font-weight:800;
}
.stat-text{
    font-size:14px;
    opacity:.9;
}

.badge-history {
  position: relative;
  cursor: pointer;
}

.badge-history:hover .history-tooltip {
  opacity: 1;
  visibility: visible;
  overflow: visible;
  transform: translateY(0);
}

.history-tooltip {
  position: absolute;
  bottom: 125%;
  left: 50%;
  transform: translate(-50%, 5px);
  background: #1f2937;
  color: #fff;
  padding: 10px 12px;
  border-radius: 10px;
  font-size: 13px;
  white-space: nowrap;
  box-shadow: 0 8px 24px rgba(0,0,0,.25);
  opacity: 0;
  visibility: hidden;
  transition: .2s;
  z-index: 99999;
}

.history-tooltip::after {
  content: "";
  position: absolute;
  top: 100%;
  left: 50%;
  transform: translateX(-50%);
  border-width: 6px;
  border-style: solid;
  border-color: #1f2937 transparent transparent transparent;
}

.table thead{background:#f0f6ff}
.table th{font-weight:700;color:#3178c6}
@media(max-width:991px){.desktop-only{display:none}}
@media(min-width:992px){.mobile-only{display:none}}
.page-footer{text-align:center;font-size:13px;color:#666;padding:12px}
.badge {
    opacity: 1 !important;
    filter: none !important;
}

/* CHECK IN */
.badge-in {
    background-color: #eafaf1 !important;
    color: #2ecc71 !important;
    font-weight: 700;
}

/* CHECK OUT */
.badge-out {
    background-color: #fdecea !important;
    color: #e74c3c !important;
    font-weight: 700;
}
</style>
</head>

<body>

<?php
$pageTitle = "Kiểm tra điểm danh trại sinh";
$full_name = $_SESSION['full_name'] ?? '';
include __DIR__ . '/../config/header.php';
?>
<!-- SEARCH -->
<div class="container-fluid px-3 mt-3 mb-2">
    <div class="input-group">
        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
        <input type="text" id="searchBox" class="form-control" placeholder="Tìm theo tên, mã hoặc lớp...">
    </div>
</div>

<div class="container-fluid px-3">
    <div id="searchResult"></div>
</div>

<div class="container-fluid px-3 py-3">

    <!-- MOBILE -->
    <div id="cards" class="mobile-only"></div>

    <!-- DESKTOP -->
    <div class="table-wrapper desktop-only">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Mã</th><th>Họ tên</th><th>Lớp</th>
                    <th>Trạng thái</th><th>Thời gian</th><th>Ban Tổ Chức</th>
                </tr>
            </thead>
            <tbody id="table"></tbody>
        </table>
    </div>
<div class="modal fade" id="historyModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Lịch sử điểm danh</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="historyBody"></div>
    </div>
  </div>
</div>

</div>

<?php include __DIR__ . '/../config/footer.php'; ?>

<script>
const TYPE_LABEL = {
    CHECK_IN: 'Đã Check in',
    CHECK_OUT: 'Đã Check out'
};

function formatType(type){
    return TYPE_LABEL[type] || type;
}

let isSearching = false;

function loadAttendance(){
    if(isSearching) return;

    fetch('../api/get_attendance_list.php')
    .then(r=>r.json())
    .then(res=>{
        if(!res.success) return;

        const cards = document.getElementById('cards');
        const table = document.getElementById('table');

        cards.innerHTML = '';
        table.innerHTML = '';

        res.data.forEach(row=>{
        const currentType = row.type;
        const currentTime = row.scan_time;

        const history = row.history
        ? row.history.split(';;').map(h => {
            const [type, time, pin, btc] = h.split('|');
            return { type, time, pin, btc };
            })
        : [];

            /* ===== MOBILE ===== */
            const card = document.createElement('div');
            card.className = 'card-att att-card';
            card.dataset.name  = row.full_name.toLowerCase();
            card.dataset.class = row.class.toLowerCase();
            card.dataset.code  = row.student_code.toLowerCase();

            card.innerHTML = `
                <div class="name">${row.full_name}</div>
                <div class="meta">${row.student_code} • ${row.class}</div>
                <span class="badge ${currentType==='CHECK_IN'?'badge-in':'badge-out'} mt-2">
                    ${formatType(currentType)}
                </span>
                <div class="meta mt-1">⏰ ${currentTime}</div>
            `;
            cards.appendChild(card);

            /* ===== HISTORY ===== */


            const hoverText = history.length
            ? history.map(h =>
                `${h.type === 'CHECK_IN' ? 'Check-in' : 'Check-out'}: ${h.time}`
                ).join('<br>')
            : 'Chưa có lịch sử';

            /* ===== MÁY TÍNH ===== */
            const tr = document.createElement('tr');
            tr.className = 'att-row';
            tr.dataset.name  = row.full_name.toLowerCase();
            tr.dataset.class = row.class.toLowerCase();
            tr.dataset.code  = row.student_code.toLowerCase();

            tr.innerHTML = `
                <td>${row.student_code}</td>
                <td class="fw-semibold">${row.full_name}</td>
                <td>${row.class}</td>

                <td>
                    <span class="badge-history">
                    <span class="badge ${currentType==='CHECK_IN'?'badge-in':'badge-out'}"
                        onclick='openHistory(${JSON.stringify(history)})'>
                        ${formatType(currentType)}
                    </span>
                    <div class="history-tooltip">${hoverText}</div>
                    </span>
                </td>

                <td>${row.scan_time}</td>
                <td>${row.scanned_by}</td>
            `;
            table.appendChild(tr);

        });
    });
}

//SEARCH
document.getElementById('searchBox').addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();
    isSearching = q !== '';

    // DESKTOP
    document.querySelectorAll('.att-row').forEach(row => {
        const match =
            row.dataset.name.includes(q) ||
            row.dataset.class.includes(q) ||
            row.dataset.code.includes(q);

        row.style.display = match ? '' : 'none';
    });

    // MOBILE
    document.querySelectorAll('.att-card').forEach(card => {
        const match =
            card.dataset.name.includes(q) ||
            card.dataset.class.includes(q) ||
            card.dataset.code.includes(q);

        card.style.display = match ? '' : 'none';
    });
});



function openHistory(history){
    const body = document.getElementById('historyBody');

    if(!history || history.length === 0){
        body.innerHTML = '<i>Chưa có lịch sử</i>';
    } else {
        body.innerHTML = history.map((h,i)=>`
            <div class="mb-2">
              <b>Lần ${i+1}:</b>
              ${h.type === 'CHECK_IN' ? 'Check-in' : 'Check-out'}
              vào lúc <b>${h.time}</b><br>
              do <b>${h.btc}</b><br>
              bằng mã PIN: <b>${h.pin}</b>
            </div>
            <hr>
        `).join('');
    }

    new bootstrap.Modal(
      document.getElementById('historyModal')
    ).show();
}

loadAttendance();
setInterval(loadAttendance, 5000);
</script>

</body>
</html>
