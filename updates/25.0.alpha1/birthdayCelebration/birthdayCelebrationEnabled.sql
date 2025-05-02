ALTER TABLE `lu_year_level`
    ADD COLUMN `birthday_celebration_enabled` tinyint(1) DEFAULT 0 AFTER `qrcode_enabled`;
