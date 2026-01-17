<?php
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['timetable_file'])) {
    $file = $_FILES['timetable_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    // Chỉ cho phép Excel
    if (($ext == 'xlsx' || $ext == 'xls') && $file['size'] < 5*1024*1024) {
        $target = '../uploads/timetable.xlsx'; // Ghi đè file cũ
        if (move_uploaded_file($file['tmp_name'], $target)) {
            $msg = '<div class="alert alert-success mt-3">Tải file thành công!</div>';
        } else {
            $msg = '<div class="alert alert-danger mt-3">Lỗi khi lưu file lên server.</div>';
        }
    } else {
        $msg = '<div class="alert alert-warning mt-3">Chỉ chấp nhận file Excel (.xlsx, .xls) nhỏ hơn 5MB.</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Upload thời khóa biểu</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4" style="max-width:500px">
    <h4 class="mb-4">Upload file Excel thời khóa biểu</h4>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="timetable_file" accept=".xlsx,.xls" class="form-control mb-3" required>
        <button type="submit" class="btn btn-primary">Tải lên</button>
    </form>
    <?= $msg ?>
    <div class="mt-3 text-secondary">
        File sẽ được lưu vào thư mục <code>uploads/timetable.xlsx</code> và ghi đè nếu đã có file cũ.<br>
        Chỉ hỗ trợ file Excel dạng .xlsx hoặc .xls, dung lượng tối đa 5MB.<br>
        File mẫu: <b>TKB LOP</b> dạng bảng ngang từng lớp như bạn gửi.
    </div>
</div>
</body>
</html>