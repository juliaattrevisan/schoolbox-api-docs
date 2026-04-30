# Populate new column from old values.
# The old column was not nullable, so in case of bad data we must choose a value.  Chose 'staffOnly' for safety as that is most restrictive.
UPDATE `submission_box` SET `mark_visibility` = CASE `_mark_visibility`
    WHEN 0 THEN 'staffOnly'
    WHEN 1 THEN 'staffAndStudent'
    WHEN 2 THEN 'staffStudentAndParent'
    ELSE 'staffOnly'
END;

# Populate new column from old values.
# The old column was not nullable, so in case of bad data we must choose a value.  Chose 'staffOnly' for safety as that is most restrictive.
UPDATE `project` SET `mark_visibility` = CASE `_mark_visibility`
    WHEN 0 THEN 'staffOnly'
    WHEN 1 THEN 'staffAndStudent'
    WHEN 2 THEN 'staffStudentAndParent'
    ELSE 'staffOnly'
END;

# Populate new column from old values.
# The old column was not nullable, so in case of bad data we must choose a value.  Chose 'staffOnly' for safety as that is most restrictive.
UPDATE `assess` SET `mark_visibility` = CASE `_mark_visibility`
    WHEN 0 THEN 'staffOnly'
    WHEN 1 THEN 'staffAndStudent'
    WHEN 2 THEN 'staffStudentAndParent'
    ELSE 'staffOnly'
END;

# Populate new column from old values.
# The old column was nullable, so in case of bad data we leave as null.
UPDATE `lti_assessment` SET `mark_visibility` = CASE `_mark_visibility`
    WHEN 0 THEN 'staffOnly'
    WHEN 1 THEN 'staffAndStudent'
    WHEN 2 THEN 'staffStudentAndParent'
    ELSE NULL
END WHERE `_mark_visibility` IS NOT NULL;

# No UPDATE command is required for the reporting table, because since it is created in this version, it will not contain any old data to update.

