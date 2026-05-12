-- General Fund inventory performance indexes
-- Run this in MySQL (phpMyAdmin SQL tab or mysql CLI) on database `gsodbms`.
-- If an index name already exists, skip that line or rename the index.

USE `gsodbms`;

-- History table: filter by dept_id + status, then join by par_number / emp_id
ALTER TABLE `general_fund_property_history`
  ADD INDEX `idx_gf_hist_dept_status` (`dept_id`, `status`),
  ADD INDEX `idx_gf_hist_dept_status_par` (`dept_id`, `status`, `par_number`),
  ADD INDEX `idx_gf_hist_dept_status_emp` (`dept_id`, `status`, `emp_id`);

-- Property table: join by par_number; order/filter by item
ALTER TABLE `par_gen_fund`
  ADD INDEX `idx_par_gen_fund_par_number` (`par_number`),
  ADD INDEX `idx_par_gen_fund_item` (`item`);

-- Employee: filter/order by emp_name
ALTER TABLE `employee`
  ADD INDEX `idx_employee_emp_name` (`emp_name`);

ANALYZE TABLE `general_fund_property_history`, `par_gen_fund`, `employee`;
