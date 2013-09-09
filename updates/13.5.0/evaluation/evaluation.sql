/* SB-2763
 */
INSERT INTO `assess_luQuestionType` (`classname`, `filename`, `description`, `moodle`, `automated_marking`, `availability`) VALUES ('Numeric', 'numeric', 'Numeric Answer', 'numeric', 'true', 'all');
UPDATE `assess_luQuestionType` SET `type_id` = '12' WHERE `type_id` = '0';