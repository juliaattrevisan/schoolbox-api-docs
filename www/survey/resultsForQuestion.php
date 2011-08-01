<?php
/**
 * Assessment export. Uses the handlers to export their question types into a
 * Moodle format.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once('../library/survey/Survey.php');
require_once('../library/survey/SurveySection.php');
require_once('../library/survey/SurveyQuestion.php');
require_once('../library/survey/SurveyAnswer.php');
require_once('../library/survey/SurveyFactory.php');
require_once('../library/survey/SurveyAttempt.php');
require_once('../library/survey/SurveyExporter.php');

$assessHandler = new Survey();
$factory = new SurveyFactory();

if (!is_numeric($this->arg[0])) {
    error(112);
}

$assess = $assessHandler->getByID($this->arg[0]);

if (!$assess) {
    error(112);
}

if ($assessHandler->isDeleted($this->arg[0])) {
	error(112);
}

$surveyExporter = new SurveyExporter($assess, $factory, new SurveySection(), new SurveyAttempt(), new SurveyQuestion());

/* Optionally filter by question
 */
if (is_numeric($this->arg[1])) {
	$surveyExporter->setFilterByQuestion($this->arg[1]);
}

$sectionValues = $surveyExporter->getSectionValues();

/* Format output
 */
$this->setFile(TEMPLATE_content, 'survey/resultsForQuestion.html');
$this->setBlock(TEMPLATE_content, 'respondent', 'respondent_block');

if (is_array($sectionValues) && count($sectionValues)) {
	foreach($sectionValues as $currentSection) {
		$questionList = array();

		/* Get a list of questions going
		 */
		if (is_array($currentSection['questions']) && count($currentSection['questions'])) {
			foreach($currentSection['questions'] as $currentQuestion) {
				$questionList = $currentQuestion['question'];
			}
		}

		/* A list of attempts
		 */
		if (count($currentSection['attempts'])) {
			foreach($currentSection['attempts'] as $currentAttempt) {
				$this->setVar(array(
					'RESPONDENT_NAME' => $currentAttempt['name'],
					'RESPONDENT_RESPONSE' => $currentAttempt['responses']
				));

				$this->parse('respondent_block', 'respondent', true);
			}
		}
	}
}

$this->setVar(array(
	'SURVEY_NAME' => $assess['title'],
	'QUESTION_QUESTION' => $questionList
));

?>
