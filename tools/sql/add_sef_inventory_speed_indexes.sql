-- Speeds up SEF inventory load/search for DataTables server-side processing.
-- Target DB: gsodbms
-- Engine: MariaDB/MySQL
--
-- NOTE:
-- - These ALTER TABLE operations can take time on large tables.
-- - Run during low-traffic hours if this is a production database.

START TRANSACTION;

-- 1) Critical: make institution/status lookups and joins fast.
-- Used by: WHERE sh.sch_id = ? AND sh.status = 1, then join on property_number and emp_id.
ALTER TABLE sef_property_history
  ADD INDEX idx_sef_history_sch_status_prop_emp (sch_id, status, property_number, emp_id);

-- 2) Helps ORDER BY item and DISTINCT item list.
ALTER TABLE property_sef
  ADD INDEX idx_property_sef_item_prop (item, property_number);

COMMIT;

-- Optional (only if you want faster keyword search on item/model/description):
-- FULLTEXT can greatly speed up searches, but requires testing in your environment.
-- ALTER TABLE property_sef ADD FULLTEXT INDEX ft_property_sef_text (item, model, description);
