UPDATE `event`
SET title = ''
WHERE title IS NULL;

UPDATE `event`
SET location = ''
WHERE location IS NULL;

UPDATE `event`
SET detail = ''
WHERE detail IS NULL;

ALTER TABLE `event`
    CHANGE COLUMN `title` `title` VARCHAR(250) CHARACTER SET 'utf8mb4' NOT NULL DEFAULT '' ,
    CHANGE COLUMN `detail` `detail` TEXT CHARACTER SET 'utf8mb4' NOT NULL,
    CHANGE COLUMN `location` `location` VARCHAR(250) CHARACTER SET 'utf8mb4' NOT NULL DEFAULT '' ;

