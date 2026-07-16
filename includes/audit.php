<?php
function log_admin_action($pdo, $user_id, $action, $details = '') {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $stmt = $pdo->prepare("INSERT INTO admin_audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $action, $details, $ip]);
    } catch (Exception $e) {
        // Ignore logging errors so it doesn't break the main app flow
        error_log("Audit Log Error: " . $e->getMessage());
    }
}
