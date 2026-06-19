# Rename old INT column (preserves data for rollback) and add new ENUM column.
ALTER TABLE `submission_box`
    CHANGE `mark_visibility` `_mark_visibility` tinyint(1) NOT NULL DEFAULT 0,
    ADD COLUMN `mark_visibility` ENUM('staffOnly','staffAndStudent','staffAndParent','staffStudentAndParent') NOT NULL DEFAULT 'staffOnly' AFTER `_mark_visibility`;

# Rename old INT column (preserves data for rollback) and add new ENUM column.
ALTER TABLE `project`
    CHANGE `mark_visibility` `_mark_visibility` int(11) DEFAULT 1,
    ADD COLUMN `mark_visibility` ENUM('staffOnly','staffAndStudent','staffAndParent','staffStudentAndParent') DEFAULT 'staffAndStudent' AFTER `_mark_visibility`;

# Rename old INT column (preserves data for rollback) and add new ENUM column.
ALTER TABLE `assess`
    CHANGE `mark_visibility` `_mark_visibility` int(1) DEFAULT 1,
    ADD COLUMN `mark_visibility` ENUM('staffOnly','staffAndStudent','staffAndParent','staffStudentAndParent') DEFAULT 'staffAndStudent' AFTER `_mark_visibility`;

# Rename old INT column (preserves data for rollback) and add new ENUM column.
ALTER TABLE `lti_assessment`
    CHANGE `mark_visibility` `_mark_visibility` int(11) DEFAULT NULL,
    ADD COLUMN `mark_visibility` ENUM('staffOnly','staffAndStudent','staffAndParent','staffStudentAndParent') DEFAULT NULL AFTER `_mark_visibility`;

# Note the 'ALTER TABLE' commands for reporting have been incorporated into the CREATE TABLE command in updates/26.1.alpha1/reporting/01-schema.sql.
