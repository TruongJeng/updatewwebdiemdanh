<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../config/db.php';

// CHỈ ADMIN / CLUB LEADER
if (!in_array($_SESSION['role'], ['admin','club_leader'])) {
    die('Không có quyền');
}

// Composer autoload
require_once __DIR__ . '/../../PHPSpreadsheet/vendor/autoload.php';
$EVENT_NAME = 'TRẠI HUẤN LUYỆN LÝ THƯỜNG KIỆT 2026';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
// Lấy tất cả đội
$teams = $pdo->query("
    SELECT id, name
    FROM team_campers
    ORDER BY id ASC
")->fetchAll(PDO::FETCH_ASSOC);

if (!$teams) {
    die('Chưa có đội nào');
}
$spreadsheet = new Spreadsheet();
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

/* ======================
   SHEET TỔNG HỢP
====================== */

$summarySheet = $spreadsheet->getActiveSheet();
$summarySheet->setTitle('TỔNG HỢP');

/* ===== LOGO ===== */
$logoPath = __DIR__ . '/../../../assets/logo_CLB.png';
if (file_exists($logoPath)) {
    $drawing = new Drawing();
    $drawing->setName('Logo CLB');
    $drawing->setPath($logoPath);
    $drawing->setHeight(90);
    $drawing->setCoordinates('A1');
    $drawing->setWorksheet($summarySheet);
}

/* ===== HEADER TEXT ===== */
$summarySheet->mergeCells('B1:F2');
$summarySheet->setCellValue('B1', mb_strtoupper($EVENT_NAME));

$summarySheet->getStyle('B1')->getFont()
    ->setBold(true)
    ->setSize(16);

$summarySheet->getStyle('B1')->getAlignment()
    ->setVertical('center')
    ->setHorizontal('center');

/* ===== THỐNG KÊ ===== */
$totalTeams = count($teams);

// đếm tổng trại sinh
$stmt = $pdo->query("SELECT COUNT(*) FROM team_cam_member");
$totalMembers = (int)$stmt->fetchColumn();

$summarySheet->setCellValue('B4', 'Tổng số đội:');
$summarySheet->setCellValue('C4', $totalTeams);

$summarySheet->setCellValue('B5', 'Tổng số trại sinh:');
$summarySheet->setCellValue('C5', $totalMembers);

$summarySheet->getStyle('B4:B5')->getFont()->setBold(true);

/* ===== BẢNG DANH SÁCH ĐỘI ===== */
$summarySheet->setCellValue('A7', 'STT');
$summarySheet->setCellValue('B7', 'Tên đội');
$summarySheet->setCellValue('C7', 'Số thành viên');

$summarySheet->getStyle('A7:C7')->getFont()->setBold(true);
$summarySheet->getStyle('A7:C7')->getAlignment()->setHorizontal('center');

$row = 8;
foreach ($teams as $i => $team) {

    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM team_cam_member 
        WHERE team_id = ?
    ");
    $stmt->execute([$team['id']]);
    $count = (int)$stmt->fetchColumn();

    $summarySheet->setCellValue("A{$row}", $i + 1);
    $summarySheet->setCellValue("B{$row}", $team['name']);
    $summarySheet->setCellValue("C{$row}", $count);
    $row++;
}

/* Auto width */
foreach (['A','B','C','D','E','F'] as $col) {
    $summarySheet->getColumnDimension($col)->setAutoSize(true);
}

foreach ($teams as $teamIndex => $team) {

    // Lấy thành viên đội
    $stmt = $pdo->prepare("
        SELECT s.full_name, s.class
        FROM team_cam_member tm
        JOIN campers s ON tm.student_code = s.student_code
        WHERE tm.team_id = ?
        ORDER BY s.class DESC, s.full_name
    ");
    $stmt->execute([$team['id']]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tạo sheet
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle(mb_substr($team['name'], 0, 31)); // Excel max 31 ký tự
    /* ===== HEADER ĐỘI ===== */
    $sheet->mergeCells('A1:C1');
    $sheet->setCellValue('A1', mb_strtoupper($team['name']));

    $sheet->getStyle('A1')->getFont()
        ->setBold(true)
        ->setSize(14);

    $sheet->getStyle('A1')->getAlignment()
        ->setHorizontal('center');

    /* đẩy bảng xuống */
    $startRow = 3;

    $sheet->setCellValue("A{$startRow}", 'STT');
    $sheet->setCellValue("B{$startRow}", 'Họ và tên');
    $sheet->setCellValue("C{$startRow}", 'Lớp');

    // Header
    $sheet->setCellValue('A1', 'STT');
    $sheet->setCellValue('B1', 'Họ và tên');
    $sheet->setCellValue('C1', 'Lớp');

    $sheet->getStyle('A1:C1')->getFont()->setBold(true);

    // Data
    $row = 2;
    foreach ($members as $i => $m) {
        $sheet->setCellValue("A{$row}", $i + 1);
        $sheet->setCellValue("B{$row}", $m['full_name']);
        $sheet->setCellValue("C{$row}", $m['class']);
        $row++;
    }

    // Auto width
    foreach (['A','B','C'] as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
}
$filename = 'DanhSachChiaDoi_' . date('d-m-Y_H-i') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
