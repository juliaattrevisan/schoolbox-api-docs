# submission_box
# Rename old INT column (preserves data for rollback).
ALTER TABLE `submission_box` CHANGE `mark_visibility` `_mark_visibility` tinyint(1) NOT NULL DEFAULT 0;

# Add new ENUM column with equivalent string default.
ALTER TABLE `submission_box` ADD COLUMN `mark_visibility` ENUM('staffOnly','staffAndStudent','staffAndParent','staffStudentAndParent') NOT NULL DEFAULT 'staffOnly';

# Populate new column from old values.
# The old column was not nullable, so in case of bad data we must choose a value.  Chose 'staffOnly' for safety as that is most restrictive.
UPDATE `submission_box` SET `mark_visibility` = CASE `_mark_visibility`
    WHEN 0 THEN 'staffOnly'
    WHEN 1 THEN 'staffAndStudent'
    WHEN 2 THEN 'staffStudentAndParent'
    ELSE 'staffOnly'
END;


# project
# Rename old INT column (preserves data for rollback).
ALTER TABLE `project` CHANGE `mark_visibility` `_mark_visibility` int(11) DEFAULT 1;

# Add new ENUM column with equivalent string default.
ALTER TABLE `project` ADD COLUMN `mark_visibility` ENUM('staffOnly','staffAndStudent','staffAndParent','staffStudentAndParent') DEFAULT 'staffAndStudent';

# Populate new column from old values.
# The old column was not nullable, so in case of bad data we must choose a value.  Chose 'staffOnly' for safety as that is most restrictive.
UPDATE `project` SET `mark_visibility` = CASE `_mark_visibility`
    WHEN 0 THEN 'staffOnly'
    WHEN 1 THEN 'staffAndStudent'
    WHEN 2 THEN 'staffStudentAndParent'
    ELSE 'staffOnly'
END;


# reporting
# Note the 'ALTER TABLE' commands for reporting have been incorporated into the CREATE TABLE command in updates/26.1.alpha1/reporting/01-schema.sql.
# Populate new column from old values.
# The old column was nullable, so in case of bad data we leave as null.
UPDATE `reporting` SET `mark_visibility` = CASE `_mark_visibility`
    WHEN 0 THEN 'staffOnly'
    WHEN 1 THEN 'staffAndStudent'
    WHEN 2 THEN 'staffStudentAndParent'
    ELSE NULL
END WHERE `_mark_visibility` IS NOT NULL;


# assess
# Rename old INT column (preserves data for rollback).
ALTER TABLE `assess` CHANGE `mark_visibility` `_mark_visibility` int(1) DEFAULT 1;

# Add new ENUM column with equivalent string default.
ALTER TABLE `assess` ADD COLUMN `mark_visibility` ENUM('staffOnly','staffAndStudent','staffAndParent','staffStudentAndParent') DEFAULT 'staffAndStudent';

# Populate new column from old values.
# The old column was not nullable, so in case of bad data we must choose a value.  Chose 'staffOnly' for safety as that is most restrictive.
UPDATE `assess` SET `mark_visibility` = CASE `_mark_visibility`
    WHEN 0 THEN 'staffOnly'
    WHEN 1 THEN 'staffAndStudent'
    WHEN 2 THEN 'staffStudentAndParent'
    ELSE 'staffOnly'
END;


# lti_assessment
# Rename old INT column (preserves data for rollback).
ALTER TABLE `lti_assessment` CHANGE `mark_visibility` `_mark_visibility` int(11) DEFAULT NULL;

# Add new ENUM column with equivalent string default.
ALTER TABLE `lti_assessment` ADD COLUMN `mark_visibility` ENUM('staffOnly','staffAndStudent','staffAndParent','staffStudentAndParent') DEFAULT NULL;

# Populate new column from old values.
# The old column was nullable, so in case of bad data we leave as null.
UPDATE `lti_assessment` SET `mark_visibility` = CASE `_mark_visibility`
    WHEN 0 THEN 'staffOnly'
    WHEN 1 THEN 'staffAndStudent'
    WHEN 2 THEN 'staffStudentAndParent'
    ELSE NULL
END WHERE `_mark_visibility` IS NOT NULL;
