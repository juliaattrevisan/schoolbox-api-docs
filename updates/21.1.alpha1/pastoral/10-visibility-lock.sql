ALTER TABLE `lu_pastoral_type`
    ADD COLUMN visibility_locked TINYINT(1) DEFAULT 0 AFTER external_code;
ALTER TABLE `lu_pastoral_subtype`
    ADD COLUMN visibility_locked TINYINT(1) DEFAULT 0 AFTER external_code;
