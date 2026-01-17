<?php
$file = $_GET['file'] ?? '';
$allowed_files = ['student.csv', 'students.csv'];
if (!in_array($file, $allowed_files)) die('Không được phép tải file này!');

$filepath = __DIR__ . '/' . $file;
if (!file_exists($filepath)) die('File không tồn tại!');

// Đặt header cho tiếng Việt và file CSV
header('Content-Description: File Transfer');
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.basename($filepath).'"; filename*=UTF-8\'\''.rawurlencode(basename($filepath)));
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filepath));

// Nếu muốn file CSV mở ra trong Excel không lỗi tiếng Việt, ghi BOM:
echo "\xEF\xBB\xBF";
// Đọc file
readfile($filepath);

// Quay lại trang trước
echo '<script>setTimeout(function(){ window.history.back(); }, 1500);</script>';
exit;
?>