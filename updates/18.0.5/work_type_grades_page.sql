DELIMITER $$

DROP PROCEDURE IF EXISTS upgrade_add_column $$

CREATE PROCEDURE upgrade_add_column()
BEGIN

IF NOT EXISTS (
  ( SELECT * FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND COLUMN_NAME = 'show_on_grades_page'
    AND TABLE_NAME = 'lu_work_type' )
  ) THEN


/* the stuff to do */
ALTER TABLE `lu_work_type`
ADD COLUMN `show_on_grades_page` bool;

UPDATE `lu_work_type`
SET `show_on_grades_page` = 1
WHERE `name` = "Assessment Task"
AND EXISTS (

SELECT * FROM `config`
WHERE `name` = "site_location"
AND value IN ("peninsula", "btac")

);


END IF;

END $$

CALL upgrade_add_column() $$

DELIMITER ;

DROP PROCEDURE IF EXISTS upgrade_add_column;