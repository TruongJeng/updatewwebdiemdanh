<?php
/**
 * CSRF Token Helper
 * Include file này sau session.php để sử dụng CSRF protection.
 *
 * Cách dùng:
 *   - Trong form:  <?= csrf_field() ?>
 *   - Kiểm tra:    verify_csrf() hoặc verify_csrf_json()
 */

/**
 * Tạo hoặc lấy CSRF token hiện tại
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Tạo hidden input chứa CSRF token (dùng trong form HTML)
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Kiểm tra CSRF token từ POST request (form submit)
 * Redirect về referer hoặc trang chủ nếu không hợp lệ.
 */
function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Yêu cầu không hợp lệ (CSRF). Vui lòng tải lại trang và thử lại.');
    }
}

/**
 * Kiểm tra CSRF token từ JSON request (AJAX/API)
 * Trả JSON error nếu không hợp lệ.
 */
function verify_csrf_json(): void {
    // Hỗ trợ cả POST form-data và JSON body
    $token = $_POST['csrf_token'] ?? '';
    if (!$token) {
        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['csrf_token'] ?? $input['csrf'] ?? '';
    }
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}
