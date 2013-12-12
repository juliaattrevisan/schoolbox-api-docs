/* Add serialized config option for Evaluation questions
 */
ALTER TABLE `assess_question` ADD COLUMN `config` VARCHAR(255) NULL AFTER `question`;
ALTER TABLE `assess_question_bank` ADD COLUMN `config` VARCHAR(255) NULL AFTER `marks`;
ALTER TABLE `assess_luQuestionType` ADD COLUMN `has_config` ENUM('true','false') DEFAULT 'false' NOT NULL AFTER `has_legend`;
UPDATE `assess_luQuestionType` SET `has_config` = 'true' WHERE `type_id` = '2';