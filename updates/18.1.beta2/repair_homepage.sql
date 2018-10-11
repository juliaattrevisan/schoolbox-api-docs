DROP PROCEDURE IF EXISTS PROC_DROP_FOREIGN_KEY;
DELIMITER $$
CREATE PROCEDURE PROC_DROP_FOREIGN_KEY(IN tableName VARCHAR(64), IN constraintName VARCHAR(64))
BEGIN
  IF EXISTS(
      SELECT * FROM information_schema.table_constraints
      WHERE
          table_schema    = DATABASE()     AND
          table_name      = tableName      AND
          constraint_name = constraintName AND
          constraint_type = 'FOREIGN KEY')
  THEN
      SET @query = CONCAT('ALTER TABLE ', tableName, ' DROP FOREIGN KEY ', constraintName, ';');
      PREPARE stmt FROM @query;
      EXECUTE stmt;
      DEALLOCATE PREPARE stmt;
  END IF;
END$$
DELIMITER ;

CALL PROC_DROP_FOREIGN_KEY('homepage_log', 'fk_homepage_log_user_id');
CALL PROC_DROP_FOREIGN_KEY('homepage_log', 'fk_homepage_log_folder_id');

ALTER TABLE `homepage_log`
ADD CONSTRAINT `fk_homepage_log_user_id`
FOREIGN KEY (`user_id`)
REFERENCES `user` (`id`)
ON DELETE NO ACTION
ON UPDATE NO ACTION,
ADD CONSTRAINT `fk_homepage_log_folder_id`
FOREIGN KEY (`folder_id`)
REFERENCES `folder` (`id`)
ON DELETE NO ACTION
ON UPDATE NO ACTION;