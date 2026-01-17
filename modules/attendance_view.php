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
<style>
    body { background: linear-gradient(135deg, #b3d8fd 0%, #f8e7ff 100%);}
    .title-main { color: #3178c6; font-size:2rem; font-weight: 800; text-align: center; margin: 38px 0 18px 0; letter-spacing: 1px;}
    .subtitle { color: #6f42c1; text-align:center; margin-bottom:22px;font-weight: 500;font-size: 1.07em;}
    .student-count { font-size: 1.13em; color: #3178c6; font-weight: 600; text-align: center; margin-bottom: 18px; letter-spacing: 0.5px;}
    .refresh-indicator { text-align: center; color: #3178c6; margin-bottom: 18px; font-size: 1em; display: none;}
    .no-student-anim { text-align:center; margin-top:40px; font-size:1.14em; color:#f57c00; animation: shake 0.6s infinite alternate;}
    @keyframes shake { from {transform:rotate(-2deg);} to {transform:rotate(2deg);} }
    /* Card grid */
    .student-grid { display: flex; flex-wrap: wrap; gap: 18px; justify-content: center; }
    .student-card {
        background: linear-gradient(90deg,#f8fafd 60%, #e2e7f9 100%);
        border-radius: 14px;
        box-shadow: 0 2px 16px #3178c62a;
        width: 180px;
        min-width: 0;
        margin: 0;
        padding: 18px 10px 12px 10px;
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
        /* Không hiệu ứng xuất hiện */
    }
    .student-card .avatar {
        width: 38px; height: 38px; border-radius:50%; background: #f2f7ff;
        display:flex; align-items:center; justify-content:center; font-size:1.7em; color:#3178c6; margin-bottom:6px;
        border:2px solid #b3d8fd;
        box-shadow:0 2px 7px #b3d8fd33;
    }
    .student-card .name {
        width: 100%;
        text-align: center;
        font-weight:600;
        color:#3178c6;
        font-size:1.09em;
        white-space:nowrap;
        text-overflow:ellipsis;
        overflow:hidden;
        margin-bottom:2px;
    }
    .student-card .rank-badge {
        position: absolute; left: -8px; top: -8px;
        background: linear-gradient(135deg,#ffecd2 30%, #fcb69f 100%);
        color: #e05d00; font-weight: bold; font-size: 0.95em;
        border-radius: 50%; width: 27px; height: 27px;
        display:flex;align-items:center;justify-content:center;
        border:2px solid #fff; box-shadow:0 2px 6px #fdc28a80;
    }
    /* Responsive: 4-3-2-1 card/grid */
    @media (max-width: 1100px) { .student-card { width: 30vw; max-width:200px; } }
    @media (max-width: 800px)  { .student-card { width: 44vw; max-width: 200px;} }
    @media (max-width: 600px)  { .student-card { width: 98vw; max-width: 97vw; padding: 10px 3vw;} }
    @media (max-width: 400px)  { .student-card { font-size: 0.98em; } }
</style>
<div class="container">
    <div class="title-main"><?= htmlspecialchars($event['title']) ?></div>
    <?php if ($event['event_date']): ?>
        <div class="subtitle">
            <i class="bi bi-calendar-event"></i> <?= date('d/m/Y H:i', strtotime($event['event_date'])) ?>
        </div>
    <?php endif; ?>
    <div class="student-count" id="student-count"></div>
    <div class="refresh-indicator" id="refresh-indicator">
        <i class="bi bi-arrow-clockwise spinner-border spinner-border-sm"></i> Đang cập nhật...
    </div>
    <div id="student-list" class="student-grid"></div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
    function renderStudents(data) {
        const list = document.getElementById('student-list');
        const countDiv = document.getElementById('student-count');
        list.innerHTML = '';
        countDiv.innerHTML = '';
        if(data.length === 0) {
            list.innerHTML = `<div class="no-student-anim"><i class="bi bi-emoji-frown text-warning"></i> Chưa có học sinh nào được điểm danh cho buổi này.</div>`;
            return;
        }
        // Hiện tổng số
        countDiv.innerHTML = `<i class="bi bi-people-fill"></i> Đã điểm danh: <b>${data.length}</b> học sinh`;
        // Top 3 có hiệu ứng ruy băng (nếu muốn bỏ luôn thì xóa phần này)
        data.forEach(function(st,i){
            const card = document.createElement('div');
            card.className = 'student-card';
            let badge = '';
            if(i === 0) badge = `<span class="rank-badge" title="Người điểm danh đầu tiên 🥇">🥇</span>`;
            else if(i === 1) badge = `<span class="rank-badge" title="Người điểm danh thứ hai 🥈">🥈</span>`;
            else if(i === 2) badge = `<span class="rank-badge" title="Người điểm danh thứ ba 🥉">🥉</span>`;
            card.innerHTML = `
                ${badge}
                <div class="avatar">
                    <i class="bi bi-person-circle"></i>
                </div>
                <div class="name" title="${st.full_name}">${st.full_name}</div>
            `;
            list.appendChild(card);
        });
    }

    function getAttendanceList() {
        document.getElementById('refresh-indicator').style.display = 'block';
        fetch('attendance_view_api.php?event_id=<?= (int)$event_id ?>')
            .then(res=>res.json())
            .then(data=>{
                renderStudents(data);
                document.getElementById('refresh-indicator').style.display = 'none';
            })
            .catch(()=>{
                document.getElementById('student-list').innerHTML = '<div class="alert alert-danger text-center mt-4">Không thể tải dữ liệu điểm danh.</div>';
                document.getElementById('refresh-indicator').style.display = 'none';
            });
    }

    // Lần đầu
    getAttendanceList();
    setInterval(getAttendanceList, 5000);// Tự động cập nhật mỗi 2 giây
</script>
<?php include '../includes/footer.php'; ?>