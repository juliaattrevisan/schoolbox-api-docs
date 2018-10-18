-- Create the new column if it doesn't exist
DELIMITER $$

DROP PROCEDURE IF EXISTS upgrade_add_column $$

CREATE PROCEDURE upgrade_add_column()
  BEGIN

    IF NOT EXISTS (
        ( SELECT * FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND COLUMN_NAME = 'staff_or_instructor_visible_only'
            AND TABLE_NAME = 'lu_work_type' )
    ) THEN


      /* the stuff to do */
      ALTER TABLE `lu_work_type`
        ADD COLUMN `staff_or_instructor_visible_only` bool;

    END IF;

  END $$

CALL upgrade_add_column() $$

DELIMITER ;

DROP PROCEDURE IF EXISTS upgrade_add_column;

-- Set them all to 'false' (default case)
UPDATE lu_work_type
SET staff_or_instructor_visible_only = FALSE;

-- And remove the nullability of this column.
ALTER TABLE lu_work_type
  CHANGE COLUMN staff_or_instructor_visible_only staff_or_instructor_visible_only BOOL NOT NULL;
