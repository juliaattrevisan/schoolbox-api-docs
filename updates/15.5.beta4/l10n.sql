/* New table */
CREATE TABLE `localisation`(
  `term` VARCHAR(128) NOT NULL,
  `singular_form` VARCHAR(256) NOT NULL,
  `plural_form` VARCHAR(256) NOT NULL,
  `capitalise_first` TINYINT(1) UNSIGNED DEFAULT 1 NOT NULL,
  PRIMARY KEY (`term`)
);

/* Inject initial default values */
INSERT INTO `localisation` (`term`, `singular_form`, `plural_form`) VALUES
  ('calendar', 'calendar', 'calendars'),
  ('resource', 'resource', 'resources'),
  ('news', 'news', 'news')
ON DUPLICATE KEY UPDATE term = term;
