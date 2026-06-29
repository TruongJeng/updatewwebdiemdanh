<?php
// File cấu hình tập trung cho toàn bộ ứng dụng
return [
    'db' => [
        'host' => 'clbkynangdoanhoiltk.io.vn',
        'dbname' => 'bmkavqtl_clbkynang',
        'user' => 'bmkavqtl_truonggiang',
        'pass' => 'Giang15052006@',
    ],
    'mail' => [
        'host' => 'mail.clbkynangdoanhoiltk.io.vn',
        'username' => 'no-reply@clbkynangdoanhoiltk.io.vn',
        'password' => 'Giang15052006@', // Đổi mật khẩu này trên môi trường thực tế
        'port' => 465,
        'secure' => 'ssl',
        'from_email' => 'no-reply@clbkynangdoanhoiltk.io.vn',
        'from_name' => 'CLB Kỹ năng Đoàn - Hội',
    ]
];
