-- Land property schema
-- Scope:
--   1. Land property registry main table
--   2. Land property change history table
-- Notes:
--   - This script matches the fields used by admin/add-land.php and auth/auth.php.
--   - Department/admin foreign keys are omitted for safer adoption in legacy databases.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS land_properties (
    land_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    property_code VARCHAR(40) NOT NULL,
    fund_cluster VARCHAR(50) NOT NULL,
    classification VARCHAR(150) NOT NULL,
    declared_owner VARCHAR(255) NOT NULL,
    tct_no VARCHAR(120) NOT NULL,
    area_sqm DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    project_name VARCHAR(255) DEFAULT NULL,
    address TEXT NOT NULL,
    barangay VARCHAR(120) NOT NULL,
    acquisition_cost DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    documentary_stamp_tax DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    capital_gains_tax DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    other_incidental_transfer_fees DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    date_acquired VARCHAR(20) NOT NULL,
    has_original_tct VARCHAR(40) NOT NULL,
    tax_declaration_no VARCHAR(120) DEFAULT NULL,
    has_doas VARCHAR(40) NOT NULL,
    has_dod VARCHAR(40) NOT NULL,
    other_supporting_documents TEXT DEFAULT NULL,
    transfer_status VARCHAR(40) NOT NULL,
    current_status VARCHAR(80) NOT NULL,
    remarks TEXT DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    updated_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (land_id),
    UNIQUE KEY uq_land_property_code (property_code),
    KEY idx_land_fund_cluster (fund_cluster),
    KEY idx_land_classification (classification),
    KEY idx_land_barangay (barangay),
    KEY idx_land_current_status (current_status),
    KEY idx_land_date_acquired (date_acquired)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS land_property_history (
    history_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    land_id INT UNSIGNED NOT NULL,
    reference_number VARCHAR(40) DEFAULT NULL,
    event_type ENUM('REGISTERED','UPDATED') NOT NULL,
    action_date DATE NOT NULL,
    fund_cluster VARCHAR(50) NOT NULL,
    classification VARCHAR(150) NOT NULL,
    declared_owner VARCHAR(255) NOT NULL,
    tct_no VARCHAR(120) NOT NULL,
    area_sqm DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    project_name VARCHAR(255) DEFAULT NULL,
    address TEXT NOT NULL,
    barangay VARCHAR(120) NOT NULL,
    acquisition_cost DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    documentary_stamp_tax DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    capital_gains_tax DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    other_incidental_transfer_fees DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    date_acquired VARCHAR(20) NOT NULL,
    has_original_tct VARCHAR(40) NOT NULL,
    tax_declaration_no VARCHAR(120) DEFAULT NULL,
    has_doas VARCHAR(40) NOT NULL,
    has_dod VARCHAR(40) NOT NULL,
    other_supporting_documents TEXT DEFAULT NULL,
    transfer_status VARCHAR(40) NOT NULL,
    current_status VARCHAR(80) NOT NULL,
    remarks TEXT DEFAULT NULL,
    acted_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (history_id),
    KEY idx_land_hist_land_id_date (land_id, action_date),
    KEY idx_land_hist_reference_number (reference_number),
    KEY idx_land_hist_event_type (event_type),
    CONSTRAINT fk_land_history_property
        FOREIGN KEY (land_id) REFERENCES land_properties (land_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;