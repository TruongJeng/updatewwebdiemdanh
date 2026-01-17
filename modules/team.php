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
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>CLB Kỹ năng Đoàn - Hội Trường THPT Lý Thường Kiệt</title>
    <link rel="icon" type="image/png" href="/hethongdiemdanh/assets/logo_CLB.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        body{ background: #f6faff;}
        .table-scroll { max-height:370px; overflow:auto;}
        .manual-input { width:120px; }
        .btn-lg { font-size: 1.1em; padding: 0.75em 2em;}
        .card-team {
            border-radius: 14px; box-shadow: 0 2px 8px #3178c60a; margin-bottom:18px;
            min-height: 180px;
        }
        .card-team .card-header { font-weight: bold; border-bottom: none;}
        .team-member .bi-x { cursor:pointer; color:#e8590c;}
        .add-member-form {display:flex; gap:7px; align-items: center;}
    </style>
</head>
<body>
<?php
$pageTitle = "CLB Kỹ năng Đoàn - Hội Trường THPT Lý Thường Kiệt";
$full_name = $_SESSION['full_name'] ?? '';
include '../includes/header.php';
?>
<div class="container mt-4 mb-4">
    <div class="bg-white p-4 rounded-4 shadow-sm mb-3">
        <h3 class="mb-3 text-primary text-center" style="font-weight:800;letter-spacing:1px;">
            Chia đội cho hoạt động
        </h3>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="../dashboard.php" class="back-link"><i class="bi bi-arrow-left-circle"></i> Về Trang chủ</a>
        </div>
        <form method="get" class="row g-2 mb-3 justify-content-center">
            <div class="col-auto d-flex align-items-center">
                <label class="me-2"><b>Chọn hoạt động:</b></label>
                <select name="event_id" class="form-select" style="width:270px;" required onchange="this.form.submit()">
                    <option value="">-- Chọn --</option>
                    <?php foreach($events as $ev): ?>
                        <option value="<?= $ev['id'] ?>" <?= $event_id==$ev['id']?'selected':'' ?>>
                            <?= htmlspecialchars($ev['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
        <?php if ($event_id): ?>
        <?php if (empty($_GET['show_effect'])): ?>
        <form method="post" class="text-end mb-3" onsubmit="return confirm('Bạn chắc chắn muốn xóa toàn bộ đội và thành viên của hoạt động này?');">
            <button class="btn btn-outline-danger" name="delete_all_teams" type="submit"><i class="bi bi-trash"></i> Xóa tất cả đội của hoạt động này</button>
        </form>
        <div class="row mt-3">
            <div class="col-md-7 mb-3">
                <h5 class="mb-2 text-secondary" style="font-weight:600;">Danh sách học sinh đã điểm danh</h5>
                <div class="table-scroll">
                <table class="table table-bordered table-striped table-hover align-middle shadow-sm">
                    <thead class="table-primary">
                        <tr>
                            <th style="width:45px;">STT</th>
                            <th>Tên</th>
                            <th style="width:90px;">Lớp</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($students as $k=>$s): ?>
                        <tr>
                            <td><?= $k+1 ?></td>
                            <td><?= htmlspecialchars($s['full_name']) ?></td>
                            <td><?= htmlspecialchars($s['class']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
            <div class="col-md-5 mb-3">
                <div class="d-flex flex-wrap gap-2 mb-3 justify-content-center">
                    <button class="btn btn-primary btn-lg" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRandom" aria-expanded="false" aria-controls="collapseRandom">
                        <i class="bi bi-shuffle"></i> Chia đội tự động
                    </button>
                </div>
                <div class="collapse mt-3" id="collapseRandom">
                    <form method="post" class="border rounded-3 shadow-sm p-3 bg-light">
                        <input type="hidden" name="event_id" value="<?= $event_id ?>">
                        <div class="mb-2">
                            <label class="mb-1"><b>Số đội cần chia:</b></label>
                            <input type="number" name="num_teams" min="1" max="<?= count($students) ?>" class="form-control" style="width:110px;" required>
                        </div>
                        <button type="submit" name="random_team_do" class="btn btn-primary mt-1">Bắt đầu chia tự động</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success mt-3"><?= $success ?></div>
        <?php elseif (!empty($error)): ?>
            <div class="alert alert-danger mt-3"><?= $error ?></div>
        <?php endif; ?>

        <?php
        // Hiệu ứng xổ số random đội
        if (isset($_GET['show_effect']) && isset($_SESSION['random_teams_effect'])):
            $teams_data = json_decode($_SESSION['random_teams_effect'], true);
            unset($_SESSION['random_teams_effect']);
        ?>
            <h4 class="mt-4 mb-3 text-primary text-center" style="font-weight:700;" id="effect-title">ĐANG CHIA ĐỘI...</h4>
            <div id="effect-teams" class="row"></div>
            <div class="text-center mt-4 d-none" id="view-teams-real">
                <a href="team.php?event_id=<?=$event_id?>" class="btn btn-success btn-lg"><i class="bi bi-eye"></i> Xem lại danh sách đội</a>
            </div>
            <script>
            let teams = <?=json_encode($teams_data)?>;
            let colors = <?=json_encode($team_colors)?>;
            let container = document.getElementById('effect-teams');
            let viewRealBtn = document.getElementById('view-teams-real');
            let t = 0;
            function showNextTeam() {
                if (t >= teams.length) {
                    setTimeout(()=>{viewRealBtn.classList.remove('d-none')}, 1000);
                    return;
                }
                let team = teams[t];
                let col = document.createElement('div');
                col.className = "col-md-4 mb-3";
                let color = colors[t % colors.length];
                let member0 = team.members.length ? team.members[0] : null;
                let html = `<div class="card card-team h-100 animate__animated animate__fadeInUp" style="background:${color};cursor:pointer;" onclick="showAllMembers(this,${t})">
                    <div class="card-header fs-5 text-dark text-center">${team.name}</div>
                    <div class="card-body team-body-${t}">
                        <ol class="mb-0 ps-3">`;
                if(member0)
                    html += `<li><b>${member0.full_name}</b> <span class="text-muted">(${member0.class})</span></li>`;
                for(let i=1;i<team.members.length;i++)
                    html += `<li class="d-none"><b>${team.members[i].full_name}</b> <span class="text-muted">(${team.members[i].class})</span></li>`;
                html += `</ol><div class="text-center text-muted mt-2 team-hint">Nhấn để xem thành viên</div></div></div>`;
                col.innerHTML = html;
                container.appendChild(col);
                t++;
                setTimeout(showNextTeam, 1200);
            }
            // hiệu ứng chữ "Đang chia đội..."
            let title = document.getElementById('effect-title');
            let dots = 0;
            setInterval(()=>{
                if(title) {
                    title.innerHTML = "ĐANG CHIA ĐỘI" + '.'.repeat((++dots)%4);
                }
            }, 350);

            function showAllMembers(card, idx) {
                let body = card.querySelector('.team-body-'+idx);
                if(!body) return;
                let lis = body.querySelectorAll('li.d-none');
                lis.forEach(li=>li.classList.remove('d-none'));
                let hint = body.querySelector('.team-hint');
                if(hint) hint.style.display='none';
                card.onclick=null;
            }
            showNextTeam();
            </script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
        <?php endif; ?>

        <?php if ($event_id && $all_teams && empty($_GET['show_effect'])): ?>
            <h4 class="mt-4 mb-3 text-primary" style="font-weight:700;">Danh sách các đội & thành viên</h4>
            <div class="row">
            <?php foreach ($all_teams as $idx=>$team): ?>
                <div class="col-md-4 mb-3">
                    <div class="card card-team h-100 shadow"
                         style="background: <?=$team_colors[$idx%count($team_colors)]?>; cursor:pointer;"
                         onclick="openTeamModal(<?=$team['id']?>)">
                        <div class="card-header text-center fs-5"><?= htmlspecialchars($team['name']) ?></div>
                        <div class="card-body pb-2">
                            <div class="mb-1"><b>Thành viên:</b></div>
                            <?php if ($team['members']): ?>
                                <ol class="mb-0 ps-3">
                                    <?php foreach ($team['members'] as $mem): ?>
                                        <li class="team-member small">
                                            <?= htmlspecialchars($mem['full_name']) ?>
                                            <span class="text-muted">(<?= htmlspecialchars($mem['class']) ?>)</span>
                                            <form method="post" style="display:inline" onsubmit="event.stopPropagation();return confirm('Xóa thành viên khỏi đội?');">
                                                <input type="hidden" name="team_id" value="<?=$team['id']?>">
                                                <input type="hidden" name="student_id" value="<?=$mem['id']?>">
                                                <button name="remove_member" class="btn btn-link btn-sm p-0" title="Xóa thành viên"><i class="bi bi-x"></i></button>
                                            </form>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php else: ?>
                                <div><i>Chưa có thành viên</i></div>
                            <?php endif; ?>
                            <?php if (!empty($students_no_team)): ?>
                                <hr>
                                <form method="post" class="add-member-form" onclick="event.stopPropagation();">
                                    <input type="hidden" name="team_id" value="<?=$team['id']?>">
                                    <select name="student_id" class="form-select form-select-sm" style="width:180px" required>
                                        <option value="">Thêm thành viên...</option>
                                        <?php foreach($students_no_team as $s): ?>
                                            <option value="<?=$s['id']?>"><?=htmlspecialchars($s['full_name'].' ('.$s['class'].')')?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-sm btn-outline-success" name="add_member" title="Thêm"><i class="bi bi-plus-lg"></i></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!-- Modal cho từng đội -->
                <div class="modal fade" id="modalTeam<?=$team['id']?>" tabindex="-1" aria-labelledby="modalTeamLabel<?=$team['id']?>" aria-hidden="true">
                  <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header" style="background: <?=$team_colors[$idx%count($team_colors)]?>;">
                        <h5 class="modal-title w-100 text-center" id="modalTeamLabel<?=$team['id']?>" style="font-weight:700;">
                            <?= htmlspecialchars($team['name']) ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                      </div>
                      <div class="modal-body">
                        <div class="text-center mb-3" style="font-size:1.2em;">
                            <b>Danh sách thành viên</b>
                        </div>
                        <div id="team-table-<?=$team['id']?>">
                        <?php if ($team['members']): ?>
                            <table class="table table-bordered table-striped w-100 mx-auto" style="font-size:1.2em;max-width:500px;background:#fff;">
                                <thead class="table-primary">
                                    <tr>
                                        <th style="width:55px;">STT</th>
                                        <th>Họ và tên</th>
                                        <th>Lớp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($team['members'] as $n=>$mem): ?>
                                        <tr>
                                            <td class="text-center"><?= $n+1 ?></td>
                                            <td><?= htmlspecialchars($mem['full_name']) ?></td>
                                            <td><?= htmlspecialchars($mem['class']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="text-center text-muted">(Chưa có thành viên)</div>
                        <?php endif; ?>
                        </div>
                      </div>
                      <div class="modal-footer justify-content-center">
                        <button type="button" class="btn btn-success"
                            onclick="downloadTeamImage('team-table-<?=$team['id']?>','<?=$team['name']?>','<?=addslashes($eventTitle)?>')">
                            <i class="bi bi-image"></i> Tải ảnh PNG
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                      </div>
                    </div>
                  </div>
                </div>
            <?php endforeach; ?>
            </div>
            <script>
            function openTeamModal(teamId) {
                var modal = document.getElementById('modalTeam'+teamId);
                if (modal) {
                    var bsModal = new bootstrap.Modal(modal);
                    bsModal.show();
                }
            }
            function downloadTeamImage(elementId, teamName, eventName) {
                const node = document.getElementById(elementId);
                if (!node) return;
                html2canvas(node, {
                    backgroundColor: "#fff",
                    scale: 2
                }).then(canvas => {
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
            <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
        <?php endif; ?>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>