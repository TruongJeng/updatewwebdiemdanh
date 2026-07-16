<?php
require 'includes/db.php';

try {
    $pdo->exec("ALTER TABLE attendance_sessions ADD COLUMN lat VARCHAR(50) NULL DEFAULT NULL AFTER pin_code");
    $pdo->exec("ALTER TABLE attendance_sessions ADD COLUMN lng VARCHAR(50) NULL DEFAULT NULL AFTER lat");
    $pdo->exec("ALTER TABLE attendance_sessions ADD COLUMN radius INT NULL DEFAULT 50 AFTER lng");
    echo "Added columns to attendance_sessions.\n";
} catch (Exception $e) {
    echo "Error adding columns: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        details TEXT,
        ip_address VARCHAR(50),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Created admin_audit_logs table.\n";
} catch (Exception $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
