CREATE TABLE `filestore`(
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `hash` VARCHAR(40) NOT NULL,
  `ext` VARCHAR(8) NOT NULL,
  `size` INT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX (`hash`),
  INDEX (`ext`)
);