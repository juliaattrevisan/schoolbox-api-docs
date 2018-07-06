ALTER TABLE `rubric_criterion`
    CHANGE COLUMN `grouping` `_grouping` VARCHAR(1000) CHARACTER SET 'utf8mb4' NULL DEFAULT NULL ,
    CHANGE COLUMN `parent_id` `_parent_id` INT(11) NOT NULL ,
    CHANGE COLUMN `parent_type` `_parent_type` ENUM('submission', 'category', 'curriculum') CHARACTER SET 'utf8mb4' NULL DEFAULT NULL ;
