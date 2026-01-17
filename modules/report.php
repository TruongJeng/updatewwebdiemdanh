<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/PHPSpreadsheet/src/PhpSpreadsheet/autoload.php'; // Include autoload của PhpSpreadsheet

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Phân quyền: chỉ admin, giáo viên, club_leader mới được xem báo cáo
if (!in_array($_SESSION['role'], ['admin', 'teacher', 'club_leader'])) {
    header("Location: ../dashboard.php");
    exit("Bạn không có quyền truy cập chức năng này!");
}

// Lấy thông tin user
$full_name = $_SESSION['full_name'] ?? '';
if (!$full_name && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $full_name = $stmt->fetchColumn();
    $_SESSION['full_name'] = $full_name;
}

// Lấy danh sách sự kiện, lớp và học sinh để filter
$events = $pdo->query("SELECT id, title, event_date FROM events ORDER BY event_date DESC")->fetchAll();
$classes = $pdo->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL AND class != '' ORDER BY class")->fetchAll();
$students = $pdo->query("SELECT id, student_code, ho, ten, class FROM students ORDER BY class, student_code")->fetchAll();

// Xử lý filter
$event_id = $_GET['event_id'] ?? '';
$class = $_GET['class'] ?? '';
$student_id = $_GET['student_id'] ?? '';

$where = [];
$params = [];

if ($event_id) {
    $where[] = "a.event_id = ?";
    $params[] = $event_id;
}
if ($class) {
    $where[] = "s.class = ?";
    $params[] = $class;
}
if ($student_id) {
    $where[] = "s.id = ?";
    $params[] = $student_id;
}

$where_sql = $where ? 'WHERE '.implode(' AND ', $where) : '';

// Tổng hợp dữ liệu
$sql = "SELECT s.student_code, s.ho, s.ten, s.class, COUNT(a.id) as so_lan_diem_danh
        FROM students s
        LEFT JOIN attendance a ON s.id = a.student_id " . ($event_id ? "AND a.event_id = ?" : "") . "
        $where_sql
        GROUP BY s.id
        ORDER BY s.class, s.student_code";

$params_stat = $params;
if ($event_id) $params_stat[] = $event_id;

$stmt = $pdo->prepare($sql);
$stmt->execute($params_stat);
$stat = $stmt->fetchAll();

// Xuất file Excel nếu được yêu cầu
if (isset($_GET['export_csv'])) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle("Attendance Report");

    // Ghi tiêu đề cột
    $sheet->setCellValue('A1', 'Mã số');
    $sheet->setCellValue('B1', 'Họ và tên');
    $sheet->setCellValue('C1', 'Lớp');
    $sheet->setCellValue('D1', 'Số lần điểm danh');

    // Ghi dữ liệu vào các dòng
    $rowIndex = 2;
    foreach ($stat as $row) {
        $sheet->setCellValue('A' . $rowIndex, $row['student_code']);
        $sheet->setCellValue('B' . $rowIndex, trim($row['ho'] . ' ' . $row['ten']));
        $sheet->setCellValue('C' . $rowIndex, $row['class']);
        $sheet->setCellValue('D' . $rowIndex, $row['so_lan_diem_danh']);
        $rowIndex++;
    }

    // Xuất file Excel
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="attendance_report.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}
?>