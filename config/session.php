<?php
session_set_cookie_params([
    'path' => '/hethongdiemdanh/',
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();
