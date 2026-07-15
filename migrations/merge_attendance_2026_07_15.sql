-- =============================================
-- Migration: Gộp hệ thống điểm danh
-- Ngày: 15/07/2026
-- =============================================

-- 1. Tạo bảng ts_events (sự kiện cho hệ thống trại sinh)
CREATE TABLE IF NOT EXISTS ts_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    event_date DATETIME DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ts_events_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Thêm cột event_id vào attendance_sessions
ALTER TABLE attendance_sessions
ADD COLUMN event_id INT DEFAULT NULL AFTER id,
ADD CONSTRAINT fk_sessions_event FOREIGN KEY (event_id) REFERENCES ts_events(id) ON DELETE SET NULL;

-- 3. Thêm cột GPS/IP vào attendance_logs (cho HS tự điểm danh)
ALTER TABLE attendance_logs
ADD COLUMN lat DECIMAL(10,7) DEFAULT NULL,
ADD COLUMN lng DECIMAL(10,7) DEFAULT NULL,
ADD COLUMN gps_time DATETIME DEFAULT NULL,
ADD COLUMN gps_source VARCHAR(50) DEFAULT NULL,
ADD COLUMN ip_addr VARCHAR(45) DEFAULT NULL;
