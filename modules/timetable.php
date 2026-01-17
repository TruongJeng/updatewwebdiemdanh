<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Quy định thời gian cho từng tiết
$periodTimes = [
    1 => '07:00 - 07:45',
    2 => '07:50 - 08:35',
    3 => '08:40 - 09:25',
    4 => '09:35 - 10:20',
    5 => '10:25 - 11:10',
    6 => '13:00 - 13:45',
    7 => '13:50 - 14:35',
    8 => '14:40 - 15:25',
    9 => '15:35 - 16:20',
    10 => '16:25 - 17:10'
];

// Quét thư mục uploads lấy tất cả file timetabletuanX.csv
$weekFiles = glob(__DIR__ . '/../uploads/timetabletuan*.csv');
$weeks = [];
foreach($weekFiles as $f) {
    if(preg_match('/timetabletuan(\d+)\.csv$/', $f, $m)) {
        $weeks[] = intval($m[1]);
    }
}
sort($weeks);
$week = isset($_GET['tuan']) && in_array(intval($_GET['tuan']), $weeks) ? intval($_GET['tuan']) : (count($weeks) ? $weeks[0] : 1);
$file = __DIR__ . '/../uploads/timetabletuan' . $week . '.csv';

if (!file_exists($file)) {
    echo '<div style="color:red; font-weight:bold; text-align:center; margin-top:30px;">Hiện tại tuần này chưa có, bạn chọn tuần khác nhé.</div>';
    exit;
}

// Bảng ánh xạ mã môn sang tên môn
$subjectMap = [
    'to' => 'Toán học', 'vl' => 'Vật lý', 'ho' => 'Hóa học',
    'sv' => 'Sinh học', 'nv' => 'Ngữ văn', 'ls' => 'Lịch sử',
    'di' => 'Địa lý', 'av' => 'Tiếng Anh', 'cd' => 'GDCD',
    'cn' => 'Công nghệ', 'ti' => 'Tin học', 'th' => 'Thể dục',
    'qp' => 'QP-AN', 'td' => 'Thể dục', 'sh' => 'Sinh hoạt lớp',
    'dp' => 'GD địa phương', 'tn' => 'HĐTN', 'kc' => 'Công nghệ',
];
function subjectFullName($code, $subjectMap) {
    $prefix2 = strtolower(substr($code, 0, 2));
    $prefix3 = strtolower(substr($code, 0, 3));
    if ($prefix3 === 'shl') return 'Sinh hoạt lớp';
    return $subjectMap[$prefix2] ?? $code;
}
function convertClassName($name) {
    if (strtoupper($name) === '10C') return $name;
    if (preg_match('/^([0-2])([A-Z]+\d*)$/i', $name, $m)) {
        $num = intval($m[1]) + 10;
        return $num . $m[2];
    }
    return $name;
}

$rows = [];
if (($handle = fopen($file, "r")) !== false) {
    while (($data = fgetcsv($handle)) !== false) {
        $rows[] = array_slice($data, 3);
    }
    fclose($handle);
}
if(count($rows)<10) {
    echo '<div style="color:red; font-weight:bold; text-align:center; margin-top:30px;">File dữ liệu tuần này không hợp lệ!</div>';
    exit;
}

// Lấy 60 hàng đầu tiên (6 ngày * 10 hàng/ngày)
$rows = array_slice($rows, 0, 60);
// Bỏ 3 hàng đầu
$rows = array_slice($rows, 3);

$classList = $rows[0];
unset($rows[0]);
$rows = array_values($rows);

$class = $_GET['class'] ?? $classList[0];
$classCol = array_search($class, $classList);

$soThu = 6; // Thứ 2 -> 7
$soTiet = 10; // mỗi thứ 10 hàng trong file

