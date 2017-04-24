DELETE reportcard_comment FROM reportcard_comment
  LEFT JOIN `folder` ON category_id = `folder`.id
WHERE `folder`.id is null;

ALTER TABLE `reportcard_comment`
  ADD CONSTRAINT `fk_reportcard_comment_folder_id`
FOREIGN KEY (`category_id`)
REFERENCES `folder` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

UPDATE reportcard_comment
  LEFT JOIN `user` ON `commenter_id` = `user`.id
SET commenter_id = (SELECT `id` FROM `user` WHERE username LIKE 'alaress')
WHERE `user`.id is null;

ALTER TABLE `reportcard_comment`
  ADD INDEX `fk_reportcard_comment_commenter_id_idx` (`commenter_id` ASC);
ALTER TABLE `reportcard_comment`
  ADD CONSTRAINT `fk_reportcard_comment_commenter_id`
FOREIGN KEY (`commenter_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

DELETE reportcard_comment FROM reportcard_comment
  LEFT JOIN `user` ON student_id = `user`.id
WHERE `user`.id is null;

ALTER TABLE `reportcard_comment`
  ADD CONSTRAINT `fk_reportcard_comment_student_id`
FOREIGN KEY (`student_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;
