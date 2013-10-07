/* These are the javascript plugins which control validation and user
 * interaction for each of the question types. These should not really
 * reside in the db (they should probably be in each question class)
 * but I'm going to stash them here until we use Mappers for Evaluation
 */
ALTER TABLE `assess_luQuestionType` ADD COLUMN `plugin` VARCHAR(255) NULL AFTER `classname`;
ALTER TABLE `assess_luQuestionType` CHANGE `type_id` `type_id` INT(8) NOT NULL AUTO_INCREMENT;

UPDATE `assess_luQuestionType` SET `plugin` = 'evaluationShort' WHERE `classname` = 'Short';
UPDATE `assess_luQuestionType` SET `plugin` = 'evaluationMultipleChoice' WHERE `classname` = 'Multi';
UPDATE `assess_luQuestionType` SET `plugin` = 'evaluationFillGap' WHERE `classname` = 'Fillgap';
UPDATE `assess_luQuestionType` SET `plugin` = 'evaluationMatchingPairs' WHERE `classname` = 'Match';
UPDATE `assess_luQuestionType` SET `plugin` = 'evaluationRadio' WHERE `classname` = 'Radio';
UPDATE `assess_luQuestionType` SET `plugin` = 'evaluationRadio' WHERE `classname` = 'Rating_Agree';
UPDATE `assess_luQuestionType` SET `plugin` = 'evaluationRadio' WHERE `classname` = 'Rating_Like';
UPDATE `assess_luQuestionType` SET `plugin` = 'evaluationRadio' WHERE `classname` = 'Rating_Important';
UPDATE `assess_luQuestionType` SET `plugin` = 'evaluationRadio' WHERE `classname` = 'Rating_Satisfied';
UPDATE `assess_luQuestionType` SET `plugin` = 'evaluationRadio' WHERE `classname` = 'YesNo';
UPDATE `assess_luQuestionType` SET `plugin` = 'evaluationRadio' WHERE `classname` = 'TrueFalse';
UPDATE `assess_luQuestionType` SET `plugin` = 'evaluationExact' WHERE `classname` = 'Exact';
UPDATE `assess_luQuestionType` SET `plugin` = 'evaluationNumeric' WHERE `classname` = 'Numeric';
UPDATE `assess_luQuestionType` SET `plugin` = 'evaluationRichText' WHERE `classname` = 'RichText';