<?php
session_set_cookie_params([
    'path' => '/hethongdiemdanh/',
    'httponly' => true,
    'secure' => true,
    'samesite' => 'Strict'
]);

session_start();

