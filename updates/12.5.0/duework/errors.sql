/* create the submission errors table */
CREATE TABLE `submission_errors` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(4) NOT NULL,
  `display_as` VARCHAR(16) NOT NULL,
  `normalise_as` DECIMAL UNSIGNED,
  PRIMARY KEY (`id`),
  UNIQUE INDEX (`code`)
) CHARSET=utf8 COLLATE=utf8_bin;

/* and now populate the aforementioned table */
INSERT INTO `submission_errors`
  (`code`, `display_as`, `normalise_as`)
VALUES
  ('ABS', 'Absent', '0'),
  ('NA', 'Not Assessed', NULL)
  ('NS', 'Not Submitted', '0')
;
