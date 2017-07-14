/* yes, ASCII is on purpose here */

CREATE TABLE `mobile_app` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `app_identifier` VARCHAR(128) NOT NULL,
  `provider` ENUM('APNS','GCM') NOT NULL,
  `key` VARCHAR(512) NOT NULL,
  `cert_hash` VARCHAR(40) NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX (`app_identifier`, `provider`),
  FOREIGN KEY (`cert_hash`) REFERENCES `storage`(`hash`) ON UPDATE NO ACTION ON DELETE NO ACTION
) CHARACTER SET ASCII COLLATE ascii_general_ci;
