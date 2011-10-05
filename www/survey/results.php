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

$surveyExporter = new SurveyExporter($assess,  new SurveyFactory(), new SurveySection(), new SurveyAttempt(), new SurveyQuestion());
$sectionValues = $surveyExporter->getSectionValues();

/* Data collected, now we can show it.
 *
 * For each section, for each question, summarise all attempts' results
 */
$this->setFile(TEMPLATE_content, 'survey/results.html');
$this->setBlock(TEMPLATE_content, 'section', 'section_block');
$this->setBlock('section', 'question', 'question_block');
$this->setBlock('question', 'graph', 'graph_block');

$this->setVar(array(
	'SURVEY_NAME' => $assess['title']
));

$factory = new SurveyFactory();

if (is_array($sectionValues) && count($sectionValues)) {
	foreach($sectionValues as $currentSection) {
		$i = 1;

		if (is_array($currentSection['questions']) && count($currentSection['questions'])) {
			foreach($currentSection['questions'] as $currentQuestion) {

				/* Question data
				 */
				$this->setVar(array(
					'ASSESS_ID' => $assess['assess_id'],
					'QUESTION_QUESTION' => $currentQuestion['question'],
					'QUESTION_QUESTION_ID' => $currentQuestion['question_id'],
					'QUESTION_INDEX' => $i++,
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
		}

		$this->setVar(array(
			'SECTION_NAME' => $currentSection['section']
		));

		$this->parse('section_block', 'section', true);

		/* Clear children after parsing out the parent
		 */
		$this->setVar('question_block', '');
	}
}

