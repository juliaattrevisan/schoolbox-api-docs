-- Part 1
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*)
     FROM INFORMATION_SCHEMA.TABLES
     WHERE `TABLE_NAME` = 'submission_box_edumate_task'
           AND `TABLE_SCHEMA` = DATABASE()
    ) > 0,
    "ALTER TABLE submission_box_edumate_task RENAME TO submission_box_synergetic_task;",
    "SELECT 'Table does not exist. Skipping...'"

));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Part 2
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*)
     FROM INFORMATION_SCHEMA.TABLES
     WHERE `TABLE_NAME` = 'submission_return_edumate_task'
           AND `TABLE_SCHEMA` = DATABASE()
    ) > 0,
    "ALTER TABLE submission_return_edumate_task RENAME TO submission_return_synergetic_task;
    INSERT INTO lu_submission_type (name) VALUES ('Task from Synergetic');",
    "SELECT 'Table does not exist. Skipping...'"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
