-- Optional: FULLTEXT index for near-instant global search on SEF inventory.
-- Target DB: gsodbms
-- Engine: InnoDB (supported on MariaDB 10.4)
--
-- Notes:
-- - FULLTEXT search behavior differs from LIKE '%term%': it is token-based.
-- - Short tokens may be ignored depending on server settings (ft_min_word_len / innodb_ft_min_token_size).
-- - The app code includes a fallback to LIKE for short/numeric searches.

ALTER TABLE property_sef
  ADD FULLTEXT INDEX ft_property_sef_text (item, model, description);
