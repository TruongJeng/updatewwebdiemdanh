<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../config/db.php'; // $pdo
// 1. Kiểm tra quyền truy cập
if (!in_array($_SESSION['role'], ['admin','club_leader'])) {
    die('Không có quyền');
}

$stmt = $pdo->prepare("
    SELECT c.student_code, c.full_name, c.class
    FROM campers c
    WHERE c.is_active = 1
    ORDER BY c.full_name
");
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✏️ Đổi tên đội
if (isset($_POST['rename_team'], $_POST['team_id'], $_POST['team_name'])) {
    $name = trim($_POST['team_name']);
    if ($name !== '') {
        $stmt = $pdo->prepare("UPDATE team_campers SET name = ? WHERE id = ?");
        $stmt->execute([$name, $_POST['team_id']]);
        $_SESSION['success'] = "Đã đổi tên đội";
    }
    header("Location: chiadoi.php");
    exit;
}
// 3. Xử lý xóa thành viên khỏi đội
if (isset($_POST['remove_member']) && isset($_POST['team_id']) && isset($_POST['student_code'])) {
    $pdo->prepare("
    DELETE FROM team_cam_member 
    WHERE team_id=? AND student_code=?")
    ->execute([
        $_POST['team_id'], $_POST['student_code']
    ]);
    header("Location: chiadoi.php");
    exit;
}

// 4. Xử lý thêm thành viên vào đội
if (isset($_POST['add_member']) && isset($_POST['team_id']) && isset($_POST['student_code'])) {
    $pdo->prepare("
    INSERT IGNORE INTO team_cam_member (team_id, student_code) VALUES (?, ?)")
    ->execute([
        $_POST['team_id'], $_POST['student_code']
    ]);
    header("Location: chiadoi.php");
    exit;
}

// 5. Xử lý xóa TẤT CẢ đội
if (isset($_POST['delete_all_teams'])) {
    $pdo->exec("DELETE FROM team_cam_member");
    $pdo->exec("DELETE FROM team_campers");
    $_SESSION['success'] = "Đã xóa tất cả đội và thành viên.";
    header("Location: chiadoi.php");
    exit;
}


// 6. Xử lý chia đội random
if (isset($_POST['random_team_do'], $_POST['num_teams'])) {
    $num_teams = max(1, intval($_POST['num_teams']));
    
    // ĐẢM BẢO PDO BÁO LỖI DƯỚI DẠNG EXCEPTION
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (empty($students) || count($students) < $num_teams) {
        $error = "Số lượng học sinh không đủ!";
    } else {
        try {
            // Bước 1: Khởi tạo dữ liệu trước khi mở Transaction (Giảm tải cho DB)
            $by_grade = ['12' => [], '11' => [], '10' => [], 'CHS' => []];
            $student_map = []; 
            foreach ($students as $s) {
                $student_map[$s['student_code']] = $s;
                $class = strtoupper(trim($s['class']));
                if (preg_match('/^12/', $class)) $by_grade['12'][] = $s['student_code'];
                elseif (preg_match('/^11/', $class)) $by_grade['11'][] = $s['student_code'];
                elseif (preg_match('/^10/', $class)) $by_grade['10'][] = $s['student_code'];
                else $by_grade['CHS'][] = $s['student_code'];
            }

            foreach ($by_grade as &$list) { shuffle($list); }
            unset($list);
            $all_students_sorted = array_merge($by_grade['12'], $by_grade['11'], $by_grade['10'], $by_grade['CHS']);

            // Bước 2: Bắt đầu làm việc với Database
            $pdo->beginTransaction(); // <--- Lệnh này cực kỳ quan trọng

            // Xóa dữ liệu cũ
            $pdo->exec("DELETE FROM team_cam_member");
            $pdo->exec("DELETE FROM team_campers");
            
            // Chia đội tuần tự (Rải bài)
            $groups = array_fill(0, $num_teams, []);
            foreach ($all_students_sorted as $index => $scode) {
                $groups[$index % $num_teams][] = $scode;
            }

            $stmtTeam = $pdo->prepare("INSERT INTO team_campers (name) VALUES (?)");
            $stmtMember = $pdo->prepare("INSERT INTO team_cam_member (team_id, student_code) VALUES (?, ?)");

            $teams_result = [];
            foreach ($groups as $i => $group) {
                $team_name = "Đội " . ($i + 1);
                $stmtTeam->execute([$team_name]);
                $team_id = $pdo->lastInsertId();

                $team_members_info = [];
                foreach ($group as $scode) {
                    $stmtMember->execute([$team_id, $scode]);
                    $team_members_info[] = [
                        'student_code' => $scode,
                        'full_name'    => $student_map[$scode]['full_name'],
                        'class'        => $student_map[$scode]['class']
                    ];
                }
                $teams_result[] = ['name' => $team_name, 'team_id' => $team_id, 'members' => $team_members_info];
            }

            $pdo->commit();
            
            $_SESSION['random_teams_effect'] = json_encode($teams_result);
            header("Location: chiadoi.php?show_effect=1");
            exit;

        } catch (PDOException $e) {
            // Chỉ rollback nếu transaction thực sự tồn tại
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // HIỆN LỖI CHI TIẾT ĐỂ FIX
            die("Lỗi Database: " . $e->getMessage()); 
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            die("Lỗi Code: " . $e->getMessage());
        }
    }
}
// 7. Lấy danh sách các đội và thành viên đội cho sự kiện đã chọn (sort theo lớp)
$all_teams = [];
if (empty($_GET['show_effect'])) {
    $stmt = $pdo->prepare("
        SELECT id, name
        FROM team_campers
        ORDER BY id ASC
    ");
    $stmt->execute();
    $all_teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($all_teams as &$team) {
        $mstmt = $pdo->prepare("
            SELECT s.student_code, s.full_name, s.class
            FROM team_cam_member tm
            JOIN campers s ON tm.student_code = s.student_code
            WHERE tm.team_id = ?
        ");
        $mstmt->execute([$team['id']]);
        $members = $mstmt->fetchAll(PDO::FETCH_ASSOC);

        // Sort theo lớp: 12 -> 11 -> 10 -> khác
        usort($members, function($a, $b) {
            $getSort = function($class) {
                if (preg_match('/^12/i', $class)) return 1;
                if (preg_match('/^11/i', $class)) return 2;
                if (preg_match('/^10/i', $class)) return 3;
                return 4;
            };
            $s1 = $getSort($a['class']);
            $s2 = $getSort($b['class']);
            if ($s1 !== $s2) return $s1 - $s2;
            return strcmp($b['class'], $a['class']);
        });
        $team['members'] = $members;
    }
    unset($team);
}

// 8. Lấy học sinh chưa thuộc đội nào (chuẩn SQL)
$stmt = $pdo->prepare("
    SELECT c.student_code, c.full_name, c.class
    FROM campers c
    LEFT JOIN team_cam_member t ON c.student_code = t.student_code
    WHERE c.is_active = 1
      AND t.student_code IS NULL
    ORDER BY c.full_name
");
$stmt->execute();
$students_no_team = $stmt->fetchAll(PDO::FETCH_ASSOC);


// 9. Màu pastel cho đội
$team_colors = ["#a5d8ff","#b2f2bb","#ffd8a8","#ffadad","#eebefa","#d0ebff","#b8f2e6","#ffe066","#ffa8a8","#c0eb75"];

// 10. Thông báo flash
$success = $_SESSION['success'] ?? null;
unset($_SESSION['success']);


$pageTitle = "Chia đội trại sinh";
$full_name = $_SESSION['full_name'] ?? '';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="ml-0 lg:ml-64 pt-4 min-h-screen bg-slate-50/50 transition-all duration-300 ease-in-out p-4 sm:p-6 lg:p-8" x-data="{ openTeam: null }">
    <div class="max-w-7xl mx-auto pb-12">
        <!-- Header -->
        <div class="bg-white rounded-3xl p-8 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 text-center relative overflow-hidden mb-8">
            <div class="absolute -top-24 -right-24 w-48 h-48 bg-primary-500/10 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-24 -left-24 w-48 h-48 bg-pink-500/10 rounded-full blur-3xl"></div>
            
            <div class="relative z-10">
                <h2 class="text-3xl md:text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-primary-600 to-indigo-600 mb-2 tracking-tight">
                    CHIA ĐỘI TRẠI SINH
                </h2>
                <p class="text-slate-500 font-medium max-w-xl mx-auto">Tạo các đội ngẫu nhiên, quản lý thành viên và xuất danh sách một cách dễ dàng và vui nhộn!</p>
            </div>
        </div>

        <?php if (!empty($success)): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-6 shadow-sm flex items-center gap-3 animate-[fadeIn_0.5s_ease-out]">
                <i class="bi bi-check-circle-fill text-xl"></i>
                <span class="font-medium"><?= $success ?></span>
            </div>
        <?php elseif (!empty($error)): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 shadow-sm flex items-center gap-3 animate-[fadeIn_0.5s_ease-out]">
                <i class="bi bi-exclamation-triangle-fill text-xl"></i>
                <span class="font-medium"><?= $error ?></span>
            </div>
        <?php endif; ?>

        <?php if (empty($_GET['show_effect'])): ?>
            <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-8">
                <a href="../../dashboard.php" class="text-slate-500 hover:text-primary-600 font-semibold flex items-center gap-2 transition-colors">
                    <i class="bi bi-arrow-left"></i> Về Trang chủ
                </a>
                
                <form method="post" onsubmit="return confirm('Bạn chắc chắn muốn xóa toàn bộ đội và thành viên?');">
                    <button name="delete_all_teams" type="submit" class="bg-white hover:bg-red-50 text-red-500 hover:text-red-600 border border-red-200 px-4 py-2 rounded-xl font-semibold transition-all shadow-sm flex items-center gap-2 text-sm">
                        <i class="bi bi-trash3-fill"></i> Xóa tất cả đội
                    </button>
                </form>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-8">
                <!-- Danh sách trại sinh -->
                <div class="lg:col-span-7 bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 flex flex-col h-[500px]">
                    <div class="p-5 border-b border-slate-100 bg-slate-50/50">
                        <h5 class="font-bold text-slate-800 flex items-center gap-2">
                            <i class="bi bi-list-ol text-primary-500"></i> Danh sách trại sinh
                        </h5>
                    </div>
                    <div class="overflow-y-auto flex-1 p-0">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead class="bg-slate-50 sticky top-0 border-b border-slate-100 text-slate-500 font-semibold text-xs uppercase tracking-wider shadow-sm z-10">
                                <tr>
                                    <th class="px-5 py-3 w-16 text-center">STT</th>
                                    <th class="px-5 py-3">Họ và tên</th>
                                    <th class="px-5 py-3 w-32">Lớp</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach($students as $k=>$s): ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-5 py-2.5 text-center text-slate-400 font-medium"><?= $k+1 ?></td>
                                    <td class="px-5 py-2.5 font-semibold text-slate-700"><?= htmlspecialchars($s['full_name']) ?></td>
                                    <td class="px-5 py-2.5"><span class="bg-slate-100 text-slate-600 px-2 py-1 rounded text-xs font-bold"><?= htmlspecialchars($s['class']) ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Form Chia Đội -->
                <div class="lg:col-span-5" x-data="{ showForm: false }">
                    <div class="bg-gradient-to-br from-primary-600 to-indigo-600 rounded-2xl p-8 shadow-xl text-white text-center h-full flex flex-col justify-center items-center relative overflow-hidden">
                        <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCI+CjxjaXJjbGUgY3g9IjIiIGN5PSIyIiByPSIyIiBmaWxsPSJyZ2JhKDI1NSwyNTUsMjU1LDAuMSkiLz4KPC9zdmc+')] opacity-50"></div>
                        
                        <div class="relative z-10 w-full">
                            <div class="w-20 h-20 bg-white/20 backdrop-blur-md rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-inner">
                                <i class="bi bi-shuffle text-4xl"></i>
                            </div>
                            <h3 class="text-2xl font-black tracking-tight mb-2">CHIA ĐỘI TỰ ĐỘNG</h3>
                            <p class="text-primary-100 font-medium mb-8 text-sm">Hệ thống sẽ tự động phân bổ đều các thành viên vào các đội.</p>
                            
                            <div x-show="!showForm" x-transition>
                                <button @click="showForm = true" class="bg-white text-primary-600 hover:bg-slate-50 px-8 py-3.5 rounded-xl font-extrabold tracking-wide shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all w-full">
                                    BẮT ĐẦU CHIA ĐỘI
                                </button>
                            </div>

                            <div x-show="showForm" x-transition.duration.300ms class="bg-white/10 backdrop-blur-md border border-white/20 p-5 rounded-xl">
                                <form method="post" class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-bold text-white/90 mb-2">Nhập số đội cần chia:</label>
                                        <input type="number" name="num_teams" min="1" max="<?= count($students) ?>" class="w-full text-center text-2xl font-black text-slate-800 bg-white border-0 rounded-lg py-3 focus:ring-4 focus:ring-white/30 outline-none transition-all shadow-inner" required placeholder="VD: 5">
                                    </div>
                                    <button type="submit" name="random_team_do" class="w-full bg-amber-400 hover:bg-amber-300 text-amber-900 px-6 py-3 rounded-lg font-black tracking-wide shadow-lg transition-colors">
                                        XÁC NHẬN CHIA ĐỘI <i class="bi bi-lightning-fill ml-1"></i>
                                    </button>
                                    <button type="button" @click="showForm = false" class="w-full text-white/70 hover:text-white text-sm font-semibold transition-colors mt-2">
                                        Hủy
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php
        // Hiệu ứng xổ số random đội
        if (isset($_GET['show_effect']) && isset($_SESSION['random_teams_effect'])):
            $teams_data = json_decode($_SESSION['random_teams_effect'], true);
            unset($_SESSION['random_teams_effect']);
        ?>
            <div class="text-center py-12">
                <h4 class="text-4xl font-black text-transparent bg-clip-text bg-gradient-to-r from-primary-500 to-pink-500 tracking-tight mb-12 animate-pulse" id="effect-title">
                    ĐANG CHIA ĐỘI...
                </h4>
                
                <div id="effect-teams" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6"></div>
                
                <div class="mt-12 hidden opacity-0 transition-opacity duration-1000" id="view-teams-real">
                    <a href="chiadoi.php" class="inline-flex items-center gap-3 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white px-8 py-4 rounded-full font-black text-lg tracking-wide shadow-xl hover:shadow-2xl hover:-translate-y-1 transition-all">
                        <i class="bi bi-eye-fill"></i> XEM DANH SÁCH ĐỘI CHÍNH THỨC
                    </a>
                </div>
            </div>
            
            <script>
            let teams = <?=json_encode($teams_data)?>;
            let colors = <?=json_encode($team_colors)?>;
            let container = document.getElementById('effect-teams');
            let viewRealBtn = document.getElementById('view-teams-real');
            let t = 0;
            
            function showNextTeam() {
                if (t >= teams.length) {
                    setTimeout(() => {
                        viewRealBtn.classList.remove('hidden');
                        setTimeout(() => viewRealBtn.classList.remove('opacity-0'), 50);
                    }, 1000);
                    return;
                }
                
                let team = teams[t];
                let color = colors[t % colors.length];
                let member0 = team.members.length ? team.members[0] : null;
                
                let html = `
                    <div class="relative bg-white rounded-3xl shadow-xl overflow-hidden cursor-pointer transform scale-95 opacity-0 transition-all duration-500" 
                         style="background: linear-gradient(135deg, white, ${color}30);" 
                         id="team-card-${t}"
                         onclick="showAllMembers(this, ${t})">
                        <div class="absolute top-0 left-0 w-full h-2" style="background-color: ${color};"></div>
                        <div class="p-6">
                            <h3 class="text-2xl font-black text-slate-800 mb-4">${team.name}</h3>
                            <div class="team-body-${t} min-h-[120px] flex flex-col justify-center">
                                <ul class="space-y-2 text-left mb-4">
                                    ${member0 ? `
                                        <li class="flex justify-between items-center bg-slate-50/50 p-2 rounded-lg border border-slate-100">
                                            <span class="font-bold text-slate-700">${member0.full_name}</span>
                                            <span class="text-xs font-bold text-slate-400 bg-white px-2 py-1 rounded shadow-sm border border-slate-100">${member0.class}</span>
                                        </li>
                                    ` : ''}
                                    ${team.members.slice(1).map(m => `
                                        <li class="hidden member-item flex justify-between items-center bg-slate-50/50 p-2 rounded-lg border border-slate-100">
                                            <span class="font-bold text-slate-700">${m.full_name}</span>
                                            <span class="text-xs font-bold text-slate-400 bg-white px-2 py-1 rounded shadow-sm border border-slate-100">${m.class}</span>
                                        </li>
                                    `).join('')}
                                </ul>
                                <div class="team-hint inline-flex items-center justify-center gap-2 bg-slate-800 text-white px-4 py-2 rounded-full text-sm font-bold mx-auto mt-auto shadow-md animate-bounce">
                                    <i class="bi bi-hand-index-thumb-fill text-amber-300"></i> Mở kết quả
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                let wrapper = document.createElement('div');
                wrapper.innerHTML = html.trim();
                let card = wrapper.firstChild;
                container.appendChild(card);
                
                // Trigger animation
                setTimeout(() => {
                    card.classList.remove('scale-95', 'opacity-0');
                    card.classList.add('scale-100', 'opacity-100');
                }, 50);
                
                t++;
                setTimeout(showNextTeam, 800);
            }

            let title = document.getElementById('effect-title');
            let dots = 0;
            setInterval(() => {
                if(title) {
                    title.innerHTML = "ĐANG CHIA ĐỘI" + '.'.repeat((++dots)%4);
                }
            }, 400);

            function showAllMembers(card, idx) {
                let body = card.querySelector('.team-body-'+idx);
                if(!body) return;
                
                // Shake effect
                card.classList.add('animate-[shake_0.5s_ease-in-out]');
                
                let lis = body.querySelectorAll('.member-item');
                lis.forEach((li, i) => {
                    setTimeout(() => {
                        li.classList.remove('hidden');
                        li.classList.add('animate-[fadeIn_0.3s_ease-out]');
                    }, i * 150);
                });
                
                let hint = body.querySelector('.team-hint');
                if(hint) hint.classList.add('hidden');
                
                card.onclick = null;
                card.classList.remove('cursor-pointer');
            }
            
            showNextTeam();
            </script>
            <style>
                @keyframes shake {
                    0%, 100% { transform: translateX(0); }
                    25% { transform: translateX(-5px) rotate(-1deg); }
                    75% { transform: translateX(5px) rotate(1deg); }
                }
                @keyframes fadeIn {
                    from { opacity: 0; transform: translateY(10px); }
                    to { opacity: 1; transform: translateY(0); }
                }
            </style>
        <?php endif; ?>

        <?php if ($all_teams && empty($_GET['show_effect'])): ?>
            <div class="flex justify-between items-end mb-6">
                <div>
                    <h4 class="text-2xl font-extrabold text-slate-800 tracking-tight">Danh sách các đội</h4>
                    <p class="text-sm font-medium text-slate-500 mt-1">Quản lý thành viên, sửa tên đội và xuất danh sách</p>
                </div>
                <a href="../api/export_teams_excel.php" class="hidden sm:flex items-center gap-2 bg-emerald-500 hover:bg-emerald-600 text-white px-5 py-2.5 rounded-xl font-bold transition-all shadow-sm shadow-emerald-500/20">
                    <i class="bi bi-file-earmark-excel-fill text-lg"></i> Xuất Excel
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php foreach ($all_teams as $idx=>$team): ?>
                <?php $bgColor = $team_colors[$idx%count($team_colors)]; ?>
                
                <div class="bg-white rounded-3xl shadow-sm hover:shadow-xl border border-slate-100 overflow-hidden transition-all duration-300 flex flex-col h-full group" style="box-shadow: 0 10px 40px -10px <?= $bgColor ?>50;">
                    
                    <!-- Team Header -->
                    <div class="p-5 flex justify-between items-center border-b border-white/50 backdrop-blur-sm relative" style="background-color: <?= $bgColor ?>40;">
                        <div class="absolute top-0 left-0 w-full h-1" style="background-color: <?= $bgColor ?>;"></div>
                        
                        <div class="flex-1 min-w-0 pr-4">
                            <h5 class="text-lg font-black text-slate-800 truncate" title="<?= htmlspecialchars($team['name']) ?>">
                                <?= htmlspecialchars($team['name']) ?>
                            </h5>
                            <div class="text-xs font-bold text-slate-600/70 uppercase tracking-wider mt-0.5">
                                <?= count($team['members']) ?> Thành viên
                            </div>
                        </div>
                        
                        <button @click="openTeam = <?= $team['id'] ?>" class="w-10 h-10 rounded-full bg-white shadow-sm flex items-center justify-center text-slate-600 hover:text-primary-600 hover:scale-110 transition-transform">
                            <i class="bi bi-arrows-fullscreen"></i>
                        </button>
                    </div>

                    <!-- Team Members (Preview) -->
                    <div class="p-5 flex-1 flex flex-col">
                        <?php if ($team['members']): ?>
                            <ul class="space-y-2 mb-4 flex-1">
                                <?php foreach (array_slice($team['members'], 0, 5) as $mem): ?>
                                    <li class="flex justify-between items-center group/item">
                                        <div class="flex items-center gap-2 min-w-0 flex-1">
                                            <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 text-xs shrink-0">
                                                <i class="bi bi-person-fill"></i>
                                            </div>
                                            <span class="text-sm font-semibold text-slate-700 truncate"><?= htmlspecialchars($mem['full_name']) ?></span>
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <span class="text-[10px] font-bold text-slate-500 bg-slate-50 border border-slate-100 px-1.5 py-0.5 rounded"><?= htmlspecialchars($mem['class']) ?></span>
                                            
                                            <form method="post" class="inline" onsubmit="return confirm('Xóa thành viên khỏi đội?');">
                                                <input type="hidden" name="team_id" value="<?=$team['id']?>">
                                                <input type="hidden" name="student_code" value="<?=$mem['student_code']?>">
                                                <button name="remove_member" class="opacity-0 group-hover/item:opacity-100 w-5 h-5 rounded flex items-center justify-center bg-red-50 text-red-500 hover:bg-red-500 hover:text-white transition-all">
                                                    <i class="bi bi-x"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                                
                                <?php if(count($team['members']) > 5): ?>
                                    <li class="text-center text-xs font-bold text-slate-400 py-2 border-t border-dashed border-slate-200 mt-2">
                                        + <?= count($team['members']) - 5 ?> thành viên khác
                                    </li>
                                <?php endif; ?>
                            </ul>
                        <?php else: ?>
                            <div class="flex-1 flex flex-col items-center justify-center text-slate-400 py-6">
                                <i class="bi bi-inbox text-3xl mb-2 opacity-50"></i>
                                <span class="text-sm font-medium">Chưa có thành viên</span>
                            </div>
                        <?php endif; ?>

                        <!-- Add Member Form -->
                        <?php if (!empty($students_no_team)): ?>
                            <form method="post" class="mt-auto pt-4 border-t border-slate-100">
                                <input type="hidden" name="team_id" value="<?=$team['id']?>">
                                <div class="flex gap-2">
                                    <select name="student_code" class="flex-1 bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full px-2 py-1.5 outline-none" required>
                                        <option value="">Thêm TV...</option>
                                        <?php foreach($students_no_team as $s): ?>
                                            <option value="<?=$s['student_code']?>"><?=htmlspecialchars($s['full_name'].' ('.$s['class'].')')?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button name="add_member" class="bg-primary-50 hover:bg-primary-500 text-primary-600 hover:text-white border border-primary-200 hover:border-primary-500 rounded-lg px-3 py-1.5 transition-colors">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Alpine.js Modal for Team Details -->
                <div x-show="openTeam === <?= $team['id'] ?>" 
                     x-cloak
                     class="fixed inset-0 z-[2000] overflow-y-auto" 
                     aria-labelledby="modal-title" 
                     role="dialog" 
                     aria-modal="true">
                    
                    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                        <div x-show="openTeam === <?= $team['id'] ?>" 
                             x-transition.opacity 
                             class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" 
                             @click="openTeam = null"></div>

                        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                        <div x-show="openTeam === <?= $team['id'] ?>" 
                             x-transition:enter="ease-out duration-300" 
                             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
                             x-transition:leave="ease-in duration-200" 
                             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
                             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                             class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl w-full border border-slate-100">
                            
                            <div class="px-6 py-4 flex justify-between items-center relative" style="background-color: <?= $bgColor ?>40;">
                                <div class="absolute top-0 left-0 w-full h-1" style="background-color: <?= $bgColor ?>;"></div>
                                
                                <form method="post" class="flex-1 flex gap-2 mr-4">
                                    <input type="hidden" name="team_id" value="<?=$team['id']?>">
                                    <input type="text" name="team_name" class="flex-1 bg-white/50 border border-white/50 focus:bg-white text-lg font-black text-slate-800 px-3 py-1.5 rounded-lg outline-none focus:ring-2 focus:ring-primary-500/20 transition-all" value="<?=htmlspecialchars($team['name'])?>" required>
                                    <button name="rename_team" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-1.5 rounded-lg font-bold transition-colors shadow-sm">
                                        Lưu
                                    </button>
                                </form>

                                <button @click="openTeam = null" class="w-8 h-8 flex items-center justify-center rounded-full bg-black/5 text-slate-600 hover:bg-black/10 transition-colors shrink-0">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                            
                            <div class="px-6 py-6" id="team-image-<?=$team['id']?>">
                                <!-- Printable Area -->
                                <div class="bg-white p-4 rounded-2xl border border-slate-100">
                                    <div class="text-center mb-6">
                                        <h3 class="text-3xl font-black text-slate-800" style="color: <?= $bgColor === '#ffffff' ? '#1e293b' : 'inherit' ?>; text-shadow: 0 2px 10px <?= $bgColor ?>80;"><?=htmlspecialchars($team['name'])?></h3>
                                        <p class="text-sm font-bold text-slate-400 uppercase tracking-widest mt-2">Danh sách thành viên chính thức</p>
                                    </div>

                                    <?php if ($team['members']): ?>
                                        <div class="overflow-hidden rounded-xl border border-slate-200">
                                            <table class="w-full text-left text-sm whitespace-nowrap">
                                                <thead class="bg-slate-50 border-b border-slate-200">
                                                    <tr>
                                                        <th class="px-4 py-3 font-bold text-slate-500 uppercase text-xs w-16 text-center">STT</th>
                                                        <th class="px-4 py-3 font-bold text-slate-500 uppercase text-xs">Họ và tên</th>
                                                        <th class="px-4 py-3 font-bold text-slate-500 uppercase text-xs w-32">Lớp</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100">
                                                    <?php foreach ($team['members'] as $n=>$mem): ?>
                                                        <tr>
                                                            <td class="px-4 py-2.5 text-center font-semibold text-slate-400"><?= $n+1 ?></td>
                                                            <td class="px-4 py-2.5 font-bold text-slate-700 text-base"><?= htmlspecialchars($mem['full_name']) ?></td>
                                                            <td class="px-4 py-2.5"><span class="font-bold text-slate-500"><?= htmlspecialchars($mem['class']) ?></span></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-10 text-slate-400 font-medium bg-slate-50 rounded-xl border border-dashed border-slate-200">
                                            Chưa có thành viên nào
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-3 rounded-b-3xl">
                                <button type="button" @click="openTeam = null" class="px-5 py-2.5 rounded-xl font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 transition-colors shadow-sm">
                                    Đóng
                                </button>
                                <button type="button" class="px-5 py-2.5 rounded-xl font-bold text-white bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 transition-all shadow-md shadow-blue-500/20 flex items-center gap-2"
                                        onclick="downloadTeamImage('team-image-<?=$team['id']?>', '<?=htmlspecialchars(addslashes($team['name']))?>', 'ChiaDoiHoatDong')">
                                    <i class="bi bi-images"></i> Lưu ảnh danh sách
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>

            <!-- Mobile Export Button -->
            <div class="mt-6 sm:hidden">
                <a href="../api/export_teams_excel.php" class="flex items-center justify-center gap-2 bg-emerald-500 hover:bg-emerald-600 text-white px-5 py-3 rounded-xl font-bold transition-all shadow-sm w-full">
                    <i class="bi bi-file-earmark-excel-fill text-lg"></i> Xuất file Excel
                </a>
            </div>

            <!-- Include html2canvas for image download -->
            <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
            <script>
            function downloadTeamImage(elementId, teamName, eventName) {
                const node = document.getElementById(elementId);
                if (!node) return;
                
                // Add a small delay to ensure rendering is complete
                setTimeout(() => {
                    html2canvas(node, {
                        backgroundColor: "#ffffff",
                        scale: 2, // High resolution
                        useCORS: true,
                        logging: false
                    }).then(canvas => {
                        let clean = function(str) {
                            return (str||'').replace(/[^\w\d]/g,'_').replace(/_+/g,'_').replace(/^_|_$/g,'');
                        }
                        let fileName = clean(teamName) + "_" + clean(eventName) + ".png";
                        let link = document.createElement('a');
                        link.download = fileName;
                        link.href = canvas.toDataURL("image/png", 1.0);
                        link.click();
                    }).catch(err => {
                        console.error("Error generating image:", err);
                        alert("Có lỗi khi tạo ảnh. Vui lòng thử lại.");
                    });
                }, 100);
            }
            </script>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>