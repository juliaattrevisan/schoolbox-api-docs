ALTER TABLE `pastoral_access`
    ADD COLUMN `student_campus_only` tinyint(1) DEFAULT 0 AFTER `access`;
ALTER TABLE `pastoral_audience`
    ADD COLUMN `student_campus_only` tinyint(1) DEFAULT 0 AFTER `access`;
