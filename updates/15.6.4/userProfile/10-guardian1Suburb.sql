SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*)
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE  table_name = 'user_profile'
            AND table_schema = DATABASE()
            AND column_name = 'guardian1Suburb'
    ) > 0,
    "SELECT 'Column already exists. Skipping...'",
    "ALTER TABLE `user_profile` ADD COLUMN `guardian1Suburb` VARCHAR(255)  COLLATE utf8_general_ci NULL AFTER `guardian1Address`;"
));

PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;


