<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../PHPSpreadsheet/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
require_once __DIR__ . '/../../config/session.php';
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','club_leader','staff'])) {
    die('Không có quyền');
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Thống kê điểm danh</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
:root{--primary:#3178c6;--bg:#f4faff;}
body{background:var(--bg);font-family:system-ui}

.card-att{
  background:#fff;border-radius:14px;padding:14px 16px;margin-bottom:12px;
  box-shadow:0 4px 16px #3178c61a;border-left:6px solid var(--primary)
}
.card-att .name{font-weight:700;color:#1f3c6d}
.card-att .meta{font-size:14px;color:#555}

.badge-in{background:#eafaf1!important;color:#2ecc71!important;font-weight:700}
.badge-out{background:#fdecea!important;color:#e74c3c!important;font-weight:700}
.badge-none{background:#eef2f7!important;color:#6b7280!important;font-weight:700}

.table-wrapper{background:#fff;border-radius:14px;box-shadow:0 4px 18px #3178c618}

.badge-history{position:relative;cursor:pointer}
.badge-history:hover .history-tooltip{opacity:1;visibility:visible;transform:translateY(0)}
.history-tooltip{
  position:absolute;bottom:125%;left:50%;transform:translate(-50%,5px);
  background:#1f2937;color:#fff;padding:10px 12px;border-radius:10px;
  font-size:13px;white-space:nowrap;box-shadow:0 8px 24px rgba(0,0,0,.25);
  opacity:0;visibility:hidden;transition:.2s;z-index:9999
}
.history-tooltip::after{
  content:"";position:absolute;top:100%;left:50%;transform:translateX(-50%);
  border-width:6px;border-style:solid;border-color:#1f2937 transparent transparent
}

@media(max-width:991px){.desktop-only{display:none}}
@media(min-width:992px){.mobile-only{display:none}}
</style>
</head>
<body>
<?php
$pageTitle = "Thống kê điểm danh";
$full_name = $_SESSION['full_name'] ?? '';
include __DIR__ . '/../config/header.php';
?>
<!-- SEARCH -->
<div class="container-fluid px-3 mt-3 mb-2">
  <div class="input-group">
    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
    <input type="text" id="searchBox" class="form-control" placeholder="Tìm theo tên, mã hoặc lớp...">
  </div>
  <a href="../api/export_attendance_excel.php"
    class="btn btn-success mb-3">
    <i class="bi bi-file-earmark-excel"></i>
    Xuất báo cáo Excel
  </a>
</div>

<div class="container-fluid px-3 py-3">

  <!-- MOBILE -->
  <div id="cards" class="mobile-only"></div>

  <!-- DESKTOP -->
  <div class="table-wrapper desktop-only">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Mã</th>
          <th>Họ tên</th>
          <th>Lớp</th>
          <th>Đội</th>
          <th>Trạng thái</th>
          <th>Thời gian</th>
          <th>Ban Tổ Chức</th>
        </tr>
      </thead>
      <tbody id="table"></tbody>
    </table>
  </div> 
</div>

<!-- MODAL HISTORY -->
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
<?php include __DIR__ . '/../config/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ===== TRẠNG THÁI – CHUẨN CHECK_IN / CHECK_OUT ===== */
function getStatus(type){
  if(type === 'CHECK_IN')  return {text:'Đã check-in',  cls:'badge-in'};
  if(type === 'CHECK_OUT') return {text:'Đã check-out', cls:'badge-out'};
  return {text:'Chưa tham gia', cls:'badge-none'};
}

let isSearching = false;

function loadAttendance(){
  if(isSearching) return;

  fetch('../api/get_attendance_report.php')
    .then(r=>r.json())
    .then(res=>{
      if(!res.success || !Array.isArray(res.data)) return;

      const cards=document.getElementById('cards');
      const table=document.getElementById('table');
      cards.innerHTML=''; table.innerHTML='';

      res.data.forEach(row=>{
        const status=getStatus(row.last_type);
        const time=row.last_scan_time || '';
        const btc=row.scanned_by || '';
        const team = row.team_name || '--';

        const history=row.history
          ? row.history.split(';;').map(h=>{
              const [type,time,pin,btc]=h.split('|');
              return {type,time,pin,btc};
            })
          : [];

        /* MOBILE */
        const card=document.createElement('div');
        card.className='card-att att-card';
        card.dataset.name=row.full_name.toLowerCase();
        card.dataset.class=row.class.toLowerCase();
        card.dataset.code=row.student_code.toLowerCase();
        card.innerHTML=`
          <div class="name">${row.full_name}</div>
          <div class="meta">
            ${row.student_code} • ${row.class} • <b>${team}</b>
          </div>
          <span class="badge ${status.cls} mt-2">${status.text}</span>
          <div class="meta mt-1">⏰ ${time || '—'}</div>
        `;
        cards.appendChild(card);

        /* DESKTOP */
        const tooltip=history.length
          ? history.map(h=>`${h.type==='CHECK_IN'?'Check-in':'Check-out'}: ${h.time}`).join('<br>')
          : 'Chưa có lịch sử';

        const tr=document.createElement('tr');
        tr.className='att-row';
        tr.dataset.name=row.full_name.toLowerCase();
        tr.dataset.class=row.class.toLowerCase();
        tr.dataset.code=row.student_code.toLowerCase();
        tr.innerHTML=`
          <td>${row.student_code}</td>
          <td class="fw-semibold">${row.full_name}</td>
          <td>${row.class}</td>
          <td>${team}</td>
          <td>
            <span class="badge-history">
              <span class="badge ${status.cls}"
                    onclick='openHistory(${JSON.stringify(history)})'>
                ${status.text}
              </span>
              <div class="history-tooltip">${tooltip}</div>
            </span>
          </td>
          <td>${time || '—'}</td>
          <td>${btc || '—'}</td>
        `;
        table.appendChild(tr);
      });
    });
}

/* SEARCH */
document.getElementById('searchBox').addEventListener('input',function(){
  const q=this.value.toLowerCase().trim();
  isSearching=q!=='';
  document.querySelectorAll('.att-row,.att-card').forEach(el=>{
    const match=el.dataset.name.includes(q)||el.dataset.class.includes(q)||el.dataset.code.includes(q);
    el.style.display=match?'':'none';
  });
});

function openHistory(history){
  const body=document.getElementById('historyBody');
  body.innerHTML=(!history||history.length===0)
    ? '<i>Chưa có lịch sử</i>'
    : history.map((h,i)=>`
        <div class="mb-2">
          <b>Lần ${i+1}:</b>
          ${h.type==='CHECK_IN'?'Check-in':'Check-out'}
          lúc <b>${h.time}</b><br>
          BTC: <b>${h.btc}</b><br>
          PIN: <b>${h.pin}</b>
        </div><hr>
      `).join('');
  new bootstrap.Modal(document.getElementById('historyModal')).show();
}

loadAttendance();
setInterval(loadAttendance,5000);
</script>
</body>
</html>
