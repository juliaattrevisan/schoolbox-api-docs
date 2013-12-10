/* Add serialized config option for Evaluation questions
 */
ALTER TABLE `assess_question` ADD COLUMN `config` VARCHAR(255) NULL AFTER `question`;
ALTER TABLE `assess_question_bank` ADD COLUMN `config` VARCHAR(255) NULL AFTER `marks`;