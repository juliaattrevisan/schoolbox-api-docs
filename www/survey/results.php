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

$assessHandler = new Survey();
$sectionHandler = new SurveySection();
$factory = new SurveyFactory();
$attemptHandler = new SurveyAttempt();

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

/* Set up template
 */
$allSections = $sectionHandler->getList($this->arg[0]);

for ($k = 0; $k < count($allSections); $k++) {
	$currentSection = $allSections[$k];
	$questionValues = array();
	$responseValues = array();
	$attemptValues = array();

	/* Initialise array data
	 */
	$sectionValues[$k]['section'] = '';
	$sectionValues[$k]['questions'] = '';

	$allAttempts = $attemptHandler->getListByAssessAndSection($this->arg[0], $currentSection['section_id']);

	/* Get a list of questions
 	*/
	$allQuestions = $sectionHandler->getContents($this->arg[0], $currentSection['section_id']);

	for ($i = 0; $i < count($allQuestions); $i++) {
		$currentQuestion = $allQuestions[$i];

		/* Store some extra details about the question. question_id is used by
		 * the templating engine for uniqueness; type_id is for loading an
		 * appropriate answerHandler, which contains the graphing method
		 */
		$questionValues[$i]['question'] = strip_tags($currentQuestion['question']);
		$questionValues[$i]['question_id'] = $currentQuestion['question_id'];
		$questionValues[$i]['type_id'] = $currentQuestion['type_id'];
	}

	$sectionValues[$k]['questions'] = $questionValues;

	/* Get attempts for the assessment
	 */
	if ($allAttempts) {
		for ($j = 0; $j < count($allAttempts); $j++) {
			$currentAttempt = $allAttempts[$j];

			$attemptValues[$j]['name'] = $currentAttempt['firstname'] . ' ' . $currentAttempt['lastname'];

			if ($allQuestions) {
				$questionValues = array();
				$responseValues = array();

				for ($i = 0; $i < count($allQuestions); $i++) {
					$currentQuestion = $allQuestions[$i];

					$questionValues[$i] = strip_tags($currentQuestion['question']);

					/* This if() clause allows for sections containing no questions.
					 * because they come out with getContents()
					 */
					if ($currentQuestion['type_id']) {
						$answerHandler = $factory->createAnswerModel($currentQuestion['type_id']);

						/* Load answer template into answerOption block
						 */
						$currentResponse = $answerHandler->exportResponseToArray($currentAttempt['attempt_id'], $currentQuestion['question_id']);

						/* Update summary
						 */
						if (is_array($currentResponse)) {
							foreach($currentResponse as $individualResponse) {

								/* Find out if you need to extract the response text for the
								 * options that were available
								 */
								if (!isset($sectionValues[$k]['questions'][$i]['summary'][$individualResponse]['text'])) {
									$answerText = $answerHandler->getAnswerText($currentQuestion['question_id'], $individualResponse);

									$sectionValues[$k]['questions'][$i]['summary'][$individualResponse]['text'] = $answerText;
								}

								$sectionValues[$k]['questions'][$i]['summary'][$individualResponse]['values']++;
							}
						}

						/* Inconcatenatable non-summarisable data (short answer) which only
						 * need to be collated into a giant list
						 */
						else {
							$sectionValues[$k]['questions'][$i]['summary'][$currentResponse]['values']++;
						}
					}
				}
			}

			$attemptValues[$j]['responses'] = $responseValues;
		}
	}

	$sectionValues[$k]['section'] = $currentSection['title'];
}

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

foreach($sectionValues[0]['questions'] as $currentQuestion) {
	$answerHandler = $factory->createAnswerModel($currentQuestion['type_id']);
	$answerHandler->graphResults($this, $currentQuestion);

	/* Question data
	 */
	$this->setVar(array(
		'QUESTION_QUESTION' => $currentQuestion['question']
	));

	$this->parse('question_block', 'question', true);

	/* Clear children after parsing out the parent
	 */
	$this->setVar('series_block', '');
}

//print_r($sectionValues);
?>
