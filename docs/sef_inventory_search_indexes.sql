-- Optional (recommended) indexes to make SEF inventory DataTables search much faster.
-- Target: InnoDB tables, ~169k+ rows.
-- Run these in phpMyAdmin (SQL tab) or the MySQL CLI.
-- NOTE: Creating indexes can take time on large tables; do this during low-traffic hours.

-- 0) Quick checks (run first)
SELECT VERSION() AS mysql_version;
SHOW TABLE STATUS WHERE Name IN ('sef_property_history','property_sef','employee');

-- See existing indexes so you can skip duplicates before running ALTER TABLE.
SHOW INDEX FROM sef_property_history;
SHOW INDEX FROM property_sef;
SHOW INDEX FROM employee;

-- 1) Fast sch_id + status filtering and joins (history table)
-- Adjust column names if your schema differs.
-- If you already have an index covering (status, sch_id) you can skip idx_sef_hist_status_sch.
-- If property_number is already indexed/PK you can skip idx_sef_hist_property.
ALTER TABLE sef_property_history
  ADD INDEX idx_sef_hist_status_sch (status, sch_id),
  ADD INDEX idx_sef_hist_property (property_number),
  ADD INDEX idx_sef_hist_emp (emp_id);

-- 2) Fast prefix search for property/serial numbers (property_sef table)
-- If property_number is already PRIMARY KEY or indexed, skip idx_sef_property_number.
ALTER TABLE property_sef
  ADD INDEX idx_sef_property_number (property_number),
  ADD INDEX idx_sef_serial_1 (serial_number),
  ADD INDEX idx_sef_serial_2 (serial_number_2);

-- 3) Fast token-based search for item/model/description (optional but huge for text searches)
-- This requires InnoDB FULLTEXT support (MySQL 5.6+). If it fails, skip it.
-- Also: FULLTEXT indexes can be large; expect longer build time.
ALTER TABLE property_sef
  ADD FULLTEXT INDEX ft_sef_item_model_desc (item, model, description);

-- 4) End-user search speed (employee table)
-- If emp_name is already indexed, skip idx_employee_name.
ALTER TABLE employee
  ADD INDEX idx_employee_name (emp_name);

-- After adding indexes, it can help to update table stats:
ANALYZE TABLE sef_property_history, property_sef, employee;
