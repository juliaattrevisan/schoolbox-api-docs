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
require_once('../library/assess/assess.php');
require_once('../library/assess/assess-factory.php');
require_once('../library/assess/assess-attempt.php');
require_once('../library/assess/assess-section.php');

$factory = new AssessFactory();
$assessHandler = new Assess();
$attemptHandler = new AssessAttempt();
$sectionHandler = new AssessSection();

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

    $this->setVar(array(
        'SECTION_SECTION_ID' => $currentSection['section_id'],
        'SECTION_TITLE' => $currentSection['title'],
        'SECTION_DESCRIPTION' => $currentSection['description'],
        'SECTION_RANDOM' => $currentSection['random'],
        'SECTION_POOL' => $currentSection['pool'],
    ));

	$allAttempts = $attemptHandler->getListByAssessAndSection($this->arg[0], $currentSection['section_id']);

	$attemptValues = array();

	if ($allAttempts) {
		for ($j = 0; $j < count($allAttempts); $j++) {
			$currentAttempt = $allAttempts[$j];

			$attemptValues[$j]['name'] = $currentAttempt['firstname'] . ' ' . $currentAttempt['lastname'];

			$allQuestions = $sectionHandler->getContents($this->arg[0], $currentSection['section_id']);

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
						$responseValues[$i] = strip_tags(str_replace("\t", '', $answerHandler->exportResponseToCSV($currentAttempt['attempt_id'], $currentQuestion['question_id'])));
					}
				}
			}

			$attemptValues[$j]['questions'] = $questionValues;
			$attemptValues[$j]['responses'] = $responseValues;
		}
	}

	$sectionValues[$k]['section'] = $currentSection['title'];
	$sectionValues[$k]['attempts'] = $attemptValues;
}

/* Format output
 */
print_r($sectionValues);

/* Prevent other template crap from rendering ;)
 */
die();
?>
