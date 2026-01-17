<?php
spl_autoload_register(function ($class) {
    $prefix = 'PhpOffice\\PhpSpreadsheet\\';
    $baseDir = __DIR__ . '/src/PhpSpreadsheet/'; // Đường dẫn tới thư mục PhpSpreadsheet

    // Kiểm tra nếu lớp thuộc namespace PhpSpreadsheet
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    // Lấy phần tên lớp tương ứng với file/thư mục
    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    // Include file nếu nó tồn tại
    if (file_exists($file)) {
        require $file;
    }
});