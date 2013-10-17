/* Create report card comment table */
CREATE TABLE `reportcard_comment`(
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NOT NULL,
  `commenter_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `body` VARCHAR(512) NOT NULL,
  `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` TIMESTAMP,
  `deleted` TIMESTAMP,
  `student_visibility` BOOL NOT NULL DEFAULT TRUE,
  `parent_visibility` BOOL NOT NULL DEFAULT TRUE,
  PRIMARY KEY (`id`),
  INDEX (`category_id`, `student_id`),
  INDEX (`created`)
) CHARSET=utf8;

/* for some reason, you cannot CREATE default null timestamp fields, but you can ALTER them to be... */
ALTER TABLE `reportcard_comment`
CHANGE `modified` `modified` TIMESTAMP NULL,
CHANGE `deleted` `deleted` TIMESTAMP NULL;
