-- We only want to execute this statement if the column does not yet exist.
-- This is because in our demo data we may have already added the column and populated it with data.
DROP PROCEDURE IF EXISTS addColumn;

-- Set the delimiter to something else so we can embed semicolons in
-- the procedure body being passed by the client to server.
DELIMITER $$

-- Create the procedure
CREATE PROCEDURE addColumn()
BEGIN
  IF NOT EXISTS(
      SELECT * FROM information_schema.COLUMNS
      WHERE COLUMN_NAME = 'ib_flag' AND TABLE_NAME = 'user_profile' AND TABLE_SCHEMA = DATABASE()
    )
  THEN
    ALTER TABLE user_profile ADD COLUMN ib_flag BOOLEAN NULL;
  END IF;
END $$

-- Reset the delimiter back to normal
DELIMITER ;

-- Execute the procedure then remove it
CALL addColumn;
DROP PROCEDURE addColumn;
