-- Infrastructure schema
-- Scope:
--   1. General Fund main table + history table
--   2. Special Education Fund main table + history table
-- Notes:
--   - This script intentionally keeps General Fund and SEF in separate tables.
--   - It does not create master tables yet (account codes, classifications, conditions).
--   - Department/admin foreign keys are omitted for safer adoption in legacy databases.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS general_fund_infrastructure (
    infra_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    infra_no VARCHAR(40) NOT NULL,
    department_code INT UNSIGNED NOT NULL,
    account_code VARCHAR(20) NOT NULL,
    classification VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    location_name VARCHAR(255) NOT NULL,
    barangay VARCHAR(120) DEFAULT NULL,
    date_acquired DATE DEFAULT NULL,
    year_acquired SMALLINT UNSIGNED DEFAULT NULL,
    amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    condition_status ENUM('SERVICEABLE','UNSERVICEABLE') NOT NULL DEFAULT 'SERVICEABLE',
    remarks TEXT DEFAULT NULL,
    record_status ENUM('ACTIVE','ARCHIVED','DISPOSED') NOT NULL DEFAULT 'ACTIVE',
    created_by INT UNSIGNED DEFAULT NULL,
    updated_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (infra_id),
    UNIQUE KEY uq_gf_infra_no (infra_no),
    KEY idx_gf_department_status (department_code, record_status),
    KEY idx_gf_account_code (account_code),
    KEY idx_gf_classification (classification),
    KEY idx_gf_condition_status (condition_status),
    KEY idx_gf_year_acquired (year_acquired),
    KEY idx_gf_location_name (location_name(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS general_fund_infrastructure_history (
    history_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    infra_id BIGINT UNSIGNED NOT NULL,
    reference_number VARCHAR(40) DEFAULT NULL,
    transaction_type ENUM(
        'REGISTERED',
        'UPDATED',
        'TRANSFERRED',
        'CONDITION_CHANGED',
        'ARCHIVED',
        'RESTORED',
        'DISPOSED'
    ) NOT NULL,
    transaction_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    effective_date DATE DEFAULT NULL,
    department_code INT UNSIGNED NOT NULL,
    previous_department_code INT UNSIGNED DEFAULT NULL,
    account_code VARCHAR(20) NOT NULL,
    classification VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    location_name VARCHAR(255) NOT NULL,
    previous_location_name VARCHAR(255) DEFAULT NULL,
    barangay VARCHAR(120) DEFAULT NULL,
    date_acquired DATE DEFAULT NULL,
    year_acquired SMALLINT UNSIGNED DEFAULT NULL,
    amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    condition_status ENUM('SERVICEABLE','UNSERVICEABLE') NOT NULL,
    remarks TEXT DEFAULT NULL,
    record_status ENUM('ACTIVE','ARCHIVED','DISPOSED') NOT NULL,
    change_reason VARCHAR(255) DEFAULT NULL,
    acted_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (history_id),
    KEY idx_gf_hist_infra_id_date (infra_id, transaction_date),
    KEY idx_gf_hist_reference_number (reference_number),
    KEY idx_gf_hist_transaction_type (transaction_type),
    KEY idx_gf_hist_department_code (department_code),
    KEY idx_gf_hist_condition_status (condition_status),
    KEY idx_gf_hist_record_status (record_status),
    CONSTRAINT fk_gf_infra_history_main
        FOREIGN KEY (infra_id) REFERENCES general_fund_infrastructure (infra_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS sef_infrastructure (
    infra_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    infra_no VARCHAR(40) NOT NULL,
    department_code INT UNSIGNED NOT NULL,
    account_code VARCHAR(20) NOT NULL,
    classification VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    location_name VARCHAR(255) NOT NULL,
    barangay VARCHAR(120) DEFAULT NULL,
    date_acquired DATE DEFAULT NULL,
    year_acquired SMALLINT UNSIGNED DEFAULT NULL,
    amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    condition_status ENUM('SERVICEABLE','UNSERVICEABLE') NOT NULL DEFAULT 'SERVICEABLE',
    remarks TEXT DEFAULT NULL,
    record_status ENUM('ACTIVE','ARCHIVED','DISPOSED') NOT NULL DEFAULT 'ACTIVE',
    created_by INT UNSIGNED DEFAULT NULL,
    updated_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (infra_id),
    UNIQUE KEY uq_sef_infra_no (infra_no),
    KEY idx_sef_department_status (department_code, record_status),
    KEY idx_sef_account_code (account_code),
    KEY idx_sef_classification (classification),
    KEY idx_sef_condition_status (condition_status),
    KEY idx_sef_year_acquired (year_acquired),
    KEY idx_sef_location_name (location_name(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS sef_infrastructure_history (
    history_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    infra_id BIGINT UNSIGNED NOT NULL,
    reference_number VARCHAR(40) DEFAULT NULL,
    transaction_type ENUM(
        'REGISTERED',
        'UPDATED',
        'TRANSFERRED',
        'CONDITION_CHANGED',
        'ARCHIVED',
        'RESTORED',
        'DISPOSED'
    ) NOT NULL,
    transaction_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    effective_date DATE DEFAULT NULL,
    department_code INT UNSIGNED NOT NULL,
    previous_department_code INT UNSIGNED DEFAULT NULL,
    account_code VARCHAR(20) NOT NULL,
    classification VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    location_name VARCHAR(255) NOT NULL,
    previous_location_name VARCHAR(255) DEFAULT NULL,
    barangay VARCHAR(120) DEFAULT NULL,
    date_acquired DATE DEFAULT NULL,
    year_acquired SMALLINT UNSIGNED DEFAULT NULL,
    amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    condition_status ENUM('SERVICEABLE','UNSERVICEABLE') NOT NULL,
    remarks TEXT DEFAULT NULL,
    record_status ENUM('ACTIVE','ARCHIVED','DISPOSED') NOT NULL,
    change_reason VARCHAR(255) DEFAULT NULL,
    acted_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (history_id),
    KEY idx_sef_hist_infra_id_date (infra_id, transaction_date),
    KEY idx_sef_hist_reference_number (reference_number),
    KEY idx_sef_hist_transaction_type (transaction_type),
    KEY idx_sef_hist_department_code (department_code),
    KEY idx_sef_hist_condition_status (condition_status),
    KEY idx_sef_hist_record_status (record_status),
    CONSTRAINT fk_sef_infra_history_main
        FOREIGN KEY (infra_id) REFERENCES sef_infrastructure (infra_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Suggested usage rules:
-- 1. Insert into the main table first.
-- 2. Immediately insert a REGISTERED row into the matching history table.
-- 3. On every update, keep the latest state in the main table and add a new history row.
-- 4. Prefer ARCHIVED / DISPOSED over hard deletes.