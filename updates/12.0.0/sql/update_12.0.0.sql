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

/* Twitter feed homepage component
 */
INSERT INTO `portal-dev`.`homepage_comp`(`id`,`size`,`method`,`name`,`description`,`homepageCategoryID`,`filename`,`config`,`invisible`,`allowMultiple`) VALUES ( NULL,'0','outputTwitter','Twitter','Output twitter feeds','6','twitter.html','','false','false');