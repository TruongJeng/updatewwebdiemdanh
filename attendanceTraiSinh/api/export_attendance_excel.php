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
    u.full_name AS btc,
    asess.pin_code
FROM attendance_logs l
JOIN campers s ON l.student_id = s.id
JOIN users u ON l.scanned_by = u.id
JOIN attendance_sessions asess ON l.session_id = asess.id
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
    'B1' => 'Mã trại sinh',
    'C1' => 'Họ tên',
    'D1' => 'Lớp',
    'E1' => 'Trạng thái',
    'F1' => 'Thời gian',
    'G1' => 'Ban tổ chức',
    'H1' => 'PIN'
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
    $sheet->setCellValue("B$rowIndex", $r['student_code']);
    $sheet->setCellValue("C$rowIndex", $r['full_name']);
    $sheet->setCellValue("D$rowIndex", $r['class']);
    $sheet->setCellValue(
        "E$rowIndex",
        $r['type'] === 'CHECK_IN' ? 'Đã Check in' : 'Đã Check out'
    );
    $sheet->setCellValue("F$rowIndex", $r['scan_time']);
    $sheet->setCellValue("G$rowIndex", $r['btc']);
    $sheet->setCellValue("H$rowIndex", $r['pin_code']);

    $rowIndex++;
}

/* AUTO WIDTH */
foreach (range('A','H') as $col) {
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
