<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../config/db.php';

/* ===== PHÂN QUYỀN ===== */
if (!in_array($_SESSION['role'], ['admin','club_leader'])) {
    die('Không có quyền');
}

/* ===== PHP SPREADSHEET ===== */
require_once __DIR__ . '/../../PHPSpreadsheet/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/* ===== CONFIG ===== */
$EVENT_NAME = 'TRẠI HUẤN LUYỆN LÝ THƯỜNG KIỆT NĂM 2026';
$logoPath   = __DIR__ . '/../../assets/logo_CLB.png';

/* ===== LẤY DANH SÁCH ĐỘI ===== */
$teams = $pdo->query("
    SELECT id, name
    FROM team_campers
    ORDER BY id ASC
")->fetchAll(PDO::FETCH_ASSOC);

if (!$teams) {
    die('Chưa có đội nào');
}

/* ===== TẠO FILE ===== */
$spreadsheet = new Spreadsheet();

/* ===== FONT MẶC ĐỊNH ===== */
$spreadsheet->getDefaultStyle()->getFont()
    ->setName('Times New Roman')
    ->setSize(14);

/* =====================================================
   SHEET TỔNG HỢP
===================================================== */
$summarySheet = $spreadsheet->getActiveSheet();
$summarySheet->setTitle('TỔNG HỢP');

/* LOGO TỔNG HỢP */
if (file_exists($logoPath)) {
    $drawing = new Drawing();
    $drawing->setName('Logo CLB');
    $drawing->setPath($logoPath);
    $drawing->setHeight(50);
    $drawing->setCoordinates('A1');
    $drawing->setOffsetX(5);
    $drawing->setOffsetY(5);
    $drawing->setWorksheet($summarySheet);
}

/* TIÊU ĐỀ */
$summarySheet->mergeCells('B1:F2');
$summarySheet->setCellValue('B1', mb_strtoupper($EVENT_NAME));

$summarySheet->getStyle('B1')->applyFromArray([
    'font' => [
        'bold' => true,
        'size' => 16,
        'name' => 'Times New Roman',
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER,
    ],
]);

/* THỐNG KÊ */
$totalTeams   = count($teams);
$totalMembers = (int)$pdo->query("SELECT COUNT(*) FROM team_cam_member")->fetchColumn();

$summarySheet->setCellValue('B4', 'Tổng số đội:');
$summarySheet->setCellValue('C4', $totalTeams);
$summarySheet->setCellValue('B5', 'Tổng số trại sinh:');
$summarySheet->setCellValue('C5', $totalMembers);

$summarySheet->getStyle('B4:B5')->getFont()->setBold(true);

/* HEADER BẢNG */
$summarySheet->setCellValue('A7', 'STT');
$summarySheet->setCellValue('B7', 'TÊN ĐỘI');
$summarySheet->setCellValue('C7', 'SỐ THÀNH VIÊN');

$summarySheet->getStyle('A7:C7')->applyFromArray([
    'font' => [
        'bold' => true,
        'size' => 14,
        'name' => 'Times New Roman',
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
        ],
    ],
]);

/* DATA */
$row = 8;
foreach ($teams as $i => $team) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM team_cam_member WHERE team_id = ?");
    $stmt->execute([$team['id']]);

    $summarySheet->setCellValue("A{$row}", $i + 1);
    $summarySheet->setCellValue("B{$row}", $team['name']);
    $summarySheet->setCellValue("C{$row}", (int)$stmt->fetchColumn());
    $row++;
}

/* STYLE DATA */
$lastRow = $row - 1;
$summarySheet->getStyle("A7:C{$lastRow}")->applyFromArray([
    'font' => [
        'size' => 14,
        'name' => 'Times New Roman',
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
        ],
    ],
]);
$summarySheet->getStyle("A8:A{$lastRow}")
    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

foreach (['A','B','C','D','E','F'] as $col) {
    $summarySheet->getColumnDimension($col)->setAutoSize(true);
}

/* =====================================================
   SHEET CHI TIẾT TỪNG ĐỘI (CÓ LOGO)
===================================================== */
foreach ($teams as $idx => $team) {

    $stmt = $pdo->prepare("
        SELECT s.full_name, s.class
        FROM team_cam_member tm
        JOIN campers s ON tm.student_code = s.student_code
        WHERE tm.team_id = ?
        ORDER BY s.class DESC, s.full_name
    ");
    $stmt->execute([$team['id']]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sheet = $spreadsheet->createSheet();

    /* LOGO ĐỘI */
    if (file_exists($logoPath)) {
        $logo = new Drawing();
        $logo->setName('Logo CLB');
        $logo->setPath($logoPath);
        $logo->setHeight(55);
        $logo->setCoordinates('A1');
        $logo->setOffsetX(5);
        $logo->setOffsetY(5);
        $logo->setWorksheet($sheet);
    }

    /* TÊN SHEET */
    $safeName = preg_replace('/[\[\]\*\/\\\\\?\:]/', '', $team['name']);
    $sheet->setTitle(mb_substr($safeName, 0, 28) . '_' . ($idx + 1));

    /* TIÊU ĐỀ ĐỘI */
    $sheet->mergeCells('B1:D1');
    $sheet->setCellValue('B1', mb_strtoupper($team['name']));
    $sheet->getStyle('B1')->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 16,
            'name' => 'Times New Roman',
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical'   => Alignment::VERTICAL_CENTER,
        ],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(60);

    /* HEADER */
    $headerRow = 3;
    $sheet->setCellValue("A{$headerRow}", 'STT');
    $sheet->setCellValue("B{$headerRow}", 'HỌ VÀ TÊN');
    $sheet->setCellValue("C{$headerRow}", 'LỚP');

    $sheet->getStyle("A{$headerRow}:C{$headerRow}")->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 14,
            'name' => 'Times New Roman',
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical'   => Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
            ],
        ],
    ]);

    /* DATA */
    $row = $headerRow + 1;
    foreach ($members as $i => $m) {
        $sheet->setCellValue("A{$row}", $i + 1);
        $sheet->setCellValue("B{$row}", $m['full_name']);
        $sheet->setCellValue("C{$row}", $m['class']);
        $row++;
    }

    $sheet->getStyle("A".($headerRow+1).":C".($row-1))->applyFromArray([
        'font' => [
            'size' => 14,
            'name' => 'Times New Roman',
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
            ],
        ],
    ]);
    $sheet->getStyle("A".($headerRow+1).":A".($row-1))
        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    foreach (['A','B','C'] as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    /* IN A4 */
    $sheet->getPageSetup()
        ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
        ->setPaperSize(PageSetup::PAPERSIZE_A4)
        ->setFitToWidth(1)
        ->setFitToHeight(1);
}

/* ===== XUẤT FILE ===== */
$filename = 'DanhSachChiaDoi_' . date('d-m-Y_H-i') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
