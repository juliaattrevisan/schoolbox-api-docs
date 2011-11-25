ALTER TABLE `assess_luQuestionType` ADD COLUMN `classname` VARCHAR(255) NULL AFTER `type_id`;

UPDATE `assess_luQuestionType` SET `classname`='Short' WHERE `type_id`='1';
UPDATE `assess_luQuestionType` SET `classname`='Multi' WHERE `type_id`='2';
UPDATE `assess_luQuestionType` SET `classname`='Fillgap' WHERE `type_id`='3';
UPDATE `assess_luQuestionType` SET `classname`='Match' WHERE `type_id`='4';
UPDATE `assess_luQuestionType` SET `classname`='Radio' WHERE `type_id`='5';
UPDATE `assess_luQuestionType` SET `classname`='Rating_Agree' WHERE `type_id`='6';
UPDATE `assess_luQuestionType` SET `classname`='Rating_Like' WHERE `type_id`='7';
UPDATE `assess_luQuestionType` SET `classname`='Rating_Important' WHERE `type_id`='8';
UPDATE `assess_luQuestionType` SET `classname`='Rating_Satisfied' WHERE `type_id`='9';
UPDATE `assess_luQuestionType` SET `classname`='YesNo' WHERE `type_id`='10';
UPDATE `assess_luQuestionType` SET `classname`='TrueFalse' WHERE `type_id`='11';

/* Border Leave Request */
CREATE TABLE `leave_request` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_phone` varchar(32) NOT NULL,
  `start` datetime NOT NULL,
  `finish` datetime NOT NULL,
  `host_type` int(11) NOT NULL,
  `host_name` varchar(64) NOT NULL,
  `host_phone` varchar(32) NOT NULL,
  `description` varchar(128) NOT NULL,
  `created` datetime DEFAULT NULL,
  `status` int(11) NOT NULL,
  `authorisation_datetime` datetime DEFAULT NULL,
  `authorisation_user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=latin1;