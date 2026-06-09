<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/session.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','club_leader','staff'])) {
    die('Không có quyền');
}

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['attendance_session_id'])) {
    // Thử tự động phát hiện phiên đang hoạt động từ DB
    $activeStmt = $pdo->query("SELECT id, type, pin_code FROM attendance_sessions WHERE is_active = 1 ORDER BY start_time DESC LIMIT 1");
    $activeSession = $activeStmt->fetch(PDO::FETCH_ASSOC);
    if ($activeSession) {
        $_SESSION['attendance_session_id'] = $activeSession['id'];
        $_SESSION['attendance_type']       = $activeSession['type'];
        $_SESSION['scanner_pin']           = $activeSession['pin_code'];
    } else {
        die('Chưa mở phiên điểm danh');
    }
}

$pageTitle = "Kiểm tra điểm danh trại sinh";
$full_name = $_SESSION['full_name'] ?? '';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="ml-0 lg:ml-64 pt-4 min-h-screen bg-slate-50/50 transition-all duration-300 ease-in-out p-4 sm:p-6 lg:p-8">
    <div class="max-w-7xl mx-auto pb-12">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-primary-100 text-primary-600 flex items-center justify-center shadow-sm">
                    <i class="bi bi-list-check text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">DANH SÁCH ĐIỂM DANH</h2>
                    <p class="text-sm font-medium text-slate-500 mt-1">Giám sát quá trình check-in/out theo thời gian thực</p>
                </div>
            </div>
            
            <div class="relative max-w-sm w-full">
                <i class="bi bi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" id="searchBox" placeholder="Tìm theo tên, mã hoặc lớp..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 shadow-sm transition-all">
            </div>
        </div>

        <div id="searchResult" class="mb-4"></div>

        <!-- Mobile Cards -->
        <div id="cards" class="grid grid-cols-1 gap-4 lg:hidden mb-6"></div>

        <!-- Desktop Table -->
        <div class="hidden lg:block bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase text-xs tracking-wider">
                        <tr>
                            <th class="px-5 py-4">Mã</th>
                            <th class="px-5 py-4">Họ tên</th>
                            <th class="px-5 py-4">Lớp</th>
                            <th class="px-5 py-4">Trạng thái</th>
                            <th class="px-5 py-4">Thời gian</th>
                            <th class="px-5 py-4">Ban Tổ Chức</th>
                        </tr>
                    </thead>
                    <tbody id="table" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- History Modal (Alpine.js) -->
<div x-data="{ open: false, historyData: '' }" 
     @open-history.window="open = true; historyData = $event.detail"
     x-show="open" 
     class="fixed inset-0 z-[2000] overflow-y-auto" 
     aria-labelledby="modal-title" 
     role="dialog" 
     aria-modal="true"
     x-cloak>
    
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div x-show="open" 
             x-transition:enter="ease-out duration-300" 
             x-transition:enter-start="opacity-0" 
             x-transition:enter-end="opacity-100" 
             x-transition:leave="ease-in duration-200" 
             x-transition:leave-start="opacity-100" 
             x-transition:leave-end="opacity-0" 
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" 
             @click="open = false"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div x-show="open" 
             x-transition:enter="ease-out duration-300" 
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
             x-transition:leave="ease-in duration-200" 
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
             class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full">
            
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 border-b border-slate-100 flex justify-between items-center">
                <h3 class="text-lg leading-6 font-bold text-slate-800" id="modal-title">
                    Lịch sử điểm danh
                </h3>
                <button @click="open = false" class="text-slate-400 hover:text-slate-500 focus:outline-none">
                    <i class="bi bi-x-lg text-lg"></i>
                </button>
            </div>
            
            <div class="px-4 py-5 sm:p-6" x-html="historyData">
                <!-- Nội dung lịch sử sẽ được load vào đây qua x-html -->
            </div>
            
            <div class="bg-slate-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-slate-100">
                <button @click="open = false" type="button" class="mt-3 w-full inline-flex justify-center rounded-xl border border-slate-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-slate-700 hover:bg-slate-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-colors">
                    Đóng
                </button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<style>
/* CSS cho tooltip lịch sử */
.group:hover .history-tooltip {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}
.history-tooltip {
    position: absolute;
    bottom: 125%;
    left: 50%;
    transform: translate(-50%, 5px);
    background: #1e293b;
    color: #fff;
    padding: 10px 14px;
    border-radius: 10px;
    font-size: 12px;
    white-space: nowrap;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s ease;
    z-index: 50;
    pointer-events: none;
}
.history-tooltip::after {
    content: "";
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border-width: 6px;
    border-style: solid;
    border-color: #1e293b transparent transparent transparent;
}
</style>

