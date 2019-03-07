-- We only want to execute this statement if the column does not yet exist.
-- This is because the same update was successfully run on some servers previously.
DROP PROCEDURE IF EXISTS addColumn;

-- Set the delimiter to something else so we can embed semicolons in
-- the procedure body being passed by the client to server.
DELIMITER $$

-- Create the procedure
CREATE PROCEDURE addColumn()
  BEGIN
    IF NOT EXISTS(
        SELECT * FROM information_schema.COLUMNS
        WHERE COLUMN_NAME = 'realname' AND TABLE_NAME = 'submission_box_file' AND TABLE_SCHEMA = DATABASE()
    )
    THEN
      ALTER TABLE `submission_box_file` ADD COLUMN `realname` VARCHAR(255) NULL AFTER `box_id`;
    END IF;
  END $$

-- Reset the delimiter back to normal
DELIMITER ;

-- Execute the procedure then remove it
CALL addColumn;
DROP PROCEDURE addColumn;



