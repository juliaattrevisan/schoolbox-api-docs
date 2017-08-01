DELETE submission_grade FROM submission_grade
  LEFT JOIN `submission_grade_group` ON submission_grade_group_id = `submission_grade_group`.id
WHERE `submission_grade_group`.id is null;


ALTER TABLE `submission_grade`
  ADD CONSTRAINT `fk_submission_grade_submission_grade_group_id`
FOREIGN KEY (`submission_grade_group_id`)
REFERENCES `submission_grade_group` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;


UPDATE submission_box
  LEFT JOIN `submission_grade_group` ON `submission_grade_group_id` = `submission_grade_group`.id
SET submission_grade_group_id = (SELECT `id` FROM `submission_grade_group` LIMIT 1)
WHERE `submission_grade_group`.id is null AND submission_grade_group_id is not null;


ALTER TABLE `submission_box`
  ADD CONSTRAINT `fk_submission_box_submission_grade_group_id`
FOREIGN KEY (`submission_grade_group_id`)
REFERENCES `submission_grade_group` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;


DELETE rubric_mark FROM rubric_mark
  LEFT JOIN `submission_box` ON submission_box_id = `submission_box`.id
WHERE `submission_box`.id is null;

ALTER TABLE `rubric_mark`
  ADD CONSTRAINT `fk_rubric_mark_submission_box_id`
FOREIGN KEY (`submission_box_id`)
REFERENCES `submission_box` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

UPDATE project
  LEFT JOIN `submission_mark_type` ON project.`mark_type_id` = `submission_mark_type`.mark_type_id
SET project.`mark_type_id` =  (SELECT `id` FROM `submission_mark_type` WHERE slug LIKE 'none')
WHERE `submission_mark_type`.mark_type_id is null;

ALTER TABLE `project`
  ADD INDEX `fk_project_mark_type_id_idx` (`mark_type_id` ASC);
ALTER TABLE `project`
  ADD CONSTRAINT `fk_project_mark_type_id`
FOREIGN KEY (`mark_type_id`)
REFERENCES `submission_mark_type` (`mark_type_id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

UPDATE project
  LEFT JOIN `user` ON `owner_id` = `user`.id
SET owner_id = (SELECT `id` FROM `user` WHERE username LIKE 'alaress')
WHERE `user`.id is null;

ALTER TABLE `project`
  CHANGE COLUMN `owner_id` `owner_id` INT(11) UNSIGNED NOT NULL ;

ALTER TABLE `project`
  ADD CONSTRAINT `fk_project_owner_id`
FOREIGN KEY (`owner_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;