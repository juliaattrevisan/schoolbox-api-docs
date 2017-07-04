DELETE pastoral FROM pastoral
  LEFT JOIN user ON user.id = pastoral.student_id
WHERE user.id is null;

ALTER TABLE `pastoral`
  CHANGE COLUMN `owner_id` `owner_id` INT(11) UNSIGNED NULL DEFAULT NULL ,
  CHANGE COLUMN `student_id` `student_id` INT(11) UNSIGNED NOT NULL ;


ALTER TABLE `pastoral`
  ADD CONSTRAINT `fk_pastoral_student_id`
FOREIGN KEY (`student_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

UPDATE pastoral
  LEFT JOIN `user` ON `owner_id` = `user`.id
SET owner_id = (SELECT `id` FROM `user` WHERE username LIKE 'alaress')
WHERE `user`.id is null;

ALTER TABLE `pastoral`
  ADD INDEX `fk_pastoral_owner_id_idx` (`owner_id` ASC);
ALTER TABLE `pastoral`
  ADD CONSTRAINT `fk_pastoral_owner_id`
FOREIGN KEY (`owner_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

UPDATE pastoral
  LEFT JOIN lu_pastoral_category ON lu_pastoral_category.id = pastoral.category_id
  SET pastoral.category_id = NULL
WHERE lu_pastoral_category.id IS NULL;

ALTER TABLE `pastoral`
  ADD INDEX `fk_pastoral_category_id_idx` (`category_id` ASC);
ALTER TABLE `pastoral`
  ADD CONSTRAINT `fk_pastoral_category_id`
FOREIGN KEY (`category_id`)
REFERENCES `lu_pastoral_category` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;


ALTER TABLE `pastoral_access`
  CHANGE COLUMN `role_id` `role_id` INT(11) UNSIGNED NULL DEFAULT NULL ,
  CHANGE COLUMN `user_id` `user_id` INT(11) UNSIGNED NULL DEFAULT NULL ;

UPDATE pastoral_access
  LEFT JOIN groups ON groups.id = pastoral_access.role_id
SET pastoral_access.role_id = NULL
WHERE groups.id IS NULL;

ALTER TABLE `pastoral_access`
  ADD INDEX `fk_pastoral_access_role_id_idx` (`role_id` ASC);
ALTER TABLE `pastoral_access`
  ADD CONSTRAINT `fk_pastoral_access_role_id`
FOREIGN KEY (`role_id`)
REFERENCES `groups` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

UPDATE pastoral_access
  LEFT JOIN user ON user.id = pastoral_access.user_id
SET pastoral_access.user_id = NULL
WHERE user.id IS NULL;

ALTER TABLE `pastoral_access`
  ADD INDEX `fk_pastoral_access_user_id_idx` (`user_id` ASC);
ALTER TABLE `pastoral_access`
  ADD CONSTRAINT `fk_pastoral_access_user_id`
FOREIGN KEY (`user_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

UPDATE pastoral_access
  LEFT JOIN pastoral_group ON pastoral_group.id = pastoral_access.group_id
SET pastoral_access.group_id = NULL
WHERE pastoral_group.id IS NULL;

ALTER TABLE `pastoral_access`
  ADD INDEX `fk_pastoral_access_group_id_idx` (`group_id` ASC);
ALTER TABLE `pastoral_access`
  ADD CONSTRAINT `fk_pastoral_access_group_id`
FOREIGN KEY (`group_id`)
REFERENCES `pastoral_group` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `pastoral_group`
  CHANGE COLUMN `name` `name` VARCHAR(255) CHARACTER SET 'utf8mb4' NOT NULL ,
  CHANGE COLUMN `user_id` `user_id` INT(11) UNSIGNED NOT NULL ;

DELETE pastoral_group FROM pastoral_group
  LEFT JOIN user ON user.id = pastoral_group.user_id
WHERE user.id IS NULL;

ALTER TABLE `pastoral_group`
  ADD INDEX `fk_pastoral_group_user_id_idx` (`user_id` ASC);
ALTER TABLE `pastoral_group`
  ADD CONSTRAINT `fk_pastoral_group_user_id`
FOREIGN KEY (`user_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `pastoral_group_member`
  CHANGE COLUMN `user_id` `user_id` INT(11) UNSIGNED NOT NULL ,
  CHANGE COLUMN `group_id` `group_id` INT(11) NOT NULL,
  CHANGE COLUMN `author_id` `author_id` INT(11) UNSIGNED NOT NULL;

DELETE pastoral_group_member FROM pastoral_group_member
  LEFT JOIN user ON user.id = pastoral_group_member.user_id
WHERE user.id IS NULL;

DELETE pastoral_group_member FROM pastoral_group_member
  LEFT JOIN user ON user.id = pastoral_group_member.author_id
WHERE user.id IS NULL;

DELETE pastoral_group_member FROM pastoral_group_member
  LEFT JOIN pastoral_group ON pastoral_group.id = pastoral_group_member.group_id
WHERE pastoral_group.id IS NULL;

ALTER TABLE `pastoral_group_member`
  ADD INDEX `fk_pastoral_group_member_group_id_idx` (`group_id` ASC),
  ADD INDEX `fk_pastoral_group_member_user_id_idx` (`user_id` ASC),
  ADD INDEX `fk_pastoral_group_member_author_id_idx` (`author_id` ASC);
ALTER TABLE `pastoral_group_member`
  ADD CONSTRAINT `fk_pastoral_group_member_group_id`
FOREIGN KEY (`group_id`)
REFERENCES `pastoral_group` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_pastoral_group_member_user_id`
FOREIGN KEY (`user_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_pastoral_group_member_author_id`
FOREIGN KEY (`author_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;


ALTER TABLE `pastoral_comment`
  CHANGE COLUMN `owner_id` `owner_id` INT(11) UNSIGNED NOT NULL ;

DELETE pastoral_comment FROM pastoral_comment
  LEFT JOIN user ON user.id = pastoral_comment.owner_id
WHERE user.id IS NULL;

ALTER TABLE `pastoral_comment`
  ADD INDEX `fk_pastoral_comment_owner_id_idx` (`owner_id` ASC);
ALTER TABLE `pastoral_comment`
  ADD CONSTRAINT `fk_pastoral_comment_owner_id`
FOREIGN KEY (`owner_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

UPDATE lu_pastoral_category
  LEFT JOIN lu_pastoral_type ON lu_pastoral_type.id = lu_pastoral_category.pastoral_type_id
SET lu_pastoral_category.pastoral_type_id = NULL
WHERE lu_pastoral_type.id IS NULL;

ALTER TABLE `lu_pastoral_category`
  ADD INDEX `fk_lu_pastoral_category_pastoral_type_id_idx` (`pastoral_type_id` ASC);
ALTER TABLE `lu_pastoral_category`
  ADD CONSTRAINT `fk_lu_pastoral_category_pastoral_type_id`
FOREIGN KEY (`pastoral_type_id`)
REFERENCES `lu_pastoral_type` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

UPDATE lu_pastoral_type
  LEFT JOIN lu_pastoral_document_classification ON lu_pastoral_document_classification.id = lu_pastoral_type.pastoral_document_classification_id
SET lu_pastoral_type.pastoral_document_classification_id = NULL
WHERE lu_pastoral_document_classification.id IS NULL;

ALTER TABLE `lu_pastoral_type`
  ADD INDEX `fk_lu_pastoral_type_pastoral_document_classification_id_idx` (`pastoral_document_classification_id` ASC);
ALTER TABLE `lu_pastoral_type`
  ADD CONSTRAINT `fk_lu_pastoral_type_pastoral_document_classification_id`
FOREIGN KEY (`pastoral_document_classification_id`)
REFERENCES `lu_pastoral_document_classification` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;
