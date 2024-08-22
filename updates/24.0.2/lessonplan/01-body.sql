-- Increase to mediumtext

ALTER TABLE `lesson_plan_field`
    CHANGE COLUMN `body` `body` MEDIUMTEXT NULL DEFAULT NULL ;

ALTER TABLE `lesson_plan_template_field`
    CHANGE COLUMN `body` `body` MEDIUMTEXT NOT NULL ;
