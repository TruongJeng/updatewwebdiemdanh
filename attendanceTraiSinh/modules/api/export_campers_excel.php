<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','club_leader'])) {
    die('Không có quyền');
}

require_once __DIR__ . '/../../../PHPSpreadsheet/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// ==========================
// 1. LẤY DỮ LIỆU
// ==========================
$stmt = $pdo->prepare("
    SELECT
        c.student_code,
        c.full_name,
        c.class,
        IFNULL(tc.name, '--') AS team_name,
        c.phone
    FROM campers c
    LEFT JOIN team_cam_member tcm ON c.student_code = tcm.student_code
    LEFT JOIN team_campers tc ON tcm.team_id = tc.id
    WHERE c.is_active = 1
    ORDER BY c.class DESC, tc.name ASC, c.full_name ASC
");
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================
// 2. THÔNG TIN XUẤT
// ==========================
$exportedBy = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'BTC';
$exportTime = date('H:i:s d/m/Y');

// ==========================
// 3. TẠO FILE EXCEL
// ==========================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Danh sách trại sinh');

// ==========================
// 4. LOGO
// ==========================
$logo = new Drawing();
$logo->setName('Logo');
$logo->setDescription('Logo Trại');
$logo->setPath(__DIR__ . '/../../../assets/logo_CLB.png'); // chỉnh path nếu cần
$logo->setHeight(80);
$logo->setCoordinates('A1');
$logo->setOffsetX(10);
$logo->setOffsetY(5);
$logo->setWorksheet($sheet);

// ==========================
// 5. TIÊU ĐỀ CHÍNH (16)
// ==========================
$sheet->mergeCells('A3:E3');
$sheet->setCellValue('A3', 'DANH SÁCH TRẠI SINH');
$sheet->getStyle('A3')->applyFromArray([
    'font' => [
        'name' => 'Times New Roman',
        'size' => 16,
        'bold' => true
    ],
    'alignment' => [
        'horizontal' => 'center',
        'vertical'   => 'center'
    ]
]);
$sheet->getRowDimension(3)->setRowHeight(28);

// ==========================
// 6. TIÊU ĐỀ PHỤ (13)
// ==========================
$sheet->mergeCells('A4:E4');
$sheet->setCellValue('A4', 'TRẠI HUẤN LUYỆN LÝ THƯỜNG KIỆT 2026');
$sheet->getStyle('A4')->applyFromArray([
    'font' => [
        'name' => 'Times New Roman',
        'size' => 13,
        'italic' => true
    ],
    'alignment' => [
        'horizontal' => 'center'
    ]
]);

// ==========================
// 7. THÔNG TIN XUẤT (13)
// ==========================
$sheet->mergeCells('A5:E5');
$sheet->setCellValue(
    'A5',
    "Xuất lúc: {$exportTime}    |    Người xuất: {$exportedBy}"
);
$sheet->getStyle('A5')->applyFromArray([
    'font' => [
        'name' => 'Times New Roman',
        'size' => 13,
        'italic' => true
    ],
    'alignment' => [
        'horizontal' => 'right'
    ]
]);
$sheet->getRowDimension(5)->setRowHeight(22);

// ==========================
// 8. HEADER BẢNG (14)
// ==========================
$startRow = 6;

$sheet->fromArray(
    ['Mã trại sinh', 'Họ và tên', 'Lớp', 'Đội', 'Số điện thoại'],
    null,
    "A{$startRow}"
);

$sheet->getStyle("A{$startRow}:E{$startRow}")->applyFromArray([
    'font' => [
        'name' => 'Times New Roman',
        'size' => 14,
        'bold' => true
    ],
    'alignment' => [
        'horizontal' => 'center',
        'vertical'   => 'center'
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'E7F3FF']
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN
        ]
    ]
]);
$sheet->getRowDimension($startRow)->setRowHeight(26);

// ==========================
// 9. ĐỔ DỮ LIỆU (13)
// ==========================
$rowNum = $startRow + 1;

foreach ($data as $row) {
    $sheet->setCellValue("A{$rowNum}", $row['student_code']);
    $sheet->setCellValue("B{$rowNum}", $row['full_name']);
    $sheet->setCellValue("C{$rowNum}", $row['class']);
    $sheet->setCellValue("D{$rowNum}", $row['team_name']);
    $sheet->setCellValueExplicit(
        "E{$rowNum}",
        $row['phone'],
        DataType::TYPE_STRING
    );
    $rowNum++;
}

$lastRow = $rowNum - 1;

$sheet->getStyle("A".($startRow+1).":E{$lastRow}")->applyFromArray([
    'font' => [
        'name' => 'Times New Roman',
        'size' => 13
    ],
    'alignment' => [
        'vertical' => 'center'
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN
        ]
    ]
]);

// ==========================
// 10. TIỆN ÍCH
// ==========================
$sheet->freezePane("A".($startRow+1));
$sheet->setAutoFilter("A{$startRow}:E{$lastRow}");

foreach (range('A','E') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// ==========================
// 11. XUẤT FILE
// ==========================
$filename = 'Danh_sach_trai_sinh_' . date('d-m-Y_H-i') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
