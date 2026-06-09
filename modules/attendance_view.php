<?php
require_once __DIR__ . '/../includes/db.php';

$event_id = $_GET['event_id'] ?? '';
if (!$event_id) {
    echo "Thiếu mã buổi/sự kiện!";
    exit();
}
$stmt = $pdo->prepare("SELECT title, event_date FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();
if (!$event) {
    echo "Không tìm thấy buổi/sự kiện!";
    exit();
}

$pageTitle = "Danh sách điểm danh - " . htmlspecialchars($event['title']);
$full_name = $_SESSION['full_name'] ?? '';
include '../includes/header.php';
?>
<?php
if (isset($_SESSION['user_id'])) {
    include '../includes/sidebar.php';
}
?>
<main class="<?= isset($_SESSION['user_id']) ? 'ml-0 lg:ml-64' : '' ?> pt-4 min-h-screen bg-slate-50/50 transition-all duration-300 ease-in-out p-4 sm:p-6 lg:p-8">
    <div class="max-w-7xl mx-auto pb-12">
        <!-- Back Link -->
        <div class="mb-6 flex justify-between items-center">
            <a href="attendance.php" class="text-slate-500 hover:text-primary-600 transition-colors flex items-center gap-1.5 text-sm font-medium bg-white px-3 py-1.5 rounded-full border border-slate-200 shadow-sm hover:shadow">
                <i class="bi bi-arrow-left"></i> Sự kiện khác
            </a>
            
            <?php if (isset($_SESSION['user_id'])): ?>
            <a href="attendance_event.php?event_id=<?= $event_id ?>" class="text-slate-500 hover:text-primary-600 transition-colors flex items-center gap-1.5 text-sm font-medium bg-white px-3 py-1.5 rounded-full border border-slate-200 shadow-sm hover:shadow">
                <i class="bi bi-pencil-square"></i> Quản lý
            </a>
            <?php endif; ?>
        </div>
        
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold text-slate-800 tracking-tight mb-3">
                <?= htmlspecialchars($event['title']) ?>
            </h1>
            
            <?php if ($event['event_date']): ?>
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-slate-100 text-slate-600 text-sm font-medium mb-4 shadow-sm border border-slate-200">
                <i class="bi bi-calendar-event text-primary-600"></i>
                <?= date('d/m/Y H:i', strtotime($event['event_date'])) ?>
            </div>
            <?php endif; ?>
            
            <div class="text-lg text-primary-600 font-bold tracking-wide" id="student-count"></div>
            
            <div id="refresh-indicator" class="hidden text-sm text-primary-500 font-medium items-center justify-center gap-2 mt-2">
                <i class="bi bi-arrow-clockwise animate-spin"></i> Đang cập nhật...
            </div>
        </div>

        <!-- Student Grid -->
        <div id="student-list" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 sm:gap-6">
            <!-- Data will be populated here by JavaScript -->
        </div>
    </div>
</main>

<script>
    function renderStudents(data) {
        const list = document.getElementById('student-list');
        const countDiv = document.getElementById('student-count');
        list.innerHTML = '';
        countDiv.innerHTML = '';
        
        if(data.length === 0) {
            list.classList.remove('grid', 'grid-cols-2', 'sm:grid-cols-3', 'md:grid-cols-4', 'lg:grid-cols-5', 'xl:grid-cols-6');
            list.innerHTML = `
                <div class="w-full text-center py-12 px-4 bg-white rounded-2xl shadow-sm border border-slate-200 mt-8">
                    <div class="w-16 h-16 rounded-full bg-amber-50 text-amber-500 flex items-center justify-center mx-auto mb-4 animate-[bounce_2s_infinite]">
                        <i class="bi bi-emoji-frown text-3xl"></i>
                    </div>
                    <p class="text-lg font-medium text-slate-600">Chưa có ai điểm danh cho sự kiện này.</p>
                </div>
            `;
            return;
        }
        
        list.classList.add('grid', 'grid-cols-2', 'sm:grid-cols-3', 'md:grid-cols-4', 'lg:grid-cols-5', 'xl:grid-cols-6');
        
        // Update count
        countDiv.innerHTML = `
            <div class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-primary-50 text-primary-700 border border-primary-100 shadow-sm">
                <i class="bi bi-people-fill text-lg"></i>
                Đã điểm danh: <span class="text-xl font-extrabold mx-1">${data.length}</span> người
            </div>
        `;
        
        // Render cards
        data.forEach(function(st, i) {
            const card = document.createElement('div');
            card.className = 'bg-white rounded-2xl p-4 flex flex-col items-center relative transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)] shadow-[0_4px_20px_rgb(0,0,0,0.03)] border border-slate-100 group';
            
            let badge = '';
            if (i === 0) {
                badge = `<div class="absolute -top-3 -left-3 w-8 h-8 rounded-full bg-gradient-to-br from-amber-200 to-amber-400 border-2 border-white shadow-md flex items-center justify-center text-sm z-10 animate-bounce" title="Điểm danh đầu tiên">🥇</div>`;
            } else if (i === 1) {
                badge = `<div class="absolute -top-3 -left-3 w-8 h-8 rounded-full bg-gradient-to-br from-slate-200 to-slate-400 border-2 border-white shadow-md flex items-center justify-center text-sm z-10" title="Điểm danh thứ hai">🥈</div>`;
            } else if (i === 2) {
                badge = `<div class="absolute -top-3 -left-3 w-8 h-8 rounded-full bg-gradient-to-br from-orange-300 to-orange-500 border-2 border-white shadow-md flex items-center justify-center text-sm z-10" title="Điểm danh thứ ba">🥉</div>`;
            }
            
            card.innerHTML = `
                ${badge}
                <div class="w-14 h-14 rounded-full bg-primary-50 text-primary-500 flex items-center justify-center text-2xl mb-3 shadow-inner group-hover:bg-primary-100 group-hover:text-primary-600 transition-colors border border-primary-100">
                    <i class="bi bi-person-circle"></i>
                </div>
                <div class="w-full text-center">
                    <h4 class="font-bold text-slate-800 text-sm truncate px-1" title="${st.full_name}">${st.full_name}</h4>
                    ${st.class ? `<p class="text-xs font-medium text-slate-500 mt-1 bg-slate-100 rounded px-2 py-0.5 inline-block">${st.class}</p>` : ''}
                </div>
            `;
            list.appendChild(card);
        });
    }

    function getAttendanceList() {
        const indicator = document.getElementById('refresh-indicator');
        indicator.classList.remove('hidden');
        indicator.classList.add('flex');
        
        fetch('attendance_view_api.php?event_id=<?= (int)$event_id ?>')
            .then(res => res.json())
            .then(data => {
                renderStudents(data);
                indicator.classList.add('hidden');
                indicator.classList.remove('flex');
            })
            .catch(() => {
                const list = document.getElementById('student-list');
                list.classList.remove('grid', 'grid-cols-2', 'sm:grid-cols-3', 'md:grid-cols-4', 'lg:grid-cols-5', 'xl:grid-cols-6');
                list.innerHTML = `
                    <div class="w-full p-4 bg-red-50 text-red-600 border border-red-200 rounded-xl text-center shadow-sm">
                        <i class="bi bi-exclamation-triangle-fill mr-2"></i> Không thể tải dữ liệu điểm danh.
                    </div>
                `;
                indicator.classList.add('hidden');
                indicator.classList.remove('flex');
            });
    }

    // Initial load
    getAttendanceList();
    
    // Refresh every 5 seconds
    setInterval(getAttendanceList, 5000);
</script>

<?php include '../includes/footer.php'; ?>