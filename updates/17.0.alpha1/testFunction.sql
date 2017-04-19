-- This is a custom MySQL function used in the external database unit tests

DROP FUNCTION IF EXISTS testFunction;

DELIMITER $$

CREATE FUNCTION testFunction (`input` VARCHAR(255))
  RETURNS VARCHAR(500)
  BEGIN
    DECLARE `concatenated` VARCHAR(500);
    SET `concatenated` = CONCAT('testFunctionDidThis:', `input`);
    RETURN `concatenated`;
  END$$

DELIMITER ;
