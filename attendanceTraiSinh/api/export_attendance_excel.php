<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../../PHPSpreadsheet/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','club_leader','staff'])) {
    die('Không có quyền');
}

/* ===== QUERY DỮ LIỆU ===== */
$stmt = $pdo->prepare("
SELECT
    s.student_code,
    s.full_name,
    s.class,
    l.type,
    DATE_FORMAT(l.scan_time,'%H:%i:%s %d/%m/%Y') AS scan_time,
    IFNULL(u.full_name, 'Tự điểm danh') AS btc,
    asess.pin_code,
    IFNULL(ev.title, '') AS event_name
FROM attendance_logs l
JOIN campers s ON l.student_id = s.id
LEFT JOIN users u ON l.scanned_by = u.id
JOIN attendance_sessions asess ON l.session_id = asess.id
LEFT JOIN ts_events ev ON asess.event_id = ev.id
ORDER BY l.scan_time ASC
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ===== TẠO FILE EXCEL ===== */
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Report Check-in Check-out');

/* HEADER */
$headers = [
    'A1' => 'STT',
    'B1' => 'Sự kiện',
    'C1' => 'Mã trại sinh',
    'D1' => 'Họ tên',
    'E1' => 'Lớp',
    'F1' => 'Trạng thái',
    'G1' => 'Thời gian',
    'H1' => 'Ban tổ chức',
    'I1' => 'PIN'
];

foreach ($headers as $cell => $text) {
    $sheet->setCellValue($cell, $text);
    $sheet->getStyle($cell)->getFont()->setBold(true);
}

/* DATA */
$rowIndex = 2;
$stt = 1;

foreach ($rows as $r) {
    $sheet->setCellValue("A$rowIndex", $stt++);
    $sheet->setCellValue("B$rowIndex", $r['event_name']);
    $sheet->setCellValue("C$rowIndex", $r['student_code']);
    $sheet->setCellValue("D$rowIndex", $r['full_name']);
    $sheet->setCellValue("E$rowIndex", $r['class']);
    $sheet->setCellValue(
        "F$rowIndex",
        $r['type'] === 'CHECK_IN' ? 'Đã Check in' : 'Đã Check out'
    );
    $sheet->setCellValue("G$rowIndex", $r['scan_time']);
    $sheet->setCellValue("H$rowIndex", $r['btc']);
    $sheet->setCellValue("I$rowIndex", $r['pin_code']);

    $rowIndex++;
}

/* AUTO WIDTH */
foreach (range('A','I') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

/* OUTPUT */
$fileName = 'BaoCaoDiemDanh_' . date('d-m-Y_H-i') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$fileName\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
