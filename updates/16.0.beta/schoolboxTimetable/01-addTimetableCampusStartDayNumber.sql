SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*)
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE  table_name = 'timetable_campus'
            AND table_schema = DATABASE()
            AND column_name = 'start_day'
    ) > 0,
    "SELECT 'Column already exists. Skipping...'",
    "ALTER TABLE `timetable_campus` ADD COLUMN `start_day` INT DEFAULT 1  NOT NULL AFTER `offset`;"
));

PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;