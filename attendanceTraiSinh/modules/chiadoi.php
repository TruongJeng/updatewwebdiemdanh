<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../config/db.php'; // $pdo


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
    if (count($students) < $num_teams) {
        $error = "Số lượng đội lớn hơn số học sinh!";
    } else {
        // Xóa dữ liệu cũ
        $teamIds = $pdo->prepare("SELECT id FROM team_campers");
        $teamIds->execute();
        $ids = $teamIds->fetchAll(PDO::FETCH_COLUMN);
        if ($ids) {
            $in = str_repeat('?,', count($ids)-1) . '?';
            $pdo->prepare("DELETE FROM team_cam_member WHERE team_id IN ($in)")->execute($ids);
            $pdo->prepare("DELETE FROM team_campers WHERE id IN ($in)")->execute($ids);
        }

        // Tạo bảng nếu chưa có
        $pdo->exec("
        CREATE TABLE IF NOT EXISTS team_campers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS team_cam_member (
            id INT AUTO_INCREMENT PRIMARY KEY,
            team_id INT NOT NULL,
            student_code VARCHAR(30) NOT NULL,
            joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_tcm_camper
                FOREIGN KEY (student_code)
                REFERENCES campers(student_code)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $student_codes = array_column($students, 'student_code');
        shuffle($student_codes);
        // ==============================
        // CHIA ĐỘI ĐỀU KHỐI 10–11–12–CHS
        // ==============================

        // Tạo mảng đội rỗng
        $groups = array_fill(0, $num_teams, []);

        // Phân loại học sinh theo khối
        $by_grade = [
            '12'  => [],
            '11'  => [],
            '10'  => [],
            'CHS' => []
        ];

        foreach ($students as $s) {
            $class = trim($s['class']);
            if (preg_match('/^12/i', $class)) {
                $by_grade['12'][] = $s['student_code'];
            } elseif (preg_match('/^11/i', $class)) {
                $by_grade['11'][] = $s['student_code'];
            } elseif (preg_match('/^10/i', $class)) {
                $by_grade['10'][] = $s['student_code'];
            } else {
                $by_grade['CHS'][] = $s['student_code'];
            }
        }

        // Trộn mỗi khối cho ngẫu nhiên
        foreach ($by_grade as &$list) {
            shuffle($list);
        }
        unset($list);

        // Chia luân phiên từng khối
        foreach ($by_grade as $grade => $list) {
            foreach ($list as $i => $scode) {
                $groups[$i % $num_teams][] = $scode;
            }
        }

        $teams_result = [];
        foreach ($groups as $i => $group) {
            $team_name = "Đội " . ($i+1);
            $pdo->prepare("INSERT INTO team_campers (name) VALUES (?)")
            ->execute([$team_name]);
            $team_id = $pdo->lastInsertId();
            $team_members = [];
            foreach ($group as $scode) {
                $pdo->prepare("
                INSERT INTO team_cam_member (team_id, student_code) VALUES (?, ?)")
                ->execute([$team_id, $scode]);
                foreach ($students as $s) if ($s['student_code'] == $scode) $team_members[] = ['student_code'=>$scode, 'full_name'=>$s['full_name'], 'class'=>$s['class']];
            }
            $teams_result[] = ['name'=>$team_name, 'members'=>$team_members, 'team_id'=>$team_id];
        }
        $_SESSION['random_teams_effect'] = json_encode($teams_result);
        header("Location: chiadoi.php?show_effect=1");
        exit;
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

// 8. Lấy học sinh chưa thuộc đội nào (cho tính năng thêm thành viên)
$students_no_team = [];
if ($all_teams) {
    $codes = [];
    foreach ($all_teams as $tm) foreach ($tm['members'] as $m) $codes[] = $m['student_code'];
    foreach ($students as $s) if (!in_array($s['student_code'], $codes)) $students_no_team[] = $s;
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
$pageTitle = "Chia đội tự động trại sinh";
$full_name = $_SESSION['full_name'] ?? '';
include __DIR__ . '/../config/header.php';
?>
<div class="container mt-4 mb-4">
    <div class="bg-white p-4 rounded-4 shadow-sm mb-3">
        <h3 class="mb-3 text-primary text-center" style="font-weight:800;letter-spacing:1px;">
            Chia đội trại sinh là niềm vui bất ngờ!!
        </h3>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="../../dashboard.php" class="back-link"><i class="bi bi-arrow-left-circle"></i> Về Trang chủ</a>
        </div>
        <?php if (empty($_GET['show_effect'])): ?>
        <form method="post" class="text-end mb-3" onsubmit="return confirm('Bạn chắc chắn muốn xóa toàn bộ đội và thành viên của hoạt động này?');">
            <button class="btn btn-outline-danger" name="delete_all_teams" type="submit"><i class="bi bi-trash"></i> Xóa tất cả đội của hoạt động này</button>
        </form>
            <div class="row mt-3">
            <div class="col-md-7 mb-3">
                <h5 class="mb-2 text-secondary" style="font-weight:600;">Danh sách trại sinh</h5>
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
        <div class="row">
            <div class="col-md-5 mb-3">
                <div class="d-flex flex-wrap gap-2 mb-3 justify-content-center">
                    <button class="btn btn-primary btn-lg" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRandom" aria-expanded="false" aria-controls="collapseRandom">
                        <i class="bi bi-shuffle"></i> Chia đội tự động
                    </button>
                </div>
                <div class="collapse mt-3" id="collapseRandom">
                    <form method="post" class="border rounded-3 shadow-sm p-3 bg-light">
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
            <h4 class="mt-4 mb-3 text-primary text-center" style="font-weight:700;" id="effect-title">Đang chia đội...</h4>
            <div id="effect-teams" class="row"></div>
            <div class="text-center mt-4 d-none" id="view-teams-real">
                <a href="chiadoi.php" class="btn btn-success btn-lg"><i class="bi bi-eye"></i> Xem lại danh sách đội</a>
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

        <?php if ($all_teams && empty($_GET['show_effect'])): ?>
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
                                                <input type="hidden" name="student_code" value="<?=$mem['student_code']?>">
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
                                    <select name="student_code" class="form-select form-select-sm" style="width:180px" required>
                                        <option value="">Thêm thành viên...</option>
                                        <?php foreach($students_no_team as $s): ?>
                                            <option value="<?=$s['student_code']?>"><?=htmlspecialchars($s['full_name'].' ('.$s['class'].')')?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-sm btn-outline-success" name="add_member" title="Thêm"><i class="bi bi-plus-lg"></i></button>
                                </form>
                                
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!-- Modal cho từng đội nè he -->
                <div class="modal fade" id="modalTeam<?=$team['id']?>" tabindex="-1" aria-labelledby="modalTeamLabel<?=$team['id']?>" aria-hidden="true">
                  <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header" style="background: <?=$team_colors[$idx%count($team_colors)]?>;">
                        <h5 class="modal-title w-100 text-center" id="modalTeamLabel<?=$team['id']?>" style="font-weight:700;">
                            <?= htmlspecialchars($team['name']) ?>                        </h5>
                        <form method="post"
                            onclick="event.stopPropagation();"
                            class="d-flex gap-1 px-2 mt-2">
                            <input type="hidden" name="team_id" value="<?=$team['id']?>">
                            <input type="text"
                                name="team_name"
                                class="form-control form-control-sm"
                                value="<?=htmlspecialchars($team['name'])?>"
                                required>
                            <button class="btn btn-sm btn-outline-primary"
                                    name="rename_team">
                                <i class="bi bi-check"></i>
                            </button>
                        </form>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                      </div>
                      <div class="modal-body">
                        <div class="text-center mb-3" style="font-size:1.2em;">
                            <b>Danh sách thành viên</b>
                        </div>
                        <div id="team-image-<?=$team['id']?>" style="padding:12px;background:#fff;">
                            <h4 class="text-center mb-3">
                                <?=htmlspecialchars($team['name'])?>
                            </h4>

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
                      </div>
                      <div class="modal-footer justify-content-center">
                        <button type="button" class="btn btn-success"
                            onclick="downloadTeamImage('team-image-<?=$team['id']?>', '<?=htmlspecialchars(addslashes($team['name']))?>', 'ChiaDoiHoatDong')">
                            <i class="bi bi-image"></i> Tải ảnh PNG
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                      </div>
                    </div>
                  </div>
                </div>
            <?php endforeach; ?>
            </div>

            <?php include __DIR__ . '/../config/footer.php'; ?>

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
<?php include __DIR__ . '/../../includes/footer.php'; ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>