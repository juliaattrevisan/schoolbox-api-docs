/* n:1 lookup of names based on the intersection of a criterion and box */
CREATE TABLE `rubric_level`(
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `box_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  `sequence` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  INDEX (`box_id`),
  INDEX (`sequence`)
) CHARSET=utf8 COLLATE=utf8_bin;
