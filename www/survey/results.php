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
$assess = $assessHandler->getByID($this->arg[0]);

if (!is_numeric($this->arg[0])) {
    error(112);
}

if (!$assess) {
    error(112);
}

if ($assessHandler->isDeleted($this->arg[0])) {
	error(112);
}

$surveyExporter = new SurveyExporter($assess,  new SurveyFactory(), new SurveySection(), new SurveyAttempt());
$sectionValues = $surveyExporter->getSectionValues();

/* Data collected, now we can show it.
 *
 * For each section, for each question, summarise all attempts' results
 */
$this->setFile(TEMPLATE_content, 'survey/results.html');
$this->setBlock(TEMPLATE_content, 'question', 'question_block');
$this->setBlock('question', 'graph', 'graph_block');

$this->setVar(array(
	'SURVEY_NAME' => $assess['title']
));

$factory = new SurveyFactory();

foreach($sectionValues[0]['questions'] as $currentQuestion) {

	/* Question data
	 */
	$this->setVar(array(
		'QUESTION_QUESTION' => $currentQuestion['question']
	));

	/* Answer and Response data
	 */
	$answerHandler = $factory->createAnswerModel($currentQuestion['type_id']);

	$answerHandler->graphResults($this, $currentQuestion);

	$this->parse('question_block', 'question', true);

	/* Clear children after parsing out the parent
	 */
	$this->setVar('series_block', '');
}
?>