<script>
const TYPE_LABEL = {
    CHECK_IN: 'Đã Check in',
    CHECK_OUT: 'Đã Check out'
};

function formatType(type){
    return TYPE_LABEL[type] || type;
}

let isSearching = false;

function openHistory(history) {
    let historyHtml = '';
    if(!history || history.length === 0){
        historyHtml = '<div class="text-center text-slate-500 italic py-4">Chưa có lịch sử</div>';
    } else {
        historyHtml = history.map((h, i) => `
            <div class="mb-4 last:mb-0">
                <div class="font-bold text-slate-700 mb-1">Lần ${i+1}: <span class="${h.type === 'CHECK_IN' ? 'text-emerald-600' : 'text-red-600'}">${h.type === 'CHECK_IN' ? 'Check-in' : 'Check-out'}</span></div>
                <div class="text-sm text-slate-600 ml-2 space-y-1 border-l-2 border-slate-200 pl-3">
                    <div><i class="bi bi-clock mr-1 text-slate-400"></i> Lúc: <b>${h.time}</b></div>
                    <div><i class="bi bi-person mr-1 text-slate-400"></i> Bởi: <b>${h.btc}</b></div>
                    <div><i class="bi bi-key mr-1 text-slate-400"></i> Mã PIN: <span class="font-mono bg-slate-100 px-1.5 py-0.5 rounded text-xs font-bold">${h.pin}</span></div>
                </div>
            </div>
            ${i < history.length - 1 ? '<hr class="border-slate-100 my-4">' : ''}
        `).join('');
    }
    window.dispatchEvent(new CustomEvent('open-history', { detail: historyHtml }));
}

// Make openHistory available globally for Alpine/onclick
window.openHistory = openHistory;

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

            const hoverText = history.length
            ? history.map(h =>
                `${h.type === 'CHECK_IN' ? 'Check-in' : 'Check-out'}: ${h.time}`
                ).join('<br>')
            : 'Chưa có lịch sử';

            /* ===== MOBILE CARDS ===== */
            const card = document.createElement('div');
            card.className = 'att-card bg-white rounded-xl p-4 shadow-sm border-l-4 ' + (currentType === 'CHECK_IN' ? 'border-emerald-500' : 'border-red-500');
            card.dataset.name  = row.full_name.toLowerCase();
            card.dataset.class = row.class.toLowerCase();
            card.dataset.code  = row.student_code.toLowerCase();

            card.innerHTML = `
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <div class="font-bold text-slate-800">${row.full_name}</div>
                        <div class="text-xs text-slate-500 font-mono mt-0.5">${row.student_code} • ${row.class}</div>
                    </div>
                    <span class="inline-flex items-center px-2 py-1 rounded text-[10px] font-bold ${currentType === 'CHECK_IN' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'}">
                        ${formatType(currentType)}
                    </span>
                </div>
                <div class="flex justify-between items-center mt-3 pt-3 border-t border-slate-50">
                    <div class="text-xs text-slate-500"><i class="bi bi-clock mr-1"></i> ${currentTime}</div>
                    <button onclick='openHistory(${JSON.stringify(history)})' class="text-primary-600 hover:text-primary-700 text-xs font-semibold">Lịch sử</button>
                </div>
            `;
            cards.appendChild(card);

            /* ===== DESKTOP TABLE ===== */
            const tr = document.createElement('tr');
            tr.className = 'att-row hover:bg-slate-50/80 transition-colors';
            tr.dataset.name  = row.full_name.toLowerCase();
            tr.dataset.class = row.class.toLowerCase();
            tr.dataset.code  = row.student_code.toLowerCase();

            tr.innerHTML = `
                <td class="px-5 py-3.5"><span class="font-mono text-xs font-bold text-slate-500 bg-slate-100 px-2 py-1 rounded">${row.student_code}</span></td>
                <td class="px-5 py-3.5 font-bold text-slate-800">${row.full_name}</td>
                <td class="px-5 py-3.5 text-slate-600">${row.class}</td>
                <td class="px-5 py-3.5">
                    <div class="relative group inline-block">
                        <span class="cursor-pointer inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold ${currentType==='CHECK_IN' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'}"
                            onclick='openHistory(${JSON.stringify(history).replace(/'/g, "&#39;")})'>
                            ${formatType(currentType)}
                        </span>
                        <div class="history-tooltip">${hoverText}</div>
                    </div>
                </td>
                <td class="px-5 py-3.5 text-slate-600 text-xs font-medium"><i class="bi bi-clock mr-1 text-slate-400"></i> ${row.scan_time}</td>
                <td class="px-5 py-3.5 text-slate-600 text-xs">${row.scanned_by}</td>
            `;
            table.appendChild(tr);

        });
    });
}

// SEARCH
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

loadAttendance();
setInterval(loadAttendance, 5000);
</script>
