-- Profile Relation tuple is unique
-- your profile can't be related to the another profile multiple times

-- ALTER IGNORE will remove dupes
-- https://stackoverflow.com/questions/3311903/remove-duplicate-rows-in-mysql

-- Wrapped in stored proc so as to only execute if constraint does not yet exist.

-- Reset delimiter so we use normal delimiter ';' inside procedure definition.
DELIMITER $$

-- Create procedure
CREATE PROCEDURE create_constraint_if_not_exists()
BEGIN
    IF
        (SELECT COUNT(*)
        FROM information_schema.innodb_sys_indexes  i
        JOIN information_schema.innodb_sys_tables t ON t.TABLE_ID = i.TABLE_ID
        WHERE t.NAME LIKE '%sis_user_profile_relation' AND i.NAME = 'sis_unique_relation') = 0
    THEN
        ALTER IGNORE TABLE `sis_user_profile_relation`
        ADD UNIQUE INDEX `sis_unique_relation` (`child_profile_id` ASC, `user_profile_id` ASC);
    END IF;
END $$

-- Reset delimiter back to default value.
DELIMITER ;

-- Call procedure then drop it.
CALL create_constraint_if_not_exists();
DROP PROCEDURE create_constraint_if_not_exists;
