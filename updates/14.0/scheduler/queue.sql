/* Create the table to be used for schedule queue persistence */
CREATE TABLE `scheduleq`(
  `id_hash` VARCHAR(40) NOT NULL,
  `identifier` BLOB NOT NULL,
  `command` VARCHAR(32) NOT NULL,
  `data` BLOB NOT NULL,
  PRIMARY KEY (`id_hash`)
) ENGINE=INNODB
  DEFAULT CHARACTER SET=utf8
  COLLATE=utf8_general_ci;
