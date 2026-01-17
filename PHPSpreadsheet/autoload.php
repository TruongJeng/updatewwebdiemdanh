<?php
spl_autoload_register(function ($class) {
    $prefix = 'PhpOffice\\PhpSpreadsheet\\';
    $baseDir = __DIR__ . '/'; // Trỏ tới thư mục chính 'PhpSpreadsheet'

    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
    require $file;
    echo "Loaded file: $file\n";
    } else {
        die("Không tìm thấy file: $file\n");
    }
});