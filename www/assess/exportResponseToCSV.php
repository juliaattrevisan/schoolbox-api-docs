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
require_once('../library/assess/Assess.php');
require_once('../library/assess/AssessSection.php');
require_once('../library/assess/AssessQuestion.php');
require_once('../library/assess/AssessAnswer.php');
require_once('../library/assess/AssessFactory.php');
require_once('../library/assess/AssessAttempt.php');
require_once('../library/assess/AssessExporter.php');

$assessHandler = new Assess();
$sectionHandler = new AssessSection();
$factory = new AssessFactory();
$attemptHandler = new AssessAttempt();

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

$assessExporter = new AssessExporter($assess, $factory, new AssessSection(), new AssessAttempt());
$sectionValues = $assessExporter->getSectionValues();

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
				$questionList[] = $currentQuestion['question'];
			}
		}

		/* Output to stdout stream
		 */
		echo 'Section ' . $currentSection['section'] . "\n";
		$assessExporter->outputCSV2(array($questionList));

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

				$assessExporter->outputCSV2(array($currentResult));
			}
		}

		echo "\n";
	}
}

/* Prevent other template crap from rendering ;)
 */
exit(1);
?>
