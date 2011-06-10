ALTER TABLE `submission_box`     CHANGE `description` `description` VARCHAR(250) CHARACTER SET utf8 COLLATE utf8_general_ci NULL ;
ALTER TABLE `submission_return`  CHANGE `comment` `comment` varchar(250)  COLLATE utf8_general_ci NULL after `owner`;

INSERT INTO `config` (`name`, `value`, `title`, `description`, `field`, `type`, `editable`, `hidden`) VALUES ('site_version', '11.1', 'Schoolbox Version', 'Version of Schoolbox currently installed', 'input', 'Site', 'false','false');
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field`, `options`, `type`, `editable`, `hidden`) VALUES('api_key','','API Key','The API key must be set before the Schoolbox API can be used. This key should be kept safe as it is possible to gain superuser privileges with it.','input','','Security','true','false');

ALTER TABLE `submission_box` CHANGE `description` `name` VARCHAR(150) CHARACTER SET utf8 COLLATE utf8_general_ci NULL AFTER `deleted`;
ALTER TABLE `submission_box` ADD COLUMN `description` VARCHAR(750) NOT NULL AFTER `name`;

ALTER TABLE `portal`.`event` ADD FULLTEXT `fulltext` (`note`, `detail`, `location`);