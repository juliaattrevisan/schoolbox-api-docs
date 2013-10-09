/* These are the javascript plugins which control validation and user
 * interaction for each of the question types. These should not really
 * reside in the db (they should probably be in each question class)
 * but I'm going to stash them here until we use Mappers for Evaluation
 */
ALTER TABLE `assess_luQuestionType` CHANGE `type_id` `type_id` INT(8) NOT NULL AUTO_INCREMENT;

/* Multi w/ other
 */
INSERT INTO `assess_luQuestionType` (`classname`, `filename`, `description`, `moodle`, `automated_marking`, `availability`, `has_legend`) VALUES ('MultiOther', 'evaluationMultipleChoiceOther', 'multiOther', 'Multiple Choice w/ Other', 'multiOther', 'false', 'survey', 'true');

/* Numeric answer
 */
INSERT INTO `assess_luQuestionType` (`classname`, `filename`, `description`, `moodle`, `automated_marking`, `availability`, `has_legend`) VALUES ('Numeric', 'numeric', 'Numeric Answer', 'numeric', 'true', 'all', 'false');

/* Rich Text answer
 */
INSERT INTO `assess_luQuestionType` (`classname`, `filename`, `description`, `moodle`, `automated_marking`, `availability`, `has_legend`) VALUES ('RichText', 'richtext', 'Rich Text Answer', 'richtext', 'false', 'assess', 'false');

/* Exact Answer
 */
INSERT INTO `assess_luQuestionType` (`classname`, `filename`, `description`, `moodle`, `automated_marking`, `availability`, `has_legend`) VALUES ('Exact', 'exact', 'Exact Answer', 'exact', 'true', 'assess', 'true');