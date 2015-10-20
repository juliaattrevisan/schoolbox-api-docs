/* New table */
CREATE TABLE `localisation`(
  `term` VARCHAR(256) NOT NULL,
  `display_as` VARCHAR(256) NOT NULL,
  PRIMARY KEY (`term`)
);

/* Inject initial default values */
INSERT INTO `localisation` (`term`, `display_as`) VALUES
  ('calendar', 'calendar'),
  ('news', 'news')
ON DUPLICATE KEY UPDATE term = term;
