<?php
require_once __DIR__ . '/PHPSpreadsheet/src/PhpSpreadsheet/autoload.php'; // Đường dẫn autoload chính xác

use PhpOffice\PhpSpreadsheet\Spreadsheet;

$spreadsheet = new Spreadsheet();
echo "Lớp Spreadsheet hoạt động thành công!";