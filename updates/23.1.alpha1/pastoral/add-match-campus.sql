ALTER TABLE `pastoral_access`
    ADD COLUMN `match_campus` tinyint(1) DEFAULT 0 AFTER `access`;
ALTER TABLE `pastoral_audience`
    ADD COLUMN `match_campus` tinyint(1) DEFAULT 0 AFTER `access`;