// Chuẩn bị dữ liệu dạng [thứ][tiết] = mã môn
$timetable = [];
for($thu=0; $thu<$soThu; $thu++) {
    $startRow = $thu * $soTiet;
    // Sáng: 5 tiết đầu (1-5)
    for($t=1; $t<=5; $t++) {
        $rowIdx = $startRow + ($t-1);
        $mon = trim($rows[$rowIdx][$classCol] ?? '');
        $timetable[$thu+2][$t] = $mon;
    }
    // Chiều: lấy 5 dòng tiếp theo, bỏ dòng đầu tiên (tiết 6-9 là dòng 2,3,4,5 trong 5 dòng chiều)
    for($t=6; $t<=9; $t++) {
        $rowIdx = $startRow + 5 + ($t-6) + 1;
        $mon = trim($rows[$rowIdx][$classCol] ?? '');
        $timetable[$thu+2][$t] = $mon;
    }
    $timetable[$thu+2][10] = '';
}

$sangLetters  = ['S', 'Á', 'N', 'G', ''];
$chieuLetters = ['C', 'H', 'I', 'Ê', 'U'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
    <title>Xem thời khoá biểu theo tuần <?= $week ?> - Lớp <?= htmlspecialchars(convertClassName($class)) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        html, body { max-width: 100vw; }
        body {
            background: linear-gradient(120deg, #dbeafe 0%, #f0fff0 100%);
            min-height: 100vh;
            font-size: 1em;
        }
        .tkb-header-title {
            color: #245ca7;
            font-family: 'Segoe UI', serif;
            font-weight: bold;
            font-size: 2.2em;
            letter-spacing: 1px;
            text-shadow: 1px 2px 7px #a2d1f9;
            animation: popin 0.8s cubic-bezier(.68,-0.55,.27,1.55);
        }
        @keyframes popin {
            0% {transform: scale(0.7); opacity: 0;}
            100% {transform: scale(1); opacity:1;}
        }
        .tkb-header-note {
            color:#666; font-size:1.08em;
            margin-left: 10px;
        }
        .phan-tiet-btn-wrap {
            width:100%; text-align:right; margin-bottom: 5px; margin-top: -8px;
        }
        .phan-tiet-btn {
            font-size:0.98em; font-weight:600; padding:3px 12px; border-radius: 20px;
        }
        .select-big {
            font-size: 1.25em;
            font-weight: bold;
            border: 2px solid #1e40af;
            border-radius: 12px;
            padding: 8px 18px;
            background: #f1f5ff;
            box-shadow: 2px 3px 16px #dbeafe;
            margin-right: 14px;
            transition: box-shadow 0.3s, border 0.3s;
        }
        .select-big:focus, .select-big:hover {
            border: 2.5px solid #fbbf24;
            box-shadow: 0px 0px 12px #fde68a;
            background: #fffbe7;
        }
        .week-dropdown {
            font-size: 1.08em;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 8px;
            border: 2px solid #0ea5e9;
            background: #f0f9ff;
            margin-left: 6px;
            transition: border 0.3s, background 0.3s;
        }
        .week-dropdown:focus, .week-dropdown:hover {
            border: 2.5px solid #f43f5e;
            background: #fdf2f8;
        }
        .tkb-table { width:100%; border-collapse: collapse; margin-top: 15px; box-shadow: 2px 6px 18px #bee3f8;}
        .tkb-table th, .tkb-table td { border: 1px solid #245ca7; text-align:center; vertical-align:middle; font-size:1em;}
        .tkb-table th { background: linear-gradient(90deg, #2563eb 0%, #38bdf8 90%); color: #fff;}
        .tkb-period { background: #2563eb; color:#fff; font-weight:700;}
        .tkb-session { background:#fef9c3; color:#b45309; font-weight:700;}
        .tkb-cell { font-weight: 500; font-size:0.96em; min-width:78px; background: #fff;}
        .tkb-cell:not(:empty) { background: #dbeafe; transition: background 0.3s;}
        .tkb-cell:hover { background: #ffe7ba; cursor:pointer; transition: background 0.2s;}
        .tkb-table tbody td { height:36px; }
        .tkb-divider td { background:#fde68a!important; height:7px!important; padding:0!important; border-top:2px solid #fbbf24; border-bottom:2px solid #fbbf24; }
        .class-form-row {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 18px;
            flex-wrap: wrap;
        }
        .class-form-row form {
            margin-bottom: 0;
        }
        @media (max-width: 900px) {
            .tkb-header-title { font-size:1.4em;}
            .tkb-header-note { font-size:1em;}
            .class-form-row { flex-direction: column; gap: 6px; }
            .select-big, .week-dropdown { width: 100%; margin-bottom: 7px;}
            .phan-tiet-btn-wrap { text-align:center; }
        }
        @media (max-width:600px) {
            .tkb-header-title { font-size:1.07em;}
            .tkb-header-note { font-size:0.97em;}
            .tkb-table th, .tkb-table td { font-size:0.92em;}
            .tkb-table { font-size:0.92em;}
            .tkb-cell { min-width:56px;}
            .class-form-row { gap: 3px;}
        }
        .fade-in {
            animation: fadeIn 1.2s;
        }
        @keyframes fadeIn {
            0% { opacity: 0;}
            100% { opacity: 1;}
        }
        /* Popup Phan Tiet */
        #modalPeriod {
            display: none; position:fixed; z-index:9999; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.18);
        }
        #modalPeriod .modal-content {
            background:#fffbe7; border-radius:16px; width:92vw; max-width:370px; margin:60px auto; padding:20px 10px 20px 10px; box-shadow:0 8px 36px #a3a3a3; position:relative;
            animation: fadeIn 0.4s;
        }
        #modalPeriod .modal-content table { width:100%; font-size:1.07em;}
        #modalPeriod .modal-content th, #modalPeriod .modal-content td { padding:4px 8px;}
        #modalPeriod .modal-content th { color:#933; border-bottom:2px solid #fbbf24;}
        #modalPeriod .modal-content .close-btn {
            position:absolute; right:12px; top:8px; background:none; border:none; font-size:1.4em; color:#a16207;
        }
        #modalPeriod .modal-title {
            font-size:1.15em; font-weight:bold; text-align:center; color:#b45309; margin-bottom:8px;
        }
        #modalPeriod .modal-content tr td:first-child { font-weight:bold; color:#1e293b;}
        #modalPeriod .modal-content tr:hover td { background:#fef08a; }
        /* Table scroll on mobile */
        .tkb-table-wrap { overflow-x:auto; width:100%; }
        .header-ltk {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 10px;
        margin-left: 2px;
        }
        .ltk-logo {
            height: 44px;
            width: 44px;
            border-radius: 8px;
            box-shadow: 1px 2px 6px #bcd;
            background: #fff;
            object-fit: cover;
        }
        .ltk-school-name {
            font-size: 1.25em;
            font-weight: bold;
            color: #0d3d8a;
            letter-spacing: 1px;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        @media (max-width:600px) {
            .header-ltk { gap: 7px; margin-bottom:6px;}
            .ltk-logo { height: 30px; width:30px;}
            .ltk-school-name { font-size: 1em;}
        }
    </style>
    <script>
        function openPeriodRule(){ document.getElementById('modalPeriod').style.display='block'; }
        function closePeriodRule(){ document.getElementById('modalPeriod').style.display='none'; }
        window.addEventListener('keydown', function(e){
            if(e.key === "Escape") closePeriodRule();
        });
    </script>
</head>
<body>
<div class="header-ltk">
    <img src="https://i.postimg.cc/xTJJnZm4/c4f1c5a7b871416293dfb78298513ea9.png" alt="Logo Trường THPT Lý Thường Kiệt" class="ltk-logo">
    <span class="ltk-school-name">Trường THPT Lý Thường Kiệt</span>
</div>
<div style="padding:20px 2vw 0 2vw;" class="fade-in">
    <div class="text-center mb-2">
        <span class="tkb-header-title">Xem thời khóa biểu tuần <?= $week ?></span>
        <span class="tkb-header-note">| See the weekly schedule of week <?= $week ?></span>
    </div>
    <div class="phan-tiet-btn-wrap">
        <button type="button"
            class="btn btn-outline-warning btn-sm phan-tiet-btn"
            onclick="openPeriodRule()">
            ⏰ Quy định phân tiết
        </button>
    </div>
    <div class="class-form-row mb-2 mt-2">
        <form method="get" class="d-inline">
            <input type="hidden" name="tuan" value="<?= $week ?>">
            <label for="class" class="me-2" style="font-size:1.06em; color:#1e293b; font-weight: 600;">Chọn lớp:</label>
            <select name="class" id="class" onchange="this.form.submit()" class="select-big">
                <?php foreach($classList as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= $c==$class?'selected':'' ?>>
                        <?= htmlspecialchars(convertClassName($c)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <form method="get" class="d-inline ms-auto" style="flex:1; text-align:right;">
            <input type="hidden" name="class" value="<?= htmlspecialchars($class) ?>">
            <label for="tuan" style="font-size:1.06em; color:#0e7490; font-weight:600;">Chọn tuần:</label>
            <select name="tuan" id="tuan" class="week-dropdown" onchange="this.form.submit()">
                <?php foreach($weeks as $w): ?>
                    <option value="<?= $w ?>" <?= $w == $week ? 'selected' : '' ?>>Tuần <?= $w ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="tkb-table-wrap">
    <table class="tkb-table">
        <thead>
            <tr>
                <th class="tkb-period">Tiết<br>Period</th>
                <th class="tkb-session">Buổi<br>Session</th>
                <th>Thứ 2 | Mon</th>
                <th>Thứ 3 | Tue</th>
                <th>Thứ 4 | Wed</th>
                <th>Thứ 5 | Thu</th>
                <th>Thứ 6 | Fri</th>
                <th>Thứ 7 | Sat</th>
            </tr>
        </thead>
        <tbody>
        <?php for($t=1;$t<=10;$t++):
            if($t <= 5) $sessionLetter = $sangLetters[$t-1];
            else        $sessionLetter = $chieuLetters[$t-6];
        ?>
            <?php if($t==6): ?>
            <tr class="tkb-divider"><td colspan="8"></td></tr>
            <?php endif; ?>
            <tr>
                <td class="tkb-period"><?= $t ?></td>
                <td class="tkb-session"><?= $sessionLetter ?></td>
                <?php for($d=2;$d<=7;$d++):
                    $mon = $timetable[$d][$t] ?? '';
                ?>
                    <td class="tkb-cell"><?= $mon ? htmlspecialchars(subjectFullName($mon, $subjectMap)) : '' ?></td>
                <?php endfor; ?>
            </tr>
        <?php endfor; ?>
        </tbody>
    </table>
    </div>
</div>
<!-- Modal phân tiết -->
<div id="modalPeriod">
    <div class="modal-content">
        <button onclick="closePeriodRule()" class="close-btn" title="Đóng">&times;</button>
        <div class="modal-title">Quy định phân tiết</div>
        <table>
            <tr>
                <th>Tiết</th>
                <th>Thời gian</th>
            </tr>
            <?php foreach($periodTimes as $k=>$v): ?>
            <tr>
                <td><?= $k ?></td>
                <td><?= $v ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<!-- Nội dung trang kết thúc -->
</div>
<footer style="
    position:fixed;
    left:0; right:0; bottom:0;
    background:#fff;
    border-top:1px solid #ccc;
    height:30px;
    display:flex;
    align-items:center;
    justify-content:flex-end;
    z-index:1000;
    font-size:14px;
    color:#999;
    padding-right:24px;
    box-shadow:0 -2px 8px rgba(0,0,0,0.03);
">
    2025 © Ứng dụng được phát triển bởi
    <a href="https://www.facebook.com/clbkynangdoan.ltk" target="_blank" style="color:#1976d2;text-decoration:underline;margin-left:6px;">
        CLB Kỹ năng Đoàn - Hội Trường THPT Lý Thường Kiệt
    </a>
</footer>
<style>
@media (max-width: 600px) {
    footer {
        font-size: 10px !important;
        padding-right: 6px !important;
        height: 18px !important;
    }
    .main, .container, .info-board {
        padding-bottom: 24px !important;
    }
}
@media (max-width: 400px) {
    footer {
        display: none !important;
    }
}
</style>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
