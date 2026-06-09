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
    <title>Thời Khoá Biểu - Lớp <?= htmlspecialchars(convertClassName($class)) ?></title>
    <!-- Tailwind CSS (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { primary: { 50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe', 300: '#93c5fd', 400: '#60a5fa', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8', 800: '#1e40af', 900: '#1e3a8a' } }
                }
            }
        }
    </script>
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 50%, #bae6fd 100%);
            min-height: 100vh;
        }
        /* Custom scrollbar for table */
        .table-container::-webkit-scrollbar {
            height: 8px;
        }
        .table-container::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.5);
            border-radius: 8px;
        }
        .table-container::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 8px;
        }
        .tkb-cell:empty {
            background-color: transparent !important;
        }
    </style>
</head>
<body class="text-slate-800 font-sans antialiased pb-16" x-data="{ showModal: false }">

    <!-- Header -->
    <div class="bg-white/80 backdrop-blur-md border-b border-white/50 shadow-sm sticky top-0 z-40 px-4 py-3 flex items-center gap-3">
        <img src="https://i.postimg.cc/xTJJnZm4/c4f1c5a7b871416293dfb78298513ea9.png" alt="Logo THPT Lý Thường Kiệt" class="h-10 w-10 md:h-12 md:w-12 rounded-xl shadow-md bg-white object-cover">
        <div>
            <h1 class="text-lg md:text-xl font-extrabold text-primary-800 tracking-tight leading-tight">THPT Lý Thường Kiệt</h1>
            <p class="text-xs md:text-sm font-medium text-slate-500">Tra cứu Thời Khóa Biểu</p>
        </div>
    </div>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 md:pt-8 pb-12 animate-[fadeIn_0.5s_ease-out]">
        
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-8">
            <!-- Title -->
            <div class="text-center md:text-left">
                <h2 class="text-2xl md:text-4xl font-black text-primary-700 drop-shadow-sm flex items-center justify-center md:justify-start gap-3">
                    <i class="bi bi-calendar-week text-primary-500"></i> Thời Khóa Biểu
                </h2>
                <p class="text-slate-600 font-medium mt-1">Lịch học chính thức Tuần <?= $week ?></p>
            </div>

            <!-- Controls -->
            <div class="flex flex-col sm:flex-row items-center gap-4 bg-white/60 backdrop-blur-md p-3 md:p-4 rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-white">
                <form method="get" class="w-full sm:w-auto flex items-center gap-2">
                    <input type="hidden" name="tuan" value="<?= $week ?>">
                    <div class="relative w-full sm:w-auto">
                        <select name="class" onchange="this.form.submit()" class="w-full sm:w-auto appearance-none bg-white border-2 border-primary-200 text-primary-800 font-bold text-lg md:text-xl rounded-xl px-5 py-2.5 pr-10 hover:border-primary-400 focus:border-primary-500 focus:ring-4 focus:ring-primary-500/20 outline-none transition-all shadow-sm cursor-pointer">
                            <?php foreach($classList as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>" <?= $c==$class?'selected':'' ?>>
                                    Lớp <?= htmlspecialchars(convertClassName($c)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="bi bi-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-primary-500 pointer-events-none font-bold"></i>
                    </div>
                </form>

                <div class="h-10 w-px bg-slate-300 hidden sm:block"></div>

                <form method="get" class="w-full sm:w-auto flex items-center gap-2">
                    <input type="hidden" name="class" value="<?= htmlspecialchars($class) ?>">
                    <div class="relative w-full sm:w-auto">
                        <select name="tuan" onchange="this.form.submit()" class="w-full sm:w-auto appearance-none bg-slate-50 border border-slate-200 text-slate-700 font-semibold rounded-lg px-4 py-2 pr-9 hover:border-primary-300 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none transition-all cursor-pointer shadow-sm">
                            <?php foreach($weeks as $w): ?>
                                <option value="<?= $w ?>" <?= $w == $week ? 'selected' : '' ?>>Tuần <?= $w ?></option>
                            <?php endforeach; ?>
                        </select>
                        <i class="bi bi-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-sm"></i>
                    </div>
                </form>
                
                <button @click="showModal = true" type="button" class="w-full sm:w-auto bg-amber-100 hover:bg-amber-200 text-amber-700 px-4 py-2 rounded-lg font-semibold text-sm transition-colors border border-amber-200 shadow-sm flex items-center justify-center gap-2">
                    <i class="bi bi-clock-history"></i> Giờ học
                </button>
            </div>
        </div>

        <!-- Timetable -->
        <div class="bg-white/80 backdrop-blur-xl rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.06)] border border-white overflow-hidden p-1 sm:p-2">
            <div class="overflow-x-auto table-container rounded-2xl">
                <table class="w-full text-center border-collapse min-w-[700px]">
                    <thead>
                        <tr>
                            <th class="p-3 bg-gradient-to-br from-primary-600 to-primary-500 text-white font-bold border border-primary-600/20 rounded-tl-xl shadow-inner w-14">Tiết</th>
                            <th class="p-3 bg-gradient-to-br from-amber-400 to-amber-500 text-amber-900 font-bold border border-amber-500/20 shadow-inner w-14">Buổi</th>
                            <?php
                            $days = [2 => 'Thứ 2', 3 => 'Thứ 3', 4 => 'Thứ 4', 5 => 'Thứ 5', 6 => 'Thứ 6', 7 => 'Thứ 7'];
                            foreach($days as $d => $name): ?>
                                <th class="p-3 bg-gradient-to-br from-slate-700 to-slate-800 text-white font-bold border border-slate-700/20 shadow-inner <?= $d==7 ? 'rounded-tr-xl' : '' ?>">
                                    <div class="text-sm md:text-base"><?= $name ?></div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-primary-100/50">
                        <?php for($t=1;$t<=10;$t++):
                            $isMorning = $t <= 5;
                            $sessionLetter = $isMorning ? $sangLetters[$t-1] : $chieuLetters[$t-6];
                        ?>
                            <?php if($t==6): ?>
                            <tr>
                                <td colspan="8" class="bg-gradient-to-r from-transparent via-amber-200 to-transparent h-2 border-none"></td>
                            </tr>
                            <?php endif; ?>
                            <tr class="group">
                                <td class="p-2 md:p-3 bg-primary-50/50 font-bold text-primary-700 border border-primary-100/50 group-hover:bg-primary-100 transition-colors">
                                    <?= $t ?>
                                </td>
                                <td class="p-2 md:p-3 bg-amber-50/50 font-bold text-amber-600 border border-amber-100/50 group-hover:bg-amber-100 transition-colors">
                                    <?= $sessionLetter ?>
                                </td>
                                <?php for($d=2;$d<=7;$d++):
                                    $mon = $timetable[$d][$t] ?? '';
                                    $monName = $mon ? htmlspecialchars(subjectFullName($mon, $subjectMap)) : '';
                                ?>
                                    <td class="p-2 md:p-3 border border-slate-100 font-semibold text-slate-700 tkb-cell <?= $mon ? 'bg-white hover:bg-primary-50 hover:text-primary-700 hover:shadow-sm cursor-default transition-all hover:-translate-y-0.5' : '' ?>">
                                        <?= $monName ?>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </main>

    <!-- Modal Giờ Học (Alpine) -->
    <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <div @click.away="showModal = false" class="bg-white rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-8 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 translate-y-8 scale-95">
            
            <div class="px-6 py-4 bg-amber-400 text-amber-900 flex justify-between items-center">
                <h3 class="font-extrabold text-lg flex items-center gap-2"><i class="bi bi-clock-fill"></i> Thời gian tiết học</h3>
                <button @click="showModal = false" class="w-8 h-8 flex items-center justify-center rounded-full bg-amber-500 hover:bg-amber-600 text-white transition-colors">
                    <i class="bi bi-x-lg text-sm font-bold"></i>
                </button>
            </div>
            
            <div class="p-6 bg-slate-50">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-slate-500 uppercase font-bold border-b-2 border-slate-200">
                        <tr>
                            <th class="pb-2 w-16 text-center">Tiết</th>
                            <th class="pb-2 pl-4">Thời gian</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach($periodTimes as $k=>$v): ?>
                        <tr class="hover:bg-amber-50 transition-colors">
                            <td class="py-2.5 font-bold text-slate-700 text-center"><?= $k ?></td>
                            <td class="py-2.5 pl-4 font-semibold text-slate-600 bg-white rounded-r-lg my-1 shadow-[0_2px_4px_rgb(0,0,0,0.02)] inline-block w-full border border-slate-100"><?= $v ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="fixed bottom-0 left-0 right-0 bg-white/80 backdrop-blur-md border-t border-slate-200 py-2.5 px-4 flex items-center justify-center md:justify-end z-40 text-[11px] md:text-sm text-slate-500 font-medium shadow-[0_-4px_6px_-1px_rgb(0,0,0,0.05)]">
        <span>&copy; <?= date('Y') ?> Phát triển bởi</span>
        <a href="https://www.facebook.com/clbkynangdoan.ltk" target="_blank" class="text-primary-600 hover:text-primary-700 font-bold ml-1 hover:underline decoration-2 underline-offset-2">
            CLB Kỹ năng Đoàn - Hội
        </a>
    </footer>

    <style>
        [x-cloak] { display: none !important; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</body>
</html>
