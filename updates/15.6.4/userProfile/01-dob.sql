SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*)
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE  table_name = 'user_profile'
            AND table_schema = DATABASE()
            AND column_name = 'dob'
    ) > 0,
    "SELECT 'Column already exists. Skipping...'",
    "ALTER TABLE `user_profile` ADD COLUMN `dob` DATE   NULL AFTER `user_id`;"
));

PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
