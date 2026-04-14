# submission_box
# Rename old INT column (preserves data for rollback).
ALTER TABLE `submission_box` CHANGE `mark_visibility` `_mark_visibility` tinyint(1) NOT NULL DEFAULT 0;

# Add new ENUM column with equivalent string default.
ALTER TABLE `submission_box` ADD COLUMN `mark_visibility` ENUM('staffOnly','staffAndStudent','staffAndParent','staffStudentAndParent') NOT NULL DEFAULT 'staffOnly';

# Populate new column from old values.
UPDATE `submission_box` SET `mark_visibility` = CASE `_mark_visibility`
    WHEN 0 THEN 'staffOnly'
    WHEN 1 THEN 'staffAndStudent'
    WHEN 2 THEN 'staffStudentAndParent'
    WHEN 3 THEN 'staffAndParent'
    ELSE 'staffOnly'
END;


# project
# Rename old INT column (preserves data for rollback).
ALTER TABLE `project` CHANGE `mark_visibility` `_mark_visibility` int(11) DEFAULT 1;

# Add new ENUM column with equivalent string default.
ALTER TABLE `project` ADD COLUMN `mark_visibility` ENUM('staffOnly','staffAndStudent','staffAndParent','staffStudentAndParent') DEFAULT 'staffAndStudent';

# Populate new column from old values.
UPDATE `project` SET `mark_visibility` = CASE `_mark_visibility`
    WHEN 0 THEN 'staffOnly'
    WHEN 1 THEN 'staffAndStudent'
    WHEN 2 THEN 'staffStudentAndParent'
    WHEN 3 THEN 'staffAndParent'
    ELSE 'staffAndStudent'
END;


# reporting
# Rename old INT column (preserves data for rollback).
ALTER TABLE `reporting` CHANGE `mark_visibility` `_mark_visibility` tinyint(4) DEFAULT NULL;

# Add new ENUM column with equivalent string default.
ALTER TABLE `reporting` ADD COLUMN `mark_visibility` ENUM('staffOnly','staffAndStudent','staffAndParent','staffStudentAndParent') DEFAULT NULL;

# Populate new column from old values.
UPDATE `reporting` SET `mark_visibility` = CASE `_mark_visibility`
    WHEN 0 THEN 'staffOnly'
    WHEN 1 THEN 'staffAndStudent'
    WHEN 2 THEN 'staffStudentAndParent'
    WHEN 3 THEN 'staffAndParent'
    ELSE NULL
END WHERE `_mark_visibility` IS NOT NULL;


# assess
# Rename old INT column (preserves data for rollback).
ALTER TABLE `assess` CHANGE `mark_visibility` `_mark_visibility` int(1) DEFAULT 1;

# Add new ENUM column with equivalent string default.
ALTER TABLE `assess` ADD COLUMN `mark_visibility` ENUM('staffOnly','staffAndStudent','staffAndParent','staffStudentAndParent') DEFAULT 'staffAndStudent';

# Populate new column from old values.
UPDATE `assess` SET `mark_visibility` = CASE `_mark_visibility`
    WHEN 0 THEN 'staffOnly'
    WHEN 1 THEN 'staffAndStudent'
    WHEN 2 THEN 'staffStudentAndParent'
    WHEN 3 THEN 'staffAndParent'
    ELSE 'staffAndStudent'
END;


# lti_assessment
# Rename old INT column (preserves data for rollback).
ALTER TABLE `lti_assessment` CHANGE `mark_visibility` `_mark_visibility` int(11) DEFAULT NULL;

# Add new ENUM column with equivalent string default.
ALTER TABLE `lti_assessment` ADD COLUMN `mark_visibility` ENUM('staffOnly','staffAndStudent','staffAndParent','staffStudentAndParent') DEFAULT NULL;

# Populate new column from old values.
UPDATE `lti_assessment` SET `mark_visibility` = CASE `_mark_visibility`
    WHEN 0 THEN 'staffOnly'
    WHEN 1 THEN 'staffAndStudent'
    WHEN 2 THEN 'staffStudentAndParent'
    WHEN 3 THEN 'staffAndParent'
    ELSE NULL
END WHERE `_mark_visibility` IS NOT NULL;
