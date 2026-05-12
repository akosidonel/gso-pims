-- Dashboard + notifications performance indexes
-- Run this in MySQL (phpMyAdmin SQL tab or mysql CLI) on database `gsodbms`.
-- If an index name already exists, skip that line or rename the index.

USE `gsodbms`;

-- Speeds up unread notifications queries (WHERE is_read=0 ORDER BY created_at DESC)
ALTER TABLE `clearance_history`
  ADD INDEX `idx_clearance_history_is_read_created` (`is_read`, `created_at`);

-- Speeds up admin/dashboard.php approved clearance preview
-- (WHERE status=1 ORDER BY created_at DESC LIMIT 4)
ALTER TABLE `clearance_history`
  ADD INDEX `idx_clearance_history_status_created` (`status`, `created_at`);

-- If your clearance_history joins on emp_id / ctype_id frequently
ALTER TABLE `clearance_history`
  ADD INDEX `idx_clearance_history_emp_id` (`emp_id`),
  ADD INDEX `idx_clearance_history_ctype_id` (`ctype_id`);

-- Speeds up auth/fetch_dashboard_metrics.php equipment counts
ALTER TABLE `par_gen_fund`
  ADD INDEX `idx_par_gen_fund_item_par` (`item`, `par_number`);

ALTER TABLE `general_fund_property_history`
  ADD INDEX `idx_gf_hist_par_status` (`par_number`, `status`);

ALTER TABLE `property_sef`
  ADD INDEX `idx_property_sef_item_prop` (`item`, `property_number`);

ALTER TABLE `sef_property_history`
  ADD INDEX `idx_sef_hist_prop_status` (`property_number`, `status`);

ANALYZE TABLE `clearance_history`, `par_gen_fund`, `general_fund_property_history`, `property_sef`, `sef_property_history`;
