ALTER TABLE `course_unit_implementation`
  CHANGE COLUMN `course_implementation_id` `course_implementation_id` INT(11) UNSIGNED NOT NULL ,
  CHANGE COLUMN `course_unit_specification_id` `course_unit_specification_id` INT(11) UNSIGNED NOT NULL ;

-- No choice but to delete units which don't have a course
DELETE cui FROM course_unit_implementation cui
  LEFT JOIN course_implementation ci ON cui.course_implementation_id = ci.id
WHERE ci.id IS NULL;

-- And units which don't have a spec
DELETE cui FROM course_unit_implementation cui
  LEFT JOIN course_unit_specification cus ON cui.course_unit_specification_id = cus.id
WHERE cus.id IS NULL;

ALTER TABLE `course_unit_implementation`
  ADD INDEX `fk_course_unit_implementation_course_unit_specification_id_idx` (`course_unit_specification_id` ASC);
ALTER TABLE `course_unit_implementation`
  ADD CONSTRAINT `fk_course_unit_implementation_course_implementation_id`
FOREIGN KEY (`course_implementation_id`)
REFERENCES `course_implementation` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_course_unit_implementation_course_unit_specification_id`
FOREIGN KEY (`course_unit_specification_id`)
REFERENCES `course_unit_specification` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `course_unit_implementation`
  DROP COLUMN `unit_category_id`,
  DROP COLUMN `category_id`,
  DROP COLUMN `component_id`,
  DROP INDEX `course_unit_implementation_category_id` ,
  DROP INDEX `course_unit_implementation_unit_category_id` ,
  DROP INDEX `course_unit_implementation_component_id` ;
