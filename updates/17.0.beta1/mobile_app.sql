/* yes, ASCII is on purpose here */

CREATE TABLE `mobile_app` (
  `app_id` VARCHAR(128) NOT NULL,
  `pem_hash` VARCHAR(40) NOT NULL,
  PRIMARY KEY (`app_id`),
  FOREIGN KEY (`pem_hash`) REFERENCES `storage`(`hash`) ON UPDATE NO ACTION ON DELETE NO ACTION
) CHARSET=ASCII;


ALTER TABLE `mobile_app_registry`
  ADD COLUMN `app_id` VARCHAR(128) NULL AFTER `provider`,
  ADD FOREIGN KEY (`app_id`) REFERENCES `mobile_app`(`app_id`) ON UPDATE NO ACTION ON DELETE NO ACTION,
  CHARSET=ASCII;

