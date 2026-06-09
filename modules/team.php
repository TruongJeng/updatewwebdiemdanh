<?php
require_once __DIR__ . '/../config/session.php';
require_once '../includes/db.php'; // $pdo

// 1. Lấy sự kiện
$events = $pdo->query("SELECT id, title FROM events ORDER BY event_date DESC")->fetchAll(PDO::FETCH_ASSOC);

// Tìm tên hoạt động (event) hiện tại
$eventTitle = '';
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
foreach($events as $ev) if($ev['id']==$event_id) $eventTitle = $ev['title'] ?? '';

// 2. Lấy học sinh đã điểm danh hoạt động
$students = [];
if ($event_id) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.full_name, s.class
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE a.event_id = ?
        ORDER BY s.full_name
    ");
    $stmt->execute([$event_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 3. Xử lý xóa thành viên khỏi đội
if (isset($_POST['remove_member']) && isset($_POST['team_id']) && isset($_POST['student_id'])) {
    $pdo->prepare("DELETE FROM team_members WHERE team_id=? AND student_id=?")->execute([
        $_POST['team_id'], $_POST['student_id']
    ]);
    header("Location: team.php?event_id=$event_id");
    exit;
}

// 4. Xử lý thêm thành viên vào đội
if (isset($_POST['add_member']) && isset($_POST['team_id']) && isset($_POST['student_id'])) {
    $pdo->prepare("INSERT IGNORE INTO team_members (team_id, student_id) VALUES (?, ?)")->execute([
        $_POST['team_id'], $_POST['student_id']
    ]);
    header("Location: team.php?event_id=$event_id");
    exit;
}

// 5. Xử lý xóa TẤT CẢ đội
if (isset($_POST['delete_all_teams']) && $event_id) {
    $teamIds = $pdo->prepare("SELECT id FROM teams WHERE event_id = ?");
    $teamIds->execute([$event_id]);
    $ids = $teamIds->fetchAll(PDO::FETCH_COLUMN);
    if ($ids) {
        $in = str_repeat('?,', count($ids)-1) . '?';
        $pdo->prepare("DELETE FROM team_members WHERE team_id IN ($in)")->execute($ids);
        $pdo->prepare("DELETE FROM teams WHERE id IN ($in)")->execute($ids);
    }
    $_SESSION['success'] = "Đã xóa tất cả đội và thành viên của hoạt động này.";
    header("Location: team.php?event_id=$event_id");
    exit;
}

// 6. Xử lý chia đội random
if (isset($_POST['random_team_do']) && $event_id && isset($_POST['num_teams'])) {
    $num_teams = max(1, intval($_POST['num_teams']));
    if (count($students) < $num_teams) {
        $error = "Số lượng đội lớn hơn số học sinh!";
    } else {
        // Xóa dữ liệu cũ
        $teamIds = $pdo->prepare("SELECT id FROM teams WHERE event_id = ?");
        $teamIds->execute([$event_id]);
        $ids = $teamIds->fetchAll(PDO::FETCH_COLUMN);
        if ($ids) {
            $in = str_repeat('?,', count($ids)-1) . '?';
            $pdo->prepare("DELETE FROM team_members WHERE team_id IN ($in)")->execute($ids);
            $pdo->prepare("DELETE FROM teams WHERE id IN ($in)")->execute($ids);
        }

        // Tạo bảng nếu chưa có
        $pdo->exec("CREATE TABLE IF NOT EXISTS teams (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            event_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS team_members (
            id INT AUTO_INCREMENT PRIMARY KEY,
            team_id INT NOT NULL,
            student_id INT NOT NULL,
            joined_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $student_ids = array_column($students, 'id');
        shuffle($student_ids);
        $groups = array_chunk($student_ids, ceil(count($student_ids) / $num_teams));
        $teams_result = [];
        foreach ($groups as $i => $group) {
            $team_name = "Đội " . ($i+1);
            $pdo->prepare("INSERT INTO teams (name, event_id) VALUES (?, ?)")->execute([$team_name, $event_id]);
            $team_id = $pdo->lastInsertId();
            $team_members = [];
            foreach ($group as $sid) {
                $pdo->prepare("INSERT INTO team_members (team_id, student_id) VALUES (?, ?)")->execute([$team_id, $sid]);
                foreach ($students as $s) if ($s['id'] == $sid) $team_members[] = ['id'=>$sid, 'full_name'=>$s['full_name'], 'class'=>$s['class']];
            }
            $teams_result[] = ['name'=>$team_name, 'members'=>$team_members, 'team_id'=>$team_id];
        }
        $_SESSION['random_teams_effect'] = json_encode($teams_result);
        header("Location: team.php?event_id=$event_id&show_effect=1");
        exit;
    }
}

// 7. Lấy danh sách các đội và thành viên đội cho sự kiện đã chọn (sort theo lớp)
$all_teams = [];
if ($event_id && empty($_GET['show_effect'])) {
    $stmt = $pdo->prepare("SELECT id, name FROM teams WHERE event_id = ? ORDER BY name");
    $stmt->execute([$event_id]);
    $all_teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($all_teams as &$team) {
        $mstmt = $pdo->prepare("
            SELECT s.id, s.full_name, s.class
            FROM team_members tm
            JOIN students s ON tm.student_id = s.id
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

// 8. Lấy học sinh chưa thuộc đội nào (cho tính năng thêm thành viên)
$students_no_team = [];
if ($event_id && $all_teams) {
    $ids = [];
    foreach ($all_teams as $tm) foreach ($tm['members'] as $m) $ids[] = $m['id'];
    foreach ($students as $s) if (!in_array($s['id'], $ids)) $students_no_team[] = $s;
}

// 9. Màu pastel cho đội
$team_colors = ["#a5d8ff","#b2f2bb","#ffd8a8","#ffadad","#eebefa","#d0ebff","#b8f2e6","#ffe066","#ffa8a8","#c0eb75"];

// 10. Thông báo flash
$success = $_SESSION['success'] ?? null;
unset($_SESSION['success']);
?>
<?php
$pageTitle = "CHIA ĐỘI HOẠT ĐỘNG";
$full_name = $_SESSION['full_name'] ?? '';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<main class="ml-0 lg:ml-64 pt-4 min-h-screen bg-slate-50/50 transition-all duration-300 ease-in-out p-4 sm:p-6 lg:p-8" x-data="{ showRandomForm: false, showEffect: <?= isset($_GET['show_effect']) && isset($_SESSION['random_teams_effect']) ? 'true' : 'false' ?> }">
    <div class="max-w-7xl mx-auto pb-12">
        <div class="flex items-center gap-3 mb-6">
            <a href="../dashboard.php" class="text-slate-500 hover:text-primary-600 transition-colors flex items-center gap-1.5 text-sm font-medium bg-white px-3 py-1.5 rounded-full border border-slate-200 shadow-sm hover:shadow">
                <i class="bi bi-arrow-left"></i> Về Trang chủ
            </a>
        </div>
        
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-primary-100 text-primary-600 flex items-center justify-center shadow-sm">
                    <i class="bi bi-diagram-3 text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">CHIA ĐỘI HOẠT ĐỘNG</h2>
                    <p class="text-sm font-medium text-slate-500 mt-1">Phân chia nhóm nhanh chóng và ngẫu nhiên</p>
                </div>
            </div>
            
            <form method="get" class="flex items-center bg-white rounded-xl shadow-sm border border-slate-200 p-2">
                <label class="px-3 text-sm font-semibold text-slate-600 flex-shrink-0">Hoạt động:</label>
                <select name="event_id" class="flex-1 min-w-[200px] border-none bg-slate-50 rounded-lg px-3 py-2 text-sm font-medium focus:ring-0 outline-none text-slate-700" required onchange="this.form.submit()">
                    <option value="">-- Chọn hoạt động --</option>
                    <?php foreach($events as $ev): ?>
                        <option value="<?= $ev['id'] ?>" <?= $event_id==$ev['id']?'selected':'' ?>>
                            <?= htmlspecialchars($ev['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        
        <!-- Alerts -->
        <?php if (!empty($success)): ?>
            <div class="mb-6 flex items-center justify-between p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 rounded-r-lg shadow-sm">
                <div class="flex items-center gap-2">
                    <i class="bi bi-check-circle-fill text-lg"></i>
                    <span class="font-medium"><?= htmlspecialchars($success) ?></span>
                </div>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="mb-6 flex items-center justify-between p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-r-lg shadow-sm">
                <div class="flex items-center gap-2">
                    <i class="bi bi-exclamation-circle-fill text-lg"></i>
                    <span class="font-medium"><?= htmlspecialchars($error) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($event_id && empty($_GET['show_effect'])): ?>
            <!-- Controls -->
            <div class="flex flex-wrap items-center justify-between gap-4 mb-6 bg-white p-4 rounded-2xl shadow-[0_4px_20px_rgb(0,0,0,0.03)] border border-slate-100">
                <button @click="showRandomForm = !showRandomForm" class="flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-5 py-2.5 rounded-xl font-semibold transition-all shadow-sm hover:shadow-md text-sm">
                    <i class="bi bi-shuffle"></i> Chia đội tự động
                </button>
                
                <?php if (!empty($all_teams)): ?>
                <form method="post" onsubmit="return confirm('Bạn chắc chắn muốn xóa toàn bộ đội và thành viên của hoạt động này?');">
                    <button class="flex items-center gap-2 bg-red-50 text-red-600 hover:bg-red-100 px-4 py-2.5 rounded-xl font-semibold transition-colors shadow-sm text-sm border border-red-100" name="delete_all_teams" type="submit">
                        <i class="bi bi-trash3"></i> Xóa tất cả đội
                    </button>
                </form>
                <?php endif; ?>
            </div>

            <!-- Random Team Form (Alpine Collapse) -->
            <div x-show="showRandomForm" x-collapse x-cloak class="mb-6">
                <div class="bg-primary-50 rounded-2xl p-6 border border-primary-100 shadow-inner">
                    <h4 class="text-base font-bold text-primary-800 mb-4 flex items-center gap-2">
                        <i class="bi bi-gear-fill"></i> Thiết lập chia đội ngẫu nhiên
                    </h4>
                    <form method="post" class="flex flex-col sm:flex-row items-end gap-4">
                        <input type="hidden" name="event_id" value="<?= $event_id ?>">
                        <div class="w-full sm:w-auto flex-1 max-w-xs">
                            <label class="block text-xs font-semibold text-primary-700 uppercase tracking-wider mb-2">Số đội cần chia</label>
                            <input type="number" name="num_teams" min="1" max="<?= count($students) ?: 1 ?>" class="w-full px-4 py-2.5 bg-white border border-primary-200 rounded-xl text-primary-900 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none font-bold" required placeholder="Nhập số đội...">
                        </div>
                        <button type="submit" name="random_team_do" class="w-full sm:w-auto bg-primary-600 hover:bg-primary-700 text-white px-6 py-2.5 rounded-xl font-bold transition-all shadow-sm flex items-center justify-center gap-2 h-[42px]">
                            <i class="bi bi-magic"></i> Bắt đầu chia
                        </button>
                    </form>
                    <p class="text-xs text-primary-600 font-medium mt-3 flex items-center gap-1.5">
                        <i class="bi bi-info-circle"></i> Số học sinh hiện có: <b><?= count($students) ?></b>
                    </p>
                </div>
            </div>

            <?php if (empty($all_teams)): ?>
                <!-- List of check-in students (before teams created) -->
                <div class="bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 overflow-hidden mb-6">
                    <div class="p-5 border-b border-slate-100 bg-slate-50/50">
                        <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                            <i class="bi bi-people-fill text-primary-500"></i> Danh sách học sinh đã điểm danh
                        </h3>
                    </div>
                    <?php if ($students): ?>
                    <div class="overflow-auto max-h-[400px]">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase text-xs tracking-wider sticky top-0 z-10 shadow-sm">
                                <tr>
                                    <th class="px-5 py-4 w-16 text-center">STT</th>
                                    <th class="px-5 py-4">Họ và tên</th>
                                    <th class="px-5 py-4 w-32 text-center">Lớp</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                            <?php foreach($students as $k=>$s): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="px-5 py-3 text-center text-slate-500 font-medium"><?= $k+1 ?></td>
                                    <td class="px-5 py-3 font-bold text-slate-800"><?= htmlspecialchars($s['full_name']) ?></td>
                                    <td class="px-5 py-3 text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800 border border-slate-200">
                                            <?= htmlspecialchars($s['class']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="p-8 text-center text-slate-500">
                        <i class="bi bi-person-x text-4xl text-slate-300 mb-3 block"></i>
                        Chưa có học sinh nào điểm danh sự kiện này!
                    </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Display Teams -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($all_teams as $idx=>$team): 
                    $color = $team_colors[$idx % count($team_colors)];
                    // Convert hex to a slightly lighter bg color for tailwind if needed, but we can just use inline styles for the distinct colors
                ?>
                    <div class="bg-white rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.06)] overflow-hidden border border-slate-100 flex flex-col transition-all duration-300 hover:-translate-y-1 group" style="box-shadow: 0 10px 40px <?= $color ?>40;">
                        <!-- Team Header -->
                        <div class="px-6 py-5 border-b border-black/5 flex justify-between items-center" style="background-color: <?= $color ?>; color: #1e293b;">
                            <h3 class="font-extrabold text-lg tracking-tight"><?= htmlspecialchars($team['name']) ?></h3>
                            <button onclick="openTeamModal(<?=$team['id']?>)" class="w-8 h-8 rounded-full bg-white/40 hover:bg-white text-slate-800 flex items-center justify-center transition-colors shadow-sm backdrop-blur-sm">
                                <i class="bi bi-arrows-fullscreen text-sm"></i>
                            </button>
                        </div>
                        
                        <!-- Team Members -->
                        <div class="p-6 flex-1 bg-white">
                            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Thành viên (<?= count($team['members']) ?>)</div>
                            
                            <?php if ($team['members']): ?>
                                <ul class="space-y-2 mb-4">
                                    <?php foreach ($team['members'] as $mem): ?>
                                        <li class="flex items-center justify-between group/item p-2 -mx-2 rounded-lg hover:bg-slate-50 transition-colors">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center text-xs font-bold border border-slate-200">
                                                    <i class="bi bi-person"></i>
                                                </div>
                                                <div>
                                                    <div class="font-bold text-slate-700 text-sm"><?= htmlspecialchars($mem['full_name']) ?></div>
                                                    <div class="text-xs text-slate-500 font-medium"><?= htmlspecialchars($mem['class']) ?></div>
                                                </div>
                                            </div>
                                            <form method="post" onsubmit="return confirm('Xóa thành viên khỏi đội?');" class="opacity-0 group-hover/item:opacity-100 transition-opacity">
                                                <input type="hidden" name="team_id" value="<?=$team['id']?>">
                                                <input type="hidden" name="student_id" value="<?=$mem['id']?>">
                                                <button name="remove_member" class="w-7 h-7 rounded-md bg-red-50 hover:bg-red-500 text-red-500 hover:text-white flex items-center justify-center transition-colors" title="Xóa thành viên">
                                                    <i class="bi bi-x-lg text-xs"></i>
                                                </button>
                                            </form>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="text-center py-6 text-slate-400 text-sm font-medium italic bg-slate-50 rounded-xl mb-4 border border-slate-100">
                                    Chưa có thành viên
                                </div>
                            <?php endif; ?>
                            
                            <!-- Add Member -->
                            <?php if (!empty($students_no_team)): ?>
                                <div class="pt-4 border-t border-slate-100 mt-auto">
                                    <form method="post" class="flex items-center gap-2">
                                        <input type="hidden" name="team_id" value="<?=$team['id']?>">
                                        <select name="student_id" class="flex-1 px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-700 text-sm focus:border-primary-500 focus:bg-white focus:ring-2 outline-none transition-colors" required>
                                            <option value="">+ Thêm người...</option>
                                            <?php foreach($students_no_team as $s): ?>
                                                <option value="<?=$s['id']?>"><?=htmlspecialchars($s['full_name'].' ('.$s['class'].')')?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="w-10 h-10 rounded-lg bg-emerald-50 hover:bg-emerald-500 text-emerald-600 hover:text-white flex items-center justify-center transition-colors border border-emerald-100 hover:border-emerald-500 shadow-sm" name="add_member" title="Thêm">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>

                <!-- Modals for Teams (Alpine or Bootstrap. Let's keep a simple structure) -->
                <?php foreach ($all_teams as $idx=>$team): 
                    $color = $team_colors[$idx % count($team_colors)];
                ?>
                <div class="modal fade" id="modalTeam<?=$team['id']?>" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 rounded-3xl overflow-hidden shadow-2xl">
                      <div class="px-6 py-4 flex justify-between items-center" style="background-color: <?= $color ?>;">
                        <h5 class="text-xl font-extrabold text-slate-900 m-0">
                            <?= htmlspecialchars($team['name']) ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                      </div>
                      <div class="p-6 bg-white">
                        <div class="text-center mb-4 text-slate-500 font-semibold text-sm uppercase tracking-wider">
                            Danh sách thành viên
                        </div>
                        <div id="team-table-<?=$team['id']?>" class="bg-white p-2">
                        <?php if ($team['members']): ?>
                            <table class="w-full text-left text-sm border-collapse">
                                <thead>
                                    <tr class="bg-slate-100">
                                        <th class="px-4 py-3 border border-slate-200 font-bold text-slate-700 w-12 text-center">#</th>
                                        <th class="px-4 py-3 border border-slate-200 font-bold text-slate-700">Họ và tên</th>
                                        <th class="px-4 py-3 border border-slate-200 font-bold text-slate-700 w-24 text-center">Lớp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($team['members'] as $n=>$mem): ?>
                                        <tr>
                                            <td class="px-4 py-3 border border-slate-200 text-center font-medium text-slate-500"><?= $n+1 ?></td>
                                            <td class="px-4 py-3 border border-slate-200 font-bold text-slate-800 text-base"><?= htmlspecialchars($mem['full_name']) ?></td>
                                            <td class="px-4 py-3 border border-slate-200 text-center text-slate-600 font-semibold"><?= htmlspecialchars($mem['class']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="text-center text-slate-400 italic py-4">Chưa có thành viên</div>
                        <?php endif; ?>
                        </div>
                      </div>
                      <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-3 bg-slate-50">
                        <button type="button" class="px-5 py-2.5 rounded-xl font-semibold text-slate-600 hover:bg-slate-200 transition-colors" data-bs-dismiss="modal">Đóng</button>
                        <button type="button" class="px-5 py-2.5 rounded-xl font-bold bg-slate-800 hover:bg-slate-900 text-white shadow-sm flex items-center gap-2 transition-colors"
                            onclick="downloadTeamImage('team-table-<?=$team['id']?>','<?=$team['name']?>','<?=addslashes($eventTitle)?>')">
                            <i class="bi bi-download"></i> Tải ảnh PNG
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Hiệu ứng random (Alpine/JS) -->
        <?php if (isset($_GET['show_effect']) && isset($_SESSION['random_teams_effect'])):
            $teams_data = json_decode($_SESSION['random_teams_effect'], true);
            unset($_SESSION['random_teams_effect']);
        ?>
            <div x-show="showEffect" class="py-8">
                <div class="text-center mb-10">
                    <h3 class="text-3xl font-black text-primary-600 tracking-wider flex items-center justify-center gap-3" id="effect-title">
                        <i class="bi bi-arrow-repeat animate-spin"></i> ĐANG CHIA ĐỘI...
                    </h3>
                </div>
                
                <div id="effect-teams" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"></div>
                
                <div class="text-center mt-12 hidden transition-all" id="view-teams-real">
                    <a href="team.php?event_id=<?=$event_id?>" class="inline-flex items-center gap-2 px-8 py-4 bg-emerald-500 hover:bg-emerald-600 text-white rounded-2xl font-bold text-lg shadow-lg hover:shadow-xl hover:-translate-y-1 transition-all">
                        <i class="bi bi-eye"></i> Xem danh sách chính thức
                    </a>
                </div>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                let teams = <?=json_encode($teams_data)?>;
                let colors = <?=json_encode($team_colors)?>;
                let container = document.getElementById('effect-teams');
                let viewRealBtn = document.getElementById('view-teams-real');
                let t = 0;
                
                function showNextTeam() {
                    if (t >= teams.length) {
                        setTimeout(()=>{
                            viewRealBtn.classList.remove('hidden');
                            document.getElementById('effect-title').innerHTML = '<i class="bi bi-check-circle-fill"></i> HOÀN TẤT CHIA ĐỘI!';
                            document.getElementById('effect-title').classList.remove('text-primary-600');
                            document.getElementById('effect-title').classList.add('text-emerald-500');
                        }, 1000);
                        return;
                    }
                    let team = teams[t];
                    let col = document.createElement('div');
                    let color = colors[t % colors.length];
                    let member0 = team.members.length ? team.members[0] : null;
                    
                    let html = `
                    <div class="bg-white rounded-3xl shadow-xl overflow-hidden border border-slate-100 flex flex-col cursor-pointer transition-all hover:scale-105 active:scale-95 duration-300 animate-[fadeInUp_0.5s_ease-out_forwards]" style="box-shadow: 0 10px 40px ${color}40;" onclick="showAllMembers(this,${t})">
                        <div class="px-6 py-5 border-b border-black/5 text-center" style="background-color: ${color}; color: #1e293b;">
                            <h3 class="font-black text-xl tracking-tight">${team.name}</h3>
                        </div>
                        <div class="p-6 flex-1 bg-white team-body-${t}">
                            <ul class="space-y-3 relative">
                    `;
                    
                    if(member0) {
                        html += `
                                <li class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center font-bold text-xs"><i class="bi bi-person"></i></div>
                                    <div>
                                        <div class="font-bold text-slate-800">${member0.full_name}</div>
                                        <div class="text-xs text-slate-500">${member0.class}</div>
                                    </div>
                                </li>`;
                    }
                    
                    for(let i=1; i<team.members.length; i++) {
                        html += `
                                <li class="hidden items-center gap-3 animate-[fadeIn_0.3s_ease-out_forwards]">
                                    <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center font-bold text-xs"><i class="bi bi-person"></i></div>
                                    <div>
                                        <div class="font-bold text-slate-800">${team.members[i].full_name}</div>
                                        <div class="text-xs text-slate-500">${team.members[i].class}</div>
                                    </div>
                                </li>`;
                    }
                    
                    html += `
                            </ul>
                            <div class="mt-6 text-center team-hint">
                                <div class="inline-flex items-center gap-2 px-4 py-2 bg-slate-50 text-slate-500 rounded-full text-xs font-semibold uppercase tracking-wider border border-slate-200 animate-pulse">
                                    <i class="bi bi-hand-index-thumb"></i> Nhấn để mở
                                </div>
                            </div>
                        </div>
                    </div>`;
                    
                    col.innerHTML = html;
                    container.appendChild(col);
                    t++;
                    setTimeout(showNextTeam, 1000);
                }
                
                // hiệu ứng chữ "Đang chia đội..."
                let title = document.getElementById('effect-title');
                let dots = 0;
                let loadingInt = setInterval(()=>{
                    if(title && t < teams.length) {
                        title.innerHTML = '<i class="bi bi-arrow-repeat animate-spin"></i> ĐANG CHIA ĐỘI' + '.'.repeat((++dots)%4);
                    } else {
                        clearInterval(loadingInt);
                    }
                }, 400);

                window.showAllMembers = function(card, idx) {
                    let body = card.querySelector('.team-body-'+idx);
                    if(!body) return;
                    let lis = body.querySelectorAll('li.hidden');
                    lis.forEach((li, i) => {
                        setTimeout(() => {
                            li.classList.remove('hidden');
                            li.classList.add('flex');
                        }, i * 150); // delay từng người
                    });
                    let hint = body.querySelector('.team-hint');
                    if(hint) hint.style.display='none';
                    card.onclick = null;
                    card.classList.remove('hover:scale-105', 'active:scale-95', 'cursor-pointer');
                };
                
                showNextTeam();
            });
            </script>
            <style>
                @keyframes fadeInUp {
                    from { opacity: 0; transform: translateY(30px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                @keyframes fadeIn {
                    from { opacity: 0; transform: translateX(-10px); }
                    to { opacity: 1; transform: translateX(0); }
                }
            </style>
        <?php endif; ?>
    </div>
</main>

<!-- Bootstrap JS (for modal fallback if used above) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
    function openTeamModal(teamId) {
        var modal = document.getElementById('modalTeam' + teamId);
        if (modal) {
            var bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }
    }
    
    function downloadTeamImage(elementId, teamName, eventName) {
        const node = document.getElementById(elementId);
        if (!node) return;
        
        // Hide scrollbars temporarily
        const oldOverflow = node.style.overflow;
        node.style.overflow = 'hidden';
        
        html2canvas(node, {
            backgroundColor: "#ffffff",
            scale: 2,
            logging: false,
            useCORS: true
        }).then(canvas => {
            node.style.overflow = oldOverflow;
            let clean = function(str) {
                return (str||'').replace(/[^\w\d]/g,'_').replace(/_+/g,'_').replace(/^_|_$/g,'');
            }
            let fileName = clean(teamName) + "_" + clean(eventName) + ".png";
            let link = document.createElement('a');
            link.download = fileName;
            link.href = canvas.toDataURL("image/png");
            link.click();
        });
    }
</script>

<?php include '../includes/footer.php'; ?>