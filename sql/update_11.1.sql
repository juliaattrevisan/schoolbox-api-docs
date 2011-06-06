ALTER TABLE `submission_box`     CHANGE `description` `description` VARCHAR(250) CHARACTER SET utf8 COLLATE utf8_general_ci NULL ;
ALTER TABLE `submission_return`  CHANGE `comment` `comment` varchar(250)  COLLATE utf8_general_ci NULL after `owner`;

INSERT INTO `config` (`name`, `value`, `title`, `description`, `field`, `type`, `editable`, `hidden`) VALUES ('site_version', '11.1', 'Schoolbox Version', 'Version of Schoolbox currently installed', 'input', 'Site', 'false','false');
