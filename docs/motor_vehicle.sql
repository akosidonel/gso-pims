-- Motor vehicle schema
-- Scope:
--   1. Motor vehicle registry table

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS motor_vehicle (
    motor_vehicle_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    chassis_no VARCHAR(120) NOT NULL,
    engine_no VARCHAR(120) NOT NULL,
    plate_no VARCHAR(40) NOT NULL,
    color VARCHAR(80) NOT NULL,
    mv_file VARCHAR(120) NOT NULL,
    vehicle_usage VARCHAR(120) NOT NULL,
    capacity VARCHAR(80) NOT NULL,
    year_model SMALLINT UNSIGNED NOT NULL,
    coverage ENUM('None','TPL','Comprehensive') NOT NULL DEFAULT 'None',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (motor_vehicle_id),
    UNIQUE KEY uq_motor_vehicle_chassis_no (chassis_no),
    UNIQUE KEY uq_motor_vehicle_engine_no (engine_no),
    UNIQUE KEY uq_motor_vehicle_plate_no (plate_no),
    KEY idx_motor_vehicle_mv_file (mv_file),
    KEY idx_motor_vehicle_year_model (year_model),
    KEY idx_motor_vehicle_coverage (coverage)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
