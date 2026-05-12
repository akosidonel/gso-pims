-- SEF inventory performance indexes
-- Run this in MySQL (phpMyAdmin SQL tab or mysql CLI) on database `gsodbms`.
-- These indexes speed up `admin/sef-inventory.php` server-side DataTables and filters.
-- If an index name already exists in your DB, either skip that line or rename the index.

USE `gsodbms`;

-- History table: most queries filter by sch_id + status, then join on property_number / emp_id
ALTER TABLE `sef_property_history`
  ADD INDEX `idx_sef_hist_sch_status` (`sch_id`, `status`),
  ADD INDEX `idx_sef_hist_sch_status_prop` (`sch_id`, `status`, `property_number`),
  ADD INDEX `idx_sef_hist_sch_status_emp` (`sch_id`, `status`, `emp_id`);

-- Property table: joined by property_number; filtered/ordered by item
ALTER TABLE `property_sef`
  ADD INDEX `idx_property_sef_item` (`item`);

-- Employee table: joined by emp_id; filtered by emp_name exact match and ordered in filters
ALTER TABLE `employee`
  ADD INDEX `idx_employee_emp_name` (`emp_name`);

-- Optional: update optimizer statistics
ANALYZE TABLE `sef_property_history`, `property_sef`, `employee`;
