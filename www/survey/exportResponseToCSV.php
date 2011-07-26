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
require_once('../library/survey/survey.php');
require_once('../library/survey/survey-factory.php');
require_once('../library/survey/survey-attempt.php');
require_once('../library/survey/survey-section.php');

/**
 * Output a csv row
 *
 * @param <type> $data
 */
function outputCSV2($data) {
    $outstream = fopen("php://output", 'rectum');

    array_walk($data, '__outputCSV', $outstream);

    fclose($outstream);
}

function __outputCSV(&$vals, $key, $filehandler) {
	fputcsv($filehandler, $vals, ',', '"');
}

$factory = new SurveyFactory();
$assessHandler = new Survey();
$attemptHandler = new SurveyAttempt();
$sectionHandler = new SurveySection();

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

	/* Initialise array data
	 */
	$sectionValues[$k]['section'] = '';
	$sectionValues[$k]['questions'] = '';
	$sectionValues[$k]['attempts'] = array();

    $this->setVar(array(
        'SECTION_SECTION_ID' => $currentSection['section_id'],
        'SECTION_TITLE' => $currentSection['title'],
        'SECTION_DESCRIPTION' => $currentSection['description'],
        'SECTION_RANDOM' => $currentSection['random'],
        'SECTION_POOL' => $currentSection['pool'],
    ));

	$allAttempts = $attemptHandler->getListByAssessAndSection($this->arg[0], $currentSection['section_id']);

	$attemptValues = array();

	/* Get a list of questions
 	*/
	$allQuestions = $sectionHandler->getContents($this->arg[0], $currentSection['section_id']);

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
						$responseValues[$i] = strip_tags(str_replace("\t", '', $answerHandler->exportResponseToCSV($currentAttempt['attempt_id'], $currentQuestion['question_id'])));
					}
				}
			}

			$attemptValues[$j]['responses'] = $responseValues;
		}
	}

	$sectionValues[$k]['section'] = $currentSection['title'];
	$sectionValues[$k]['attempts'] = $attemptValues;
}

/* Format output
 */
header('Content-type: text/ascii');
header('Content-disposition: attachment; filename="exported_response_' . $this->arg[0] . '.csv"');

if (is_array($sectionValues) && count($sectionValues)) {
	foreach($sectionValues as $currentSection) {
		$questionList = array();

		/* Get a list of questions going
		 */
		if (is_array($currentSection['questions']) && count($currentSection['questions'])) {
			$questionList[] = "Respondent";

			foreach($currentSection['questions'] as $currentQuestion) {
				$questionList[] = $currentQuestion;
			}
		}

		/* Output to stdout stream
		 */
		echo 'Section ' . $currentSection['section'] . "\n";
		outputCSV2(array($questionList));

		/* A list of attempts per section
		 */
		if (count($currentSection['attempts'])) {
			foreach($currentSection['attempts'] as $currentAttempt) {
				$currentResult = array();

				$currentResult[] = $currentAttempt['name'];

				/* A list of responses per attempt
				 */
				if (count($currentAttempt['responses'])) {
					foreach($currentAttempt['responses'] as $currentResponse) {
						$currentResult[] = $currentResponse;
					}
				}

				outputCSV2(array($currentResult));
			}
		}

		echo "\n";
	}
}

/* Prevent other template crap from rendering ;)
 */
exit(1);
?>
