-- Performance indexes for admin/add-item.php "Recent Add Item" table
-- Adds composite indexes to speed up joining the active (status=1) history row.

ALTER TABLE general_fund_property_history
  ADD INDEX idx_gf_hist_par_status (par_number, status);

ALTER TABLE sef_property_history
  ADD INDEX idx_sef_hist_prop_status (property_number, status);
