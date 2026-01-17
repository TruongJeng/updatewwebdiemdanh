<?php
// Nếu cần phân quyền, có thể thêm session_start() và kiểm tra session ở đây
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Thông tin phần mềm</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f7f9fb; }
        .swinfo-container {
            background: #fff; max-width:800px; margin: 38px auto 24px auto;
            padding: 30px 22px 28px 22px; border-radius: 14px;
            box-shadow: 0 4px 24px #3178c615, 0 1.5px 8px #a8c8f088;
            border:1.5px solid #e53935;
        }
        .swinfo-title { color: #e53935; font-weight: 700; font-size: 1.18em; margin-bottom: 0;}
        .table th { width: 260px; }
        .table td, .table th { vertical-align: middle;}
        .icon-browser { font-size: 1.6em; margin-right: 8px;}
        @media (max-width:700px) {
            .swinfo-container {padding:10px 2vw;}
            .table th {width:130px;}
        }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="swinfo-container">
    <div class="swinfo-title py-2">Thông tin phần mềm</div>
    <table class="table table-bordered mb-0">
        <tr>
            <th>Tên</th>
            <td>Cổng thông tin sinh viên</td>
        </tr>
        <tr>
            <th>Mã</th>
            <td>003</td>
        </tr>
        <tr>
            <th>Đơn vị xử lý sự cố phần mềm</th>
            <td>Tổ phần mềm (Email: <a href="mailto:tophanmem@tdtu.edu.vn">tophanmem@tdtu.edu.vn</a>)</td>
        </tr>
        <tr>
            <th>Fan Page</th>
            <td><a href="https://www.facebook.com/tdtsoftware" target="_blank">https://www.facebook.com/tdtsoftware</a></td>
        </tr>
        <tr>
            <th>Đơn vị hỗ trợ thông tin</th>
            <td>Văn phòng tư vấn và hỗ trợ (E0001)</td>
        </tr>
        <tr>
            <th>Trình duyệt hỗ trợ tốt nhất</th>
            <td>
                <span class="icon-browser" title="Firefox">🦊</span>
                <span class="icon-browser" title="Chrome">🌐</span>
            </td>
        </tr>
    </table>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>