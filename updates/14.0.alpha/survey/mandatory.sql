/* Add Mandatory configuration option to survey-based questions
 */
UPDATE `assess_luQuestionType` SET has_config = 'true'  WHERE availability = 'all' OR availability = 'survey'